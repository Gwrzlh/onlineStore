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
        // validasi input
        $validate = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        // Pengkondisian hasil validasi
        if($validate->fails()){
            return response()->json([
                'success' => false,
                'message' => "validasi gagal,Data Tidak sesuai",
                'errors' => $validate->errors()
            ], 422);
        }

        // Mengambil data produk dan stock
        $item = $request->items[0];
        $productId = $item['product_id'];
        $quantityToBuy = $item['quantity'];
        $redisKey = "product:{$productId}:stock";


        // mengecek apakah key stok sudak ada di redis atau belum
        if (!Redis::exists($redisKey)) {
                $product = Product::find($productId);
                Redis::set($redisKey, $product->stock);
            }
        $remainingStock = Redis::decrby($redisKey, $quantityToBuy);

        if ($remainingStock < 0) {
            Redis::incrby($redisKey, $quantityToBuy);
            return response()->json([
                'success' => false,
                'message' => 'Maaf, stok produk sudah habis atau tidak mencukupi!'
            ], 400);
        }

        try {
            $order = DB::transaction(function () use ($productId, $quantityToBuy) {
                $product = Product::find($productId);
                
                // Sinkronisasi stok di MySQL agar tetap akurat dengan Redis
                $product->decrement('stock', $quantityToBuy);

                // Buat Nota Utama
                $order = Order::create([
                    'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                    'total_price' => $product->price * $quantityToBuy,
                ]);

                // Buat Detail Barang
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
            // Jika database MySQL bermasalah, kembalikan stok Redis
            Redis::incrby($redisKey, $quantityToBuy);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem, silakan coba lagi.'
            ], 500);
        }
            
    }
}
