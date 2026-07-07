<?php

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('it can handle race condition during flash sale', function () {
    // menyiapkan data produk contoh di MySQL dengan stok 10 item
    $product = Product::create([
        'name' => 'Sepatu Limited Flash Sale',
        'price' => 50000.00,
        'stock' => 10,
    ]);

    //menyiapkan payload JSON
    $payload = [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
            ]
        ]
    ];

    // Simulasi Pembelian Massal (Kirim 20 request berurutan)
    $totalRequests = 20;
    $responses = [];

    for ($i = 0; $i < $totalRequests; $i++) {
        $responses[] = postJson('/orders', $payload);
    }

    // A. Cek Stok Akhir di MySQL harus TEPAT 0
    $updatedProduct = Product::find($product->id);
    expect($updatedProduct->stock)->toEqual(0);

    // B. Hitung total item yang berhasil terjual di database harus TEPAT 10
    $totalOrderItemsCreated = OrderItem::where('product_id', $product->id)->sum('quantity');
    expect($totalOrderItemsCreated)->toEqual(10);

    // C. Pastikan tepat 10 sukses (201) dan 10 gagal (400)
    $successCount = 0;
    $failedCount = 0;

    foreach ($responses as $response) {
        if ($response->status() === 201) {
            $successCount++;
        } elseif ($response->status() === 400) {
            $failedCount++;
        }
    }

    expect($successCount)->toEqual(10);
    expect($failedCount)->toEqual(10);
});