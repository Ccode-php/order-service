<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        return Order::with('items')->get();
    }

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

            // 1️⃣ Faqat stockni TEKSHIRAMIZ
            $variantRes = Http::withHeaders([
                'Authorization' => $request->header('Authorization')
            ])->get(
                env('PRODUCT_SERVICE_URL') . "/api/variants/{$item['variant_id']}"
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

        // 2️⃣ Order yaratamiz (pending)
        $order = Order::create([
            'user_id' => $user['id'],
            'total_price' => $total,
            'status' => 'pending',
        ]);

        // 3️⃣ Order itemlarni saqlaymiz
        foreach ($orderItems as $oi) {
            $order->items()->create($oi);
        }

        // ❌ PAYMENT YO‘Q
        // ❌ STOCK KAMAYTIRISH YO‘Q

        return response()->json([
            'message' => 'Order created',
            'order' => $order->load('items')
        ], 201);
    }

    /**
     * Payment SUCCESS bo‘lganda chaqiriladi
     */


    public function markAsPaid(Request $request, $id)
    {
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        try {
            // 1️⃣ ORDER PAID
            $order->update(['status' => 'paid']);

            // 2️⃣ STOCK DECREASE
            foreach ($order->items as $item) {

                $res = Http::withHeaders([
                    'Authorization' => $request->header('Authorization')
                ])->post(
                    env('PRODUCT_SERVICE_URL') .
                        "/api/variants/{$item->variant_id}/decrease-stock",
                    [
                        'quantity' => $item->quantity
                    ]
                );

                if (!$res->ok()) {
                    throw new \Exception('Stock decrease failed');
                }
            }

            return ['success' => true];
        } catch (\Throwable $e) {

            // 🧯 ROLLBACK
            $order->update(['status' => 'cancelled']);

            // payment service’ga xabar beramiz
            Http::post(
                env('PAYMENT_SERVICE_URL') .
                    "/api/payments/{$order->id}/refund"
            );

            return response()->json([
                'error' => 'Order rollback executed'
            ], 500);
        }
    }

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
            'total'    => $order->total_price,
            'status'   => $order->status,
        ]);
    }
}
