## 📑 Dokumentasi API Endpoint

### 1. Create New Order (Proses Pesanan Flash Sale)
Endpoint ini digunakan untuk mengirim pesanan baru dan sudah dilengkapi sistem anti-race condition.

* **URL:** `/api/orders`
* **Method:** `POST`
* **Headers:** 
  ```http
  Accept: application/json
  Content-Type: application/json

  JSON
 
    {
        "items": [
            {
            "product_id": 1,
            "quantity": 1
            }
        ]
    }

Response Sukses 
    {
        "success": true,
        "message": "Pesanan berhasil dibuat (Lolos Flash Sale!).",
        "data": {
            "id": 12,
            "order_number": "ORD-ABCDE12345",
            "total_price": 50000,
            "items": [
            {
                "product_id": 1,
                "quantity": 1,
                "price": 50000
            }
            ]
        }
    }
Response Gagal
    {
        "success": false,
        "message": "Maaf, stok produk sudah habis atau tidak mencukupi!"
    }