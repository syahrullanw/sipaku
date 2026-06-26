## Finance API Integration (Draft)

> Catatan: modul saat ini belum menyediakan API publik. Gunakan spesifikasi ini sebagai rancangan awal sebelum exposing endpoint.

### 1. Autentikasi
- Gunakan token bearer (JWT atau token statis) per klien.
- Endpoint otentikasi direncanakan: `POST /api/finance/token`.
- Scope disarankan: `billing.read`, `payment.create`, `honor.read`.

### 2. Endpoint Rencana

| Endpoint | Method | Deskripsi | Scope |
| --- | --- | --- | --- |
| `/api/finance/billings` | GET | List tagihan aktif (filter kategori, status). | `billing.read` |
| `/api/finance/billings/{id}` | GET | Detail tagihan + item siswa. | `billing.read` |
| `/api/finance/payments` | POST | Submit pembayaran (untuk integrasi payment gateway). | `payment.create` |
| `/api/finance/honors` | GET | Slip honor guru (filter periode). | `honor.read` |

### 3. Format Respon
Gunakan JSON standar:
```json
{
  "data": [...],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 45
  }
}
```

### 4. Keamanan
- Validasi signature setiap request (HMAC).
- Rate limit per IP/Client.
- Audit log simpan ke `finance.log`.
