<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | USER: Order list
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $user = $request->get('auth_user');

        return Order::with('items')
            ->where('user_id', $user['id'])
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | USER: Create Order
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $user = $request->get('auth_user');

        if (!$user || !isset($user['id'])) {
            return response()->json(['error' => 'User not found'], 401);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $total = 0;
        $orderItems = [];

        foreach ($request->items as $item) {

            $variantRes = Http::withHeaders([
                'Authorization' => $request->header('Authorization')
            ])->get(
                env('PRODUCT_SERVICE_URL') . "/api/product/variants/{$item['variant_id']}"
            );

            if (!$variantRes->ok()) {
                return response()->json(['error' => 'Variant not found'], 404);
            }

            $variant = $variantRes->json();

            if ($variant['stock'] < $item['quantity']) {
                return response()->json(['error' => 'Not enough stock'], 400);
            }

            $price = $variant['price'];
            $total += $price * $item['quantity'];

            $orderItems[] = [
                'product_id' => $variant['product_id'],
                'variant_id' => $item['variant_id'],
                'quantity' => $item['quantity'],
                'price' => $price,
            ];
        }

        $order = Order::create([
            'user_id' => $user['id'],
            'total_price' => $total,
            'status' => 'pending',
        ]);

        foreach ($orderItems as $oi) {
            $order->items()->create($oi);
        }

        return response()->json([
            'message' => 'Order created',
            'order' => $order->load('items')
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Order total
    |--------------------------------------------------------------------------
    */
    public function getOrderTotal($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'error' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'order_id' => $order->id,
            'user_id'  => $order->user_id,
            'total'    => $order->total_price,
            'status'   => $order->status,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL: Mark as paid
    |--------------------------------------------------------------------------
    */
    public function markAsPaid(Request $request, $id)
    {
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json([
                'error' => 'Order not found'
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | PAYMENT SERVICEDAN KELGAN SERVICE TOKEN
    |--------------------------------------------------------------------------
    */

        $serviceToken = $request->bearerToken();

        try {

            $order->update([
                'status' => 'paid'
            ]);

            foreach ($order->items as $item) {

                $res = Http::withToken($serviceToken)->post(
                    env('PRODUCT_SERVICE_URL') .
                        "/api/product/variants/{$item->variant_id}/decrease-stock",
                    [
                        'quantity' => $item->quantity
                    ]
                );

                if (!$res->ok()) {

                    throw new \Exception(
                        'Stock decrease failed: ' . $res->body()
                    );
                }
            }

            return response()->json([
                'success' => true
            ]);
        } catch (\Throwable $e) {

            $order->update([
                'status' => 'cancelled'
            ]);

            return response()->json([
                'error' => 'Order rollback executed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
