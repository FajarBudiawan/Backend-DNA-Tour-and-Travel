# Dokumentasi API Modul Expenses & Ringkasan Keuangan

Dokumen ini berisi spesifikasi teknis dan contoh payload request/response untuk modul **Expenses (Pengeluaran Kas)** dan endpoint **Finance Summary (Ringkasan Keuangan)** pada backend DNA Tour & Travel.

---

## 1. Modul Expenses (Pengeluaran Kas)

Semua endpoint pengeluaran kas berada di bawah middleware `auth:sanctum` dan memerlukan Header `Authorization: Bearer <token>`.

---

### A. List & Filter Pengeluaran Kas
- **HTTP Method**: `GET`
- **URL**: `/api/expenses`
- **Query Parameters (Opsional)**:
  - `q` (string): Pencarian berdasarkan nama vendor atau nomor referensi.
  - `category` (enum): Filter kategori (`akomodasi_tiket`, `perlengkapan`, `operasional_bus`).
  - `payment_method` (enum): Filter metode pembayaran (`bca_transfer`, `mandiri_transfer`, `bsi_transfer`, `cash`, `edc_qris`).
  - `date_from` (date): Tanggal mulai pengeluaran (format `YYYY-MM-DD`).
  - `date_to` (date): Tanggal akhir pengeluaran (format `YYYY-MM-DD`).

#### Example Request:
```http
GET /api/expenses?q=Garuda&date_from=2026-08-01&date_to=2026-08-31 HTTP/1.1
Authorization: Bearer 1|sanctum_token_here
Accept: application/json
```

#### Example Response (200 OK):
```json
{
  "message": "Daftar pengeluaran kas berhasil diambil.",
  "summary": {
    "akomodasi_tiket": 45000000.00,
    "perlengkapan": 12500000.00,
    "operasional_bus": 8000000.00,
    "total_expense": 65500000.00
  },
  "data": [
    {
      "id": "9b1deb4d-3b7d-4b9b-8a21-91f9b3e1a001",
      "vendor": "PT Garuda Indonesia",
      "category": "akomodasi_tiket",
      "amount": "45000000.00",
      "payment_method": "bca_transfer",
      "expense_date": "2026-08-15",
      "reference_number": "TRX-2026-001",
      "notes": "Pembayaran deposit tiket pesawat kloter 1",
      "recorded_by": "9a38f712-4c22-4876-90ab-123456789abc",
      "created_at": "2026-08-15T09:30:00.000000Z",
      "updated_at": "2026-08-15T09:30:00.000000Z",
      "recorded_by": {
        "id": "9a38f712-4c22-4876-90ab-123456789abc",
        "full_name": "Admin Keuangan",
        "email": "finance@dnatour.co.id",
        "role_id": "9a123456-7890-abcd-ef01-234567890abc"
      }
    }
  ]
}
```

---

### B. Tambah Pengeluaran Kas Baru
- **HTTP Method**: `POST`
- **URL**: `/api/expenses`
- **Request Body Fields**:
  - `vendor` (required, string, max: 150): Nama vendor/penerima.
  - `category` (required, enum): `akomodasi_tiket`, `perlengkapan`, `operasional_bus`.
  - `amount` (required, numeric, gt: 0): Nominal pengeluaran.
  - `payment_method` (required, enum): `bca_transfer`, `mandiri_transfer`, `bsi_transfer`, `cash`, `edc_qris`.
  - `expense_date` (required, date): Tanggal pengeluaran (`YYYY-MM-DD`).
  - `reference_number` (optional, string, max: 50): Nomor TRX/referensi. Jika dikosongkan, sistem akan otomatis men-generate format `TRX-YYYY-XXX`.
  - `notes` (optional, string, max: 255): Catatan tambahan.

#### Example Request:
```http
POST /api/expenses HTTP/1.1
Authorization: Bearer 1|sanctum_token_here
Content-Type: application/json
Accept: application/json

{
  "vendor": "CV Perlengkapan Umrah Nusantara",
  "category": "perlengkapan",
  "amount": 12500000,
  "payment_method": "mandiri_transfer",
  "expense_date": "2026-08-20",
  "notes": "Pengadaan koper & kain ihram 50 set"
}
```

#### Example Response (201 Created):
```json
{
  "message": "Pengeluaran kas berhasil dicatat.",
  "data": {
    "id": "9b2c3d4e-5f6a-7b8c-9d0e-1f2a3b4c5d6e",
    "vendor": "CV Perlengkapan Umrah Nusantara",
    "category": "perlengkapan",
    "amount": "12500000.00",
    "payment_method": "mandiri_transfer",
    "expense_date": "2026-08-20",
    "reference_number": "TRX-2026-002",
    "notes": "Pengadaan koper & kain ihram 50 set",
    "recorded_by": "9a38f712-4c22-4876-90ab-123456789abc",
    "created_at": "2026-08-26T10:15:00.000000Z",
    "updated_at": "2026-08-26T10:15:00.000000Z",
    "recorded_by": {
      "id": "9a38f712-4c22-4876-90ab-123456789abc",
      "full_name": "Admin Keuangan",
      "email": "finance@dnatour.co.id"
    }
  }
}
```

---

### C. Detail Pengeluaran Kas
- **HTTP Method**: `GET`
- **URL**: `/api/expenses/{id}`

#### Example Request:
```http
GET /api/expenses/9b2c3d4e-5f6a-7b8c-9d0e-1f2a3b4c5d6e HTTP/1.1
Authorization: Bearer 1|sanctum_token_here
Accept: application/json
```

#### Example Response (200 OK):
```json
{
  "message": "Detail pengeluaran kas berhasil diambil.",
  "data": {
    "id": "9b2c3d4e-5f6a-7b8c-9d0e-1f2a3b4c5d6e",
    "vendor": "CV Perlengkapan Umrah Nusantara",
    "category": "perlengkapan",
    "amount": "12500000.00",
    "payment_method": "mandiri_transfer",
    "expense_date": "2026-08-20",
    "reference_number": "TRX-2026-002",
    "notes": "Pengadaan koper & kain ihram 50 set",
    "recorded_by": "9a38f712-4c22-4876-90ab-123456789abc",
    "created_at": "2026-08-26T10:15:00.000000Z",
    "updated_at": "2026-08-26T10:15:00.000000Z",
    "recorded_by": {
      "id": "9a38f712-4c22-4876-90ab-123456789abc",
      "full_name": "Admin Keuangan",
      "email": "finance@dnatour.co.id"
    }
  }
}
```

---

### D. Update Data Pengeluaran Kas
- **HTTP Method**: `PUT`
- **URL**: `/api/expenses/{id}`
- **Request Body Fields (All Optional / Sometimes)**:
  - `vendor` (string, max: 150)
  - `category` (enum: `akomodasi_tiket`, `perlengkapan`, `operasional_bus`)
  - `amount` (numeric, gt: 0)
  - `payment_method` (enum)
  - `expense_date` (date)
  - `reference_number` (string, max: 50, unique)
  - `notes` (string, max: 255)

#### Example Request:
```http
PUT /api/expenses/9b2c3d4e-5f6a-7b8c-9d0e-1f2a3b4c5d6e HTTP/1.1
Authorization: Bearer 1|sanctum_token_here
Content-Type: application/json
Accept: application/json

{
  "amount": 13000000,
  "notes": "Pengadaan koper & kain ihram 50 set + penambahan syal"
}
```

#### Example Response (200 OK):
```json
{
  "message": "Data pengeluaran kas berhasil diperbarui.",
  "data": {
    "id": "9b2c3d4e-5f6a-7b8c-9d0e-1f2a3b4c5d6e",
    "vendor": "CV Perlengkapan Umrah Nusantara",
    "category": "perlengkapan",
    "amount": "13000000.00",
    "payment_method": "mandiri_transfer",
    "expense_date": "2026-08-20",
    "reference_number": "TRX-2026-002",
    "notes": "Pengadaan koper & kain ihram 50 set + penambahan syal",
    "recorded_by": "9a38f712-4c22-4876-90ab-123456789abc",
    "created_at": "2026-08-26T10:15:00.000000Z",
    "updated_at": "2026-08-26T10:20:00.000000Z",
    "recorded_by": {
      "id": "9a38f712-4c22-4876-90ab-123456789abc",
      "full_name": "Admin Keuangan",
      "email": "finance@dnatour.co.id"
    }
  }
}
```

---

### E. Hapus Pengeluaran Kas
- **HTTP Method**: `DELETE`
- **URL**: `/api/expenses/{id}`

#### Example Request:
```http
DELETE /api/expenses/9b2c3d4e-5f6a-7b8c-9d0e-1f2a3b4c5d6e HTTP/1.1
Authorization: Bearer 1|sanctum_token_here
Accept: application/json
```

#### Example Response (200 OK):
```json
{
  "message": "Data pengeluaran kas berhasil dihapus."
}
```

---

## 2. Ringkasan Keuangan Global (Finance Summary)

Endpoint ini digunakan oleh Dashboard Keuangan frontend untuk menampilkan card indikator performa keuangan utama.

---

### A. GET Finance Summary
- **HTTP Method**: `GET`
- **URL**: `/api/finance/summary`

#### Description Result Fields:
- `total_pemasukan`: Jumlah total pembayaran dari jamaah (`SUM(amount)` dari tabel `registration_payments`).
- `total_pengeluaran`: Jumlah total pengeluaran kas (`SUM(amount)` dari tabel `expenses`).
- `total_piutang`: Jumlah sisa pembayaran dari pendaftaran aktif yang belum lunas (`SUM(remaining_cost)`).
- `saldo_bersih`: Selisih antara total pemasukan dan total pengeluaran (`total_pemasukan - total_pengeluaran`).

#### Example Request:
```http
GET /api/finance/summary HTTP/1.1
Authorization: Bearer 1|sanctum_token_here
Accept: application/json
```

#### Example Response (200 OK):
```json
{
  "message": "Ringkasan keuangan berhasil diambil.",
  "data": {
    "total_pemasukan": 450000000.00,
    "total_pengeluaran": 65500000.00,
    "total_piutang": 120000000.00,
    "saldo_bersih": 384500000.00
  }
}
```
