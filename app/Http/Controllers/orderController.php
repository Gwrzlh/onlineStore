<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class orderController extends Controller
{
    public function store(Request $request)
    {
       // 1. Validasi input
        $validate = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => "validasi gagal, Data Tidak sesuai",
                'errors' => $validate->errors()
            ], 422);
        }

        $item = $request->items[0];
        $productId = $item['product_id'];
        $quantityToBuy = $item['quantity'];

        try {
            // 2. Menjalankan Transaksi Database dengan Row-Level Locking
            $order = DB::transaction(function () use ($productId, $quantityToBuy) {
                
                $product = Product::where('id', $productId)->lockForUpdate()->first();

                // 3. pengecekan stok
                if ($product->stock < $quantityToBuy) {
                    throw new \Exception('Stok habis', 400);
                }
                $product->decrement('stock', $quantityToBuy);

                // 5. Buat Nota Utama
                $order = Order::create([
                    'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                    'total_price' => $product->price * $quantityToBuy,
                ]);

                // 6. Buat Detail Barang
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantityToBuy,
                    'price' => $product->price,
                ]);

                return $order;
            });

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat (Lolos Flash Sale!).',
                'data' => $order->load('items')
            ], 201);

        } catch (\Exception $e) {
            // Jika Exception dipicu karena stok habis (Code 400)
            if ($e->getCode() === 400) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maaf, stok produk sudah habis atau tidak mencukupi!'
                ], 400);
            }
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem, silakan coba lagi.'
            ], 500);
        }
    }
}
