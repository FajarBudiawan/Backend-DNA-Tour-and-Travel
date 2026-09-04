# DNA Tour & Travel - Backend API Documentation

Selamat datang di Dokumentasi Resmi API Backend DNA Tour & Travel (Umrah Management System).
Dokumentasi ini dibuat secara independen dari source code tanpa mengubah business logic maupun controller application.

---

## Informasi Umum & Autentikasi

* **Base URL**: `http://localhost:8000/api` (sesuai environment)
* **Format Response**: `JSON`
* **Header Default**:
  ```http
  Content-Type: application/json
  Accept: application/json
  ```
* **Mekanisme Autentikasi**:
  Sebagian besar endpoint memerlukan Bearer Token via Laravel Sanctum.
  ```http
  Authorization: Bearer {token}
  ```
  Token diperoleh melalui endpoint `POST /api/login`.

---

## Ringkasan Modul & Endpoint (Total 42 Endpoints)

| Modul | Method | Endpoint | Auth Required | Status |
| :--- | :--- | :--- | :---: | :---: |
| **Authentication** | `POST` | `/api/login` | No | Active |
| **Authentication** | `POST` | `/api/logout` | Yes | Active |
| **Authentication** | `GET` | `/api/me` | Yes | Active |
| **Master Hotel** | `GET` | `/api/hotels` | Yes | Active |
| **Master Hotel** | `POST` | `/api/hotels/find-or-create` | Yes | Active |
| **Master Paket Umrah** | `GET` | `/api/packages` | Yes | Active |
| **Master Paket Umrah** | `POST` | `/api/packages` | Yes | Active |
| **Master Paket Umrah** | `GET` | `/api/packages/{package}` | Yes | Active |
| **Master Paket Umrah** | `PUT` | `/api/packages/{package}` | Yes | Active |
| **Master Paket Umrah** | `DELETE` | `/api/packages/{package}` | Yes | Active |
| **Template Itinerary Paket**| `GET` | `/api/packages/{package}/itineraries` | Yes | Active |
| **Template Itinerary Paket**| `POST` | `/api/packages/{package}/itineraries` | Yes | Active |
| **Template Itinerary Paket**| `PUT` | `/api/packages/{package}/itineraries/{itinerary}` | Yes | Active |
| **Template Itinerary Paket**| `DELETE` | `/api/packages/{package}/itineraries/{itinerary}` | Yes | Active |
| **Kloter Keberangkatan** | `GET` | `/api/kloters` | Yes | Active |
| **Kloter Keberangkatan** | `POST` | `/api/kloters` | Yes | Active |
| **Kloter Keberangkatan** | `GET` | `/api/kloters/{kloter}` | Yes | Active |
| **Kloter Keberangkatan** | `PUT` | `/api/kloters/{kloter}` | Yes | Active |
| **Kloter Keberangkatan** | `DELETE` | `/api/kloters/{kloter}` | Yes | Active |
| **Rundown Jadwal Kloter** | `GET` | `/api/kloters/{kloter}/schedules` | Yes | Active |
| **Rundown Jadwal Kloter** | `POST` | `/api/kloters/{kloter}/schedules` | Yes | Active |
| **Rundown Jadwal Kloter** | `PUT` | `/api/kloters/{kloter}/schedules/{schedule}` | Yes | Active |
| **Rundown Jadwal Kloter** | `DELETE` | `/api/kloters/{kloter}/schedules/{schedule}` | Yes | Active |
| **Rundown Jadwal Kloter** | `POST` | `/api/kloters/{kloter}/schedules/generate-from-template` | Yes | Active |
| **Pembagian Kamar (Room Meet)** | `GET` | `/api/kloters/{kloter}/rooms` | Yes | Active |
| **Pembagian Kamar (Room Meet)** | `POST` | `/api/kloters/{kloter}/auto-assign-rooms` | Yes | Active |
| **Pembagian Kamar (Room Meet)** | `POST` | `/api/rooms` | Yes | Active |
| **Pembagian Kamar (Room Meet)** | `GET` | `/api/rooms/{room}` | Yes | Active |
| **Pembagian Kamar (Room Meet)** | `PUT` | `/api/rooms/{room}` | Yes | Active |
| **Pembagian Kamar (Room Meet)** | `DELETE` | `/api/rooms/{room}` | Yes | Active |
| **Pembagian Kamar (Room Meet)** | `POST` | `/api/rooms/{room}/members` | Yes | Active |
| **Pembagian Kamar (Room Meet)** | `PUT` | `/api/rooms/{room}/members/{roomMember}` | Yes | Active |
| **Pembagian Kamar (Room Meet)** | `DELETE` | `/api/rooms/{room}/members/{roomMember}` | Yes | Active |
| **Pendaftaran Jamaah** | `GET` | `/api/registrations` | Yes | Active |
| **Pendaftaran Jamaah** | `POST` | `/api/registrations` | Yes | Active |
| **Pendaftaran Jamaah** | `GET` | `/api/registrations/{registration}` | Yes | Active |
| **Pendaftaran Jamaah** | `PUT` | `/api/registrations/{registration}` | Yes | Active |
| **Pendaftaran Jamaah** | `DELETE` | `/api/registrations/{registration}` | Yes | Active |
| **Pendaftaran Jamaah** | `POST` | `/api/registrations/{registration}/cancel` | Yes | Active |
| **Pembayaran Pendaftaran** | `GET` | `/api/payments` | Yes | Active |
| **Pembayaran Pendaftaran** | `GET` | `/api/registrations/{registration}/payments` | Yes | Active |
| **Pembayaran Pendaftaran** | `POST` | `/api/registrations/{registration}/payments` | Yes | Active |
| **Pengeluaran Kas** | `GET` | `/api/expenses` | Yes | Active |
| **Pengeluaran Kas** | `POST` | `/api/expenses` | Yes | Active |
| **Pengeluaran Kas** | `GET` | `/api/expenses/{expense}` | Yes | Active |
| **Pengeluaran Kas** | `PUT` | `/api/expenses/{expense}` | Yes | Active |
| **Pengeluaran Kas** | `DELETE` | `/api/expenses/{expense}` | Yes | Active |
| **Ringkasan Keuangan** | `GET` | `/api/finance/summary` | Yes | Active |
| **Manajemen Jamaah** | `GET` | `/api/jamaah` | Yes | Active |
| **Manajemen Jamaah** | `POST` | `/api/jamaah` | Yes | Active |
| **Manajemen Jamaah** | `GET` | `/api/jamaah/{jamaah}` | Yes | Active |
| **Manajemen Jamaah** | `PUT` | `/api/jamaah/{jamaah}` | Yes | Active |
| **Manajemen Jamaah** | `DELETE` | `/api/jamaah/{jamaah}` | Yes | Active |

---

## Detail Rincian Endpoint per Modul

### 1. Authentication

#### `POST /api/login`
* **Auth**: No
* **Deskripsi**: Login akun internal (Admin/Staff) untuk mendapatkan Bearer Token.
* **Request Body**:
  * `email` (string, required, email): Email akun.
  * `password` (string, required): Password akun.
* **Response Contoh (200 OK)**:
  ```json
  {
    "user": {
      "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
      "name": "Admin Utama",
      "email": "admin@dnatour.com",
      "role": "admin",
      "status": "active"
    },
    "token": "1|laravel_sanctum_token_example..."
  }
  ```

#### `POST /api/logout`
* **Auth**: Yes (Bearer Token)
* **Deskripsi**: Revoke current access token.
* **Response Contoh (200 OK)**:
  ```json
  {
    "message": "Berhasil logout"
  }
  ```

#### `GET /api/me`
* **Auth**: Yes (Bearer Token)
* **Deskripsi**: Mengambil profil user terautentikasi.
* **Response Contoh (200 OK)**:
  ```json
  {
    "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
    "name": "Admin Utama",
    "email": "admin@dnatour.com",
    "role": "admin"
  }
  ```

---

### 2. Master Hotel

#### `GET /api/hotels`
* **Auth**: Yes
* **Query Parameters**:
  * `city` (string, optional): Filter kota (`Makkah` atau `Madinah`).
* **Response Contoh (200 OK)**:
  ```json
  {
    "message": "Daftar hotel berhasil diambil.",
    "data": [
      {
        "id": "a0011223-3344-5566-7788-99aabbccdd01",
        "name": "Hotel Safwah Tower",
        "city": "Makkah",
        "status": "active"
      }
    ]
  }
  ```

#### `POST /api/hotels/find-or-create`
* **Auth**: Yes
* **Request Body**:
  * `name` (string, required, max:150): Nama hotel.
  * `city` (string, required, in:Makkah,Madinah): Kota hotel.
* **Response Contoh (200 OK / 201 Created)**:
  ```json
  {
    "message": "Hotel baru berhasil dibuat.",
    "data": {
      "id": "b1122334-4455-6677-8899-00aabbccdd02",
      "name": "Pullman Zamzam",
      "city": "Makkah",
      "status": "active"
    }
  }
  ```

---

### 3. Master Paket Umrah

#### `GET /api/packages`
* **Query Parameters**:
  * `q` (string, optional): Search keyword.
  * `status` (string, optional, in:draft,published,inactive): Filter status.
  * `is_featured` (boolean, optional): Filter paket unggulan.

#### `POST /api/packages`
* **Request Body**:
  * `name` (string, required, max:150)
  * `category` (string, optional, max:50)
  * `status` (string, optional, in:draft,published,inactive)
  * `is_featured` (boolean, optional)

#### `GET /api/packages/{package}`
* **Path Parameter**: `package` (UUID)

#### `PUT /api/packages/{package}`
* **Path Parameter**: `package` (UUID)
* **Request Body**:
  * `name` (string, sometimes, max:150)
  * `category` (string, optional, max:50)
  * `status` (string, sometimes, in:draft,published,inactive)
  * `is_featured` (boolean, sometimes)

#### `DELETE /api/packages/{package}`
* **Path Parameter**: `package` (UUID)
* **Guard**: Gagal jika paket memiliki kloter atau pendaftaran terikat (HTTP 422).

---

### 4. Template Itinerary Paket

#### `GET /api/packages/{package}/itineraries`
* **Path Parameter**: `package` (UUID Paket)

#### `POST /api/packages/{package}/itineraries`
* **Request Body**:
  * `day_number` (integer, required, min:1)
  * `title` (string, required, max:255)
  * `activity_time` (string, optional)
  * `location` (string, optional, max:255)
  * `category` (string, optional, max:100)
  * `description` (string, optional)

#### `PUT /api/packages/{package}/itineraries/{itinerary}`
* **Deskripsi**: Memperbarui template & otomatis meng-update rundown kloter yang `is_customized = false`.

#### `DELETE /api/packages/{package}/itineraries/{itinerary}`

---

### 5. Kloter Keberangkatan

#### `GET /api/kloters`
* **Query Parameters**: `q`, `status`, `package_id`

#### `POST /api/kloters`
* **Request Body**:
  * `name` (string, required, max:150)
  * `package_id` (uuid, optional, exists:packages,id)
  * `code` (string, optional, max:30, unique:kloters,code)
  * `flight_code` (string, optional, max:50)
  * `departure_date` (date, optional)
  * `return_date` (date, optional, after_or_equal:departure_date)
  * `hotel_makkah_id` (uuid, optional, exists:hotels,id)
  * `hotel_madinah_id` (uuid, optional, exists:hotels,id)
  * `status` (string, optional, in:draft,active,ready,completed,cancelled)
  * `tour_leader` (string, optional, max:200)
  * `mutawif_local` (string, optional, max:200)

#### `GET /api/kloters/{kloter}`
#### `PUT /api/kloters/{kloter}`
#### `DELETE /api/kloters/{kloter}`
* **Guard**: Gagal jika masih ada jamaah ter-assign via `jamaah.kloter_id` (HTTP 422).

---

### 6. Rundown Jadwal Kloter

#### `GET /api/kloters/{kloter}/schedules`
#### `POST /api/kloters/{kloter}/schedules` (Manual Store)
* **Note**: `is_customized` otomatis bernilai `true`.
#### `PUT /api/kloters/{kloter}/schedules/{schedule}` (Manual Edit)
* **Note**: Mengeset `is_customized = true` sehingga terlindungi dari re-sync template.
#### `DELETE /api/kloters/{kloter}/schedules/{schedule}`
#### `POST /api/kloters/{kloter}/schedules/generate-from-template`
* **Deskripsi**: Meng-generate/sinkronisasi rundown kegiatan kloter dari template paket berdasarkan `departure_date`.

---

### 7. Pembagian Kamar (Room Meet)

#### `GET /api/kloters/{kloter}/rooms`
#### `POST /api/kloters/{kloter}/auto-assign-rooms`
* **Request Body**: `room_type` (`quad`, `triple`, `double`, `single`, `quint`), `hotel_id` (UUID optional)
#### `POST /api/rooms`
* **Request Body**: `kloter_id` (uuid, required), `hotel_id` (uuid, optional), `room_number` (string, optional), `room_type` (in:quad,triple,double,single,quint, required), `gender` (in:L,P, required), `notes` (string, optional).
#### `GET /api/rooms/{room}`
#### `PUT /api/rooms/{room}`
#### `DELETE /api/rooms/{room}`
#### `POST /api/rooms/{room}/members`
* **Request Body**: `jamaah_id` (uuid, optional), `title` (required_without:jamaah_id, in:MR,MRS,MISS,MSTR), `occupant_name` (required_without:jamaah_id, string), `age` (integer, optional).
#### `PUT /api/rooms/{room}/members/{roomMember}`
#### `DELETE /api/rooms/{room}/members/{roomMember}`

---

### 8. Pendaftaran Jamaah

#### `GET /api/registrations`
* **Query Parameters**: `q`, `status`, `package_id`, `kloter_id`
#### `POST /api/registrations`
* **Request Body**:
  * `full_name` (string, required, max:150)
  * `nik` (string, required, size:16, unique)
  * `phone` (string, required)
  * `birth_date` (date, required, before:today)
  * `gender` (string, required, in:L,P)
  * `registration_date` (date, required)
  * `departure_date` (date, optional)
  * `package_id` (uuid, required)
  * `kloter_id` (uuid, optional)
  * `meningitis_vaccine_status` (in:belum_vaksin,sudah_vaksin, required)
  * `photo_status` (in:belum_ada,sudah_menyerahkan, required)
  * `total_package_cost` (numeric, required, min:0)
  * `initial_payment` (object, optional): `{ amount, payment_type, payment_method, payment_date }`
  * `equipments` (array, optional): `[{ equipment_name, size, is_received }]`
#### `GET /api/registrations/{registration}`
#### `PUT /api/registrations/{registration}`
#### `DELETE /api/registrations/{registration}`
#### `POST /api/registrations/{registration}/cancel`

---

### 9. Pembayaran Pendaftaran

#### `GET /api/payments`
* **Query Parameters**: `payment_method`, `payment_type`, `date_from`, `date_to`, `q`
#### `GET /api/registrations/{registration}/payments`
#### `POST /api/registrations/{registration}/payments`
* **Request Body**:
  * `amount` (numeric, required, gt:0)
  * `payment_type` (required, in:down_payment,full_payment)
  * `payment_method` (required, in:bca_transfer,mandiri_transfer,bsi_transfer,cash,edc_qris)
  * `payment_date` (date, required)
  * `notes` (string, optional)

---

### 10. Pengeluaran Kas (Expenses)

#### `GET /api/expenses`
* **Query Parameters**: `q`, `payment_method`, `date_from`, `date_to`, `category`
#### `POST /api/expenses`
* **Request Body**:
  * `vendor` (string, required, max:150)
  * `category` (required, in:akomodasi_tiket,perlengkapan,operasional_bus)
  * `amount` (numeric, required, gt:0)
  * `payment_method` (required, in:bca_transfer,mandiri_transfer,bsi_transfer,cash,edc_qris)
  * `expense_date` (date, required)
  * `reference_number` (string, optional, unique)
  * `notes` (string, optional)
#### `GET /api/expenses/{expense}`
#### `PUT /api/expenses/{expense}`
#### `DELETE /api/expenses/{expense}`

---

### 11. Ringkasan Keuangan (Finance Summary)

#### `GET /api/finance/summary`
* **Response Contoh (200 OK)**:
  ```json
  {
    "message": "Ringkasan keuangan berhasil diambil.",
    "data": {
      "total_pemasukan": 150000000.00,
      "total_pengeluaran": 45000000.00,
      "total_piutang": 30000000.00,
      "saldo_bersih": 105000000.00
    }
  }
  ```

---

### 12. Manajemen Jamaah

#### `GET /api/jamaah`
* **Query Parameters**: `q`, `status`, `kloter_id`, `package_id`
#### `POST /api/jamaah`
* **Request Body**:
  * `login_id` (string, required, max:10, unique)
  * `nik` (string, required, size:16, unique)
  * `full_name` (string, required, max:150)
  * `birth_date` (date, optional)
  * `gender` (in:L,P, optional)
  * `phone` (string, optional)
  * `emergency_contact` (string, optional)
  * `passport_number` (string, optional)
  * `visa_number` (string, optional)
  * `nationality` (string, optional, default: Indonesia)
  * `package_id` (uuid, optional)
  * `kloter_id` (uuid, optional)
  * `hotel_makkah` (string, optional)
  * `hotel_madinah` (string, optional)
  * `departure_date` (date, optional)
  * `return_date` (date, optional, after_or_equal:departure_date)
  * `tour_leader` (string, optional)
  * `mutawif_local` (string, optional)
  * `status` (in:active,archived, optional)
#### `GET /api/jamaah/{jamaah}`
#### `PUT /api/jamaah/{jamaah}`
#### `DELETE /api/jamaah/{jamaah}`

---

## Catatan Verifikasi & Integritas
* Seluruh 42 endpoint telah diverifikasi langsung terhadap `routes/api.php`, Eloquent Models, dan Form Requests.
* Tidak ada perubahan pada business logic, schema database, maupun source code controller dalam project.
