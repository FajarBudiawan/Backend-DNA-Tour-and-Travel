# 📘 Pedoman Pengembangan Backend — DNA Tour

> **Dokumen ini adalah acuan resmi** untuk seluruh pengerjaan backend.
> Terakhir diperbarui: **24 Agustus 2026**

---

## Daftar Isi

1. [Arsitektur & Teknologi](#1-arsitektur--teknologi)
2. [Status Implementasi per Modul](#2-status-implementasi-per-modul)
3. [Peta Tabel Database vs Implementasi](#3-peta-tabel-database-vs-implementasi)
4. [Masalah Arsitektur yang Harus Diselesaikan](#4-masalah-arsitektur-yang-harus-diselesaikan)
5. [Prioritas Pengerjaan](#5-prioritas-pengerjaan)
6. [Konvensi & Aturan Kode](#6-konvensi--aturan-kode)
7. [Referensi Endpoint API](#7-referensi-endpoint-api)

---

## 1. Arsitektur & Teknologi

| Komponen | Detail |
|---|---|
| **Framework** | Laravel 13.25.0 |
| **PHP** | 8.4.24 |
| **Database** | PostgreSQL (dengan PostGIS untuk geofence) |
| **Auth** | Laravel Sanctum (Bearer Token) |
| **UUID** | Semua tabel pakai UUID sebagai primary key (`gen_random_uuid()`) |
| **Session** | Database driver |

### Struktur Direktori Utama

```
Backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/    ← Semua controller API
│   │   └── Requests/           ← Form Request validation
│   └── Models/                 ← Eloquent models
├── database/
│   ├── migrations/             ← 42 file migration (36 tabel)
│   └── seeders/                ← RoleSeeder, InternalUserSeeder, PackageSeeder
├── routes/
│   └── api.php                 ← Semua route API (34 endpoint aktif)
└── bootstrap/
    └── app.php                 ← Konfigurasi middleware & exception
```

---

## 2. Status Implementasi per Modul

### ✅ SELESAI — Siap Diintegrasikan ke Frontend

Modul-modul ini sudah punya **Migration + Model + Controller + Route + Form Request** lengkap.

| # | Modul | Controller | Endpoint | Catatan |
|---|---|---|---|---|
| 1 | **Auth** | `AuthController` | 3 (login, logout, me) | Pakai Sanctum token |
| 2 | **Paket Umrah** | `PackageController` | 5 (CRUD) | — |
| 3 | **Kloter / Groups** | `KloterController` | 5 (CRUD) | Filter: q, status, package_id |
| 4 | **Pendaftaran** | `RegistrationController` | 7 (CRUD + cancel + convert) | Auto financial status |
| 5 | **Pembayaran** | `RegistrationPaymentController` | 3 (list all, list per reg, store) | Auto update status registrasi |
| 6 | **Jamaah / Pilgrims** | `JamaahController` | 5 (CRUD) | — |
| 7 | **Kamar & Roommate** | `RoomController` | 6 (CRUD + auto-assign + member mgmt) | — |

**Total: 7 modul, 34 endpoint aktif.**

---

### ⚠️ TABEL DB SUDAH ADA — Perlu Buat Model + Controller + Route

Tabel sudah di-migrate ke database, tapi belum ada kode aplikasi (Model/Controller/Route). **Effort: kecil–sedang** karena skema DB sudah jadi.

| # | Modul (Frontend) | Tabel yang Sudah Ada | Yang Perlu Dibuat |
|---|---|---|---|
| 8 | **Tour Leaders** | `tour_leaders`, `kloter_leader_assignments` | Model, Controller (CRUD + assign ke kloter), Route |
| 9 | **Families** | `family_relations` | Model, Controller (CRUD), Route |
| 10 | **Journey / Jadwal** | `kloter_schedules`, `package_itineraries` | Model, Controller (CRUD per kloter), Route |
| 11 | **Broadcast** | `broadcasts`, `broadcast_recipient_kloters`, `broadcast_logs` | 3 Model, Controller (kirim + list), Route |
| 12 | **Emergency / SOS** | `sos_incidents`, `sos_status_histories`, `early_warning_events` | 3 Model, Controller (trigger + update status), Route |
| 13 | **Live Monitoring** | `trackings`, `geofence_zones`, `meeting_points`, `attendance_sessions`, `attendances` | 5 Model, Controller(s), Route |
| 14 | **Device/Audit Logs** | `audit_logs` | Model, Controller (read-only list + filter), Route |
| 15 | **Dashboard** | *(tidak butuh tabel baru)* | Controller dengan aggregation query dari tabel existing |
| 16 | **Reports** | *(tidak butuh tabel baru)* | Controller dengan reporting query dari tabel existing |
| 17 | **Access Control** | `roles`, `internal_users` *(sudah ada)* | RoleController (CRUD), UserManagementController (CRUD), Permission middleware |
| 18 | **Hotel** (master data) | `hotels` *(Model sudah ada)* | Controller (CRUD), Route |
| 19 | **Document Checklist** | `document_checklists` | Model, Controller (CRUD per jamaah), Route |

---

### ❌ BELUM ADA SAMA SEKALI — Perlu Desain DB + Implementasi Penuh

Tidak ada migration, model, maupun controller. **Perlu desain dari nol.**

| # | Modul (Frontend) | Deskripsi | Yang Perlu Dibuat |
|---|---|---|---|
| 20 | **Mutawifs** | Data pembimbing ibadah | Migration + Model + Controller + Route |
| 21 | **Staff Stock** | Data stok/perlengkapan staff | Migration + Model + Controller + Route |
| 22 | **System Settings** | Pengaturan sistem (key-value) | Migration + Model + Controller + Route |

---

### Tabel Pendukung yang Perlu Model (Belum Ada Controller Tersendiri)

Tabel-tabel ini sudah ada di DB tapi belum punya Model Eloquent. Biasanya dikelola sebagai sub-resource dari controller induk.

| Tabel | Induk | Cara Kelola |
|---|---|---|
| `package_facilities` | Package | Nested di PackageController atau sub-endpoint |
| `package_itineraries` | Package | Nested di PackageController atau sub-endpoint |
| `package_quotas` | Package | Nested di PackageController atau sub-endpoint |
| `package_pricings` | Package | Nested di PackageController atau sub-endpoint |
| `kloter_members` | Kloter | Endpoint di KloterController (`assign-member`, `remove-member`) |
| `kloter_leader_assignments` | Kloter / TourLeader | Endpoint assign/unassign TL ke kloter |
| `kloter_status_histories` | Kloter | Auto-log saat status kloter berubah |
| `kloter_buses` | Kloter | Nested di KloterController |
| `buses` | — | Butuh BusController (CRUD master data) |
| `broadcast_recipient_kloters` | Broadcast | Otomatis saat broadcast dibuat |
| `broadcast_logs` | Broadcast | Otomatis saat broadcast dikirim |
| `sos_status_histories` | SOS Incident | Auto-log saat status SOS berubah |
| `chat_messages` | Kloter | ChatController (butuh real-time, pertimbangkan WebSocket) |
| `notification_logs` | — | NotificationController (read-only log) |
| `registration_equipments` | Registration | Model sudah ada (`RegistrationEquipment`), endpoint belum |

---

## 3. Peta Tabel Database vs Implementasi

> Legenda: ✅ = Ada | ❌ = Tidak Ada | ⚠️ = Ada tapi tidak lengkap

| # | Tabel | Migration | Model | Controller | Route |
|---|---|---|---|---|---|
| 1 | `roles` | ✅ | ✅ | ❌ | ❌ |
| 2 | `internal_users` | ✅ | ✅ | ⚠️ login saja | ⚠️ |
| 3 | `jamaah` | ✅ | ✅ | ✅ | ✅ |
| 4 | `document_checklists` | ✅ | ❌ | ❌ | ❌ |
| 5 | `hotels` | ✅ | ✅ | ❌ | ❌ |
| 6 | `buses` | ✅ | ❌ | ❌ | ❌ |
| 7 | `packages` | ✅ | ✅ | ✅ | ✅ |
| 8 | `package_facilities` | ✅ | ❌ | ❌ | ❌ |
| 9 | `package_itineraries` | ✅ | ❌ | ❌ | ❌ |
| 10 | `package_quotas` | ✅ | ❌ | ❌ | ❌ |
| 11 | `package_pricings` | ✅ | ❌ | ❌ | ❌ |
| 12 | `tour_leaders` | ✅ | ❌ | ❌ | ❌ |
| 13 | `kloters` | ✅ | ✅ | ✅ | ✅ |
| 14 | `kloter_members` | ✅ | ❌ | ❌ | ❌ |
| 15 | `kloter_leader_assignments` | ✅ | ❌ | ❌ | ❌ |
| 16 | `kloter_schedules` | ✅ | ❌ | ❌ | ❌ |
| 17 | `kloter_status_histories` | ✅ | ❌ | ❌ | ❌ |
| 18 | `kloter_buses` | ✅ | ❌ | ❌ | ❌ |
| 19 | `family_relations` | ✅ | ❌ | ❌ | ❌ |
| 20 | `payments` | ✅ | ❌ | ❌ | ❌ |
| 21 | `registrations` | ✅ | ✅ | ✅ | ✅ |
| 22 | `registration_payments` | ✅ | ✅ | ✅ | ✅ |
| 23 | `registration_equipments` | ✅ | ✅ | ❌ | ❌ |
| 24 | `rooms` | ✅ | ✅ | ✅ | ✅ |
| 25 | `room_members` | ✅ | ✅ | ✅ | ✅ |
| 26 | `geofence_zones` | ✅ | ❌ | ❌ | ❌ |
| 27 | `early_warning_events` | ✅ | ❌ | ❌ | ❌ |
| 28 | `meeting_points` | ✅ | ❌ | ❌ | ❌ |
| 29 | `attendance_sessions` | ✅ | ❌ | ❌ | ❌ |
| 30 | `attendances` | ✅ | ❌ | ❌ | ❌ |
| 31 | `sos_incidents` | ✅ | ❌ | ❌ | ❌ |
| 32 | `sos_status_histories` | ✅ | ❌ | ❌ | ❌ |
| 33 | `trackings` | ✅ | ❌ | ❌ | ❌ |
| 34 | `broadcasts` | ✅ | ❌ | ❌ | ❌ |
| 35 | `broadcast_recipient_kloters` | ✅ | ❌ | ❌ | ❌ |
| 36 | `broadcast_logs` | ✅ | ❌ | ❌ | ❌ |
| 37 | `chat_messages` | ✅ | ❌ | ❌ | ❌ |
| 38 | `notification_logs` | ✅ | ❌ | ❌ | ❌ |
| 39 | `audit_logs` | ✅ | ❌ | ❌ | ❌ |
| 40 | `personal_access_tokens` | ✅ | ✅ (Sanctum) | — | — |
| 41 | `sessions` | ✅ | — (framework) | — | — |

**Skor: 36 tabel → 12 Model (33%) → 7 Controller (19%)**

---

## 4. Masalah Arsitektur yang Harus Diselesaikan

### 🔴 Prioritas Tinggi

#### 4.1 Tidak Ada Middleware Role/Permission

**Kondisi saat ini:**
```php
// bootstrap/app.php → middleware kosong
->withMiddleware(function (Middleware $middleware): void {
    //
})
```

**Dampak:** Siapapun yang berhasil login bisa mengakses **seluruh 34 endpoint** — termasuk hapus kloter, hapus jamaah, dll. Tidak ada pembatasan berdasarkan role.

**Solusi yang dibutuhkan:**
- Buat middleware `CheckRole` atau gunakan Spatie Laravel Permission
- Terapkan role-based access: `Administrator`, `Operator`, `Finance Admin`, `Owner`
- Contoh penggunaan:
  ```php
  Route::middleware(['auth:sanctum', 'role:Administrator'])->group(function () {
      // endpoint admin-only
  });
  ```

#### 4.2 Duplikasi Tabel Pembayaran

**Kondisi:** Ada 2 tabel pembayaran yang overlap:

| Tabel | FK Utama | Dipakai? |
|---|---|---|
| `payments` (migration 000021) | `jamaah_id` + `package_id` | ❌ Tidak ada Model / Controller |
| `registration_payments` (migration 2026_08_14) | `registration_id` | ✅ Dipakai aktif |

**Keputusan yang perlu diambil:**
- **Opsi A:** Hapus tabel `payments`, jadikan `registration_payments` sebagai satu-satunya tabel pembayaran
- **Opsi B:** Pertahankan keduanya — `payments` untuk pembayaran jamaah yang sudah ter-convert, `registration_payments` untuk pembayaran saat masih pendaftaran

#### 4.3 `kloter_members` Tidak Dipakai

**Kondisi:** Model `Kloter` punya relasi langsung ke `Registration`:
```php
// Kloter.php
public function registrations()
{
    return $this->hasMany(Registration::class);
}
```

Tapi tabel `kloter_members` (yang seharusnya jadi pivot resmi kloter ↔ jamaah) **tidak punya Model dan tidak digunakan.**

**Keputusan yang perlu diambil:**
- **Opsi A:** Hapus tabel `kloter_members`, membership tetap via `registrations.kloter_id`
- **Opsi B:** Gunakan `kloter_members` sebagai pivot resmi setelah Registration di-convert ke Jamaah

### 🟡 Prioritas Sedang

#### 4.4 Package Sub-Resources Tidak Terekspos

`PackageController` saat ini hanya mengelola tabel `packages`. Empat sub-tabel berikut **tidak bisa diakses via API:**

- `package_facilities` — fasilitas paket (hotel, makan, transportasi)
- `package_itineraries` — itinerary template
- `package_quotas` — kuota per paket
- `package_pricings` — daftar harga per tipe kamar

**Dampak:** Paket yang dibuat via API tidak bisa punya detail fasilitas, harga, dsb.

#### 4.5 Hotel Tidak Punya Controller

Model `Hotel` ada, tapi **tidak ada route/controller**. Padahal:
- `KloterController.store()` membutuhkan `hotel_makkah_id` dan `hotel_madinah_id`
- `RoomController` membuat Room yang terkait Hotel

Admin tidak punya cara untuk menambah/edit hotel via API.

---

## 5. Prioritas Pengerjaan

### Fase 1 — Perbaiki Fondasi (Minggu 1)
> Tujuan: Memperkuat modul yang sudah ada agar siap produksi.

| # | Task | Effort | Deskripsi |
|---|---|---|---|
| 1.1 | HotelController (CRUD) | Kecil | Model sudah ada, tinggal controller + route |
| 1.2 | BusController (CRUD) | Kecil | Tabel sudah ada, perlu Model + Controller |
| 1.3 | Package sub-resources | Sedang | 4 Model + endpoint nested di PackageController |
| 1.4 | TourLeaderController (CRUD) | Kecil | Tabel sudah ada |
| 1.5 | Putuskan `payments` vs `registration_payments` | Kecil | Arsitektur decision |
| 1.6 | Putuskan `kloter_members` | Kecil | Arsitektur decision |

### Fase 2 — Fitur Operasional (Minggu 2–3)
> Tujuan: Fitur pendukung operasional harian.

| # | Task | Effort | Deskripsi |
|---|---|---|---|
| 2.1 | FamilyRelationController | Kecil | CRUD relasi keluarga per jamaah |
| 2.2 | KloterScheduleController (Journey) | Sedang | CRUD jadwal per kloter |
| 2.3 | Kloter Member management | Sedang | Assign/remove jamaah & TL ke kloter |
| 2.4 | BroadcastController | Sedang | Kirim broadcast ke kloter |
| 2.5 | SosIncidentController (Emergency) | Sedang | Trigger, acknowledge, resolve SOS |
| 2.6 | AuditLogController | Kecil | Read-only list + filter |
| 2.7 | DocumentChecklistController | Kecil | CRUD checklist dokumen per jamaah |

### Fase 3 — Dashboard & Reporting (Minggu 3–4)
> Tujuan: Fitur monitoring dan laporan.

| # | Task | Effort | Deskripsi |
|---|---|---|---|
| 3.1 | DashboardController | Sedang | Aggregation: total jamaah, registrasi, pendapatan, kloter aktif |
| 3.2 | ReportController | Sedang | Laporan keuangan, jamaah per kloter, status registrasi |
| 3.3 | Role/Permission middleware | Sedang | CheckRole middleware + RoleController CRUD |
| 3.4 | UserManagementController | Sedang | CRUD internal users + assign role |

### Fase 4 — Fitur Advanced (Minggu 4–6)
> Tujuan: Real-time monitoring dan fitur lanjutan.

| # | Task | Effort | Deskripsi |
|---|---|---|---|
| 4.1 | TrackingController (Live Monitoring) | Besar | Store + query lokasi real-time |
| 4.2 | GeofenceController | Besar | CRUD zona + deteksi breach |
| 4.3 | AttendanceController | Sedang | Buat sesi + tandai kehadiran |
| 4.4 | ChatMessageController | Besar | Butuh WebSocket/Pusher untuk real-time |
| 4.5 | NotificationLogController | Sedang | Read-only + integrasi FCM push |

### Fase 5 — Fitur Baru dari Nol (Sesuai Kebutuhan)
> Tujuan: Fitur yang belum ada di skema DB.

| # | Task | Effort | Deskripsi |
|---|---|---|---|
| 5.1 | Mutawif (Pembimbing Ibadah) | Sedang | Desain tabel + full CRUD |
| 5.2 | Staff Stock (Perlengkapan) | Sedang | Desain tabel + full CRUD |
| 5.3 | System Settings | Kecil | Tabel key-value + controller |

---

## 5.1. Pembagian Tugas untuk 4 Developer

Sangat bisa! Skenario pengerjaan project backend ini dapat dibagi secara ideal dan seimbang kepada **4 orang Developer** berdasarkan ranah (domain/modul) berikut:

```
                  ┌─────────────────────────────────────────┐
                  │          PROJECT BACKEND DNA TOUR       │
                  └────────────────────┬────────────────────┘
                                       │
      ┌────────────────┬───────────────┴───────────────┬────────────────┐
      │                │                               │                │
┌─────▼──────┐  ┌──────▼───────┐             ┌─────────▼────────┐  ┌────▼────────┐
│ DEV 1      │  │ DEV 2        │             │ DEV 3            │  │ DEV 4      │
│ Master Data│  │ Pendaftaran, │             │ Kloter, Akomodasi│  │ Monitoring,│
│ & Security │  │ Jamaah &     │             │ & Operasional    │  │ Emergency &│
│            │  │ Keuangan     │             │ Lapangan         │  │ IoT        │
└────────────┘  └──────────────┘             └──────────────────┘  └────────────┘
```

---

### 👨‍💻 Developer 1: Core Master Data, User Security & System Setup
> **Tanggung Jawab:** Fondasi sistem, keamanan role/user, data induk fasilitas travel, dan konfigurasi global.

* **Modul & Fitur:**
  1. **Access Control & User Mgmt:** `RoleController`, `UserManagementController`, Middleware `CheckRole` / Spatie Permission.
  2. **Data Master Akomodasi & Transport:** `HotelController`, `BusController`.
  3. **Master Paket Detail:** Extension `PackageController` untuk `package_facilities`, `package_pricings`, `package_itineraries`, `package_quotas`.
  4. **Modul Baru:** `MutawifController`, `StaffStockController`, `SystemSettingController`.

---

### 👨‍💻 Developer 2: Pendaftaran, Data Jamaah & Keuangan
> **Tanggung Jawab:** Alur administrasi pelanggan (CRM), verifikasi berkas/dokumen, transaksi pembayaran, dan laporan finansial.

* **Modul & Fitur:**
  1. **Pendaftaran & Jamaah:** `RegistrationController` (maintenance), `JamaahController`, `DocumentChecklistController`.
  2. **Hubungan Keluarga:** `FamilyRelationController` (`family_relations`).
  3. **Keuangan & Pembayaran:** `RegistrationPaymentController`, konsolidasi tabel `payments` vs `registration_payments`.
  4. **Reporting Finansial:** Endpoint laporan pendaftaran, tunggakan, & penerimaan pembayaran (`ReportController` aspek keuangan).

---

### 👨‍💻 Developer 3: Operasional Kloter, Kamar & Pembimbing Lapangan
> **Tanggung Jawab:** Pengelompokan jamaah, tata kelola kamar hotel, penjadwalan itinerary kloter, dan penugasan Tour Leader.

* **Modul & Fitur:**
  1. **Manajemen Anggota Kloter:** Manajemen anggota kloter (`kloter_members` & `KloterController` refactoring).
  2. **Manajemen Tour Leader:** `TourLeaderController`, penugasan TL ke Kloter (`kloter_leader_assignments`).
  3. **Manajemen Kamar & Roommate:** `RoomController` (auto-assign & manual adjustment).
  4. **Jadwal & Itinerary Kloter:** `KloterScheduleController` (Journey / Jadwal aktivitas harian per kloter).
  5. **Dashboard Operasional:** Ringkasan status kloter, alokasi kamar, & kuota (`DashboardController`).

---

### 👨‍💻 Developer 4: Real-time Monitoring, Emergency, Notifikasi & Logs
> **Tanggung Jawab:** Fitur lapangan berteknologi tinggi (GPS tracking, geofencing, absensi, notifikasi darurat/SOS, & audit trail).

* **Modul & Fitur:**
  1. **Live Monitoring & Tracking:** `TrackingController` (`trackings` per bulan), `GeofenceController` (`geofence_zones`).
  2. **Absensi & Meeting Point:** `AttendanceController` (`attendance_sessions`, `attendances`, `meeting_points`).
  3. **Emergency & Warning:** `SosIncidentController` (`sos_incidents`, `sos_status_histories`, `early_warning_events`).
  4. **Komunikasi & Log:** `BroadcastController`, `ChatMessageController`, `NotificationLogController`, `AuditLogController` (read-only logs).

---

## 6. Konvensi & Aturan Kode

### 6.1 Penamaan

| Komponen | Konvensi | Contoh |
|---|---|---|
| **Tabel** | snake_case, plural | `kloter_members`, `tour_leaders` |
| **Model** | PascalCase, singular | `KloterMember`, `TourLeader` |
| **Controller** | PascalCase + Controller | `KloterController`, `TourLeaderController` |
| **Form Request** | Store/Update + Model + Request | `StoreKloterRequest`, `UpdateKloterRequest` |
| **Migration** | timestamp_create_table_name | `2025_01_01_000014_create_kloters_table.php` |
| **Route** | kebab-case, plural | `/api/tour-leaders`, `/api/kloter-schedules` |

### 6.2 Struktur Response API

Semua response mengikuti format konsisten:

```json
// Sukses (200/201)
{
    "message": "Deskripsi aksi berhasil.",
    "data": { ... }
}

// Error validasi (422)
{
    "message": "The given data was invalid.",
    "errors": {
        "field_name": ["Pesan error."]
    }
}

// Unauthorized (401)
{
    "message": "Unauthenticated. Token autentikasi (Bearer token) tidak disertakan atau telah kadaluarsa."
}
```

### 6.3 Aturan Model

- Semua model **wajib** menggunakan `HasUuids` trait
- Primary key: UUID dengan `gen_random_uuid()` (level DB)
- Selalu definisikan `$table`, `$fillable`, dan `$casts`
- Relasi harus eksplisit (tidak mengandalkan konvensi Laravel karena pakai UUID)

### 6.4 Aturan Controller

- Semua controller API ada di namespace `App\Http\Controllers\Api`
- Validasi wajib pakai Form Request (`StoreXxxRequest`, `UpdateXxxRequest`)
- Response selalu dalam format JSON
- Business logic kompleks → pindahkan ke Service class

### 6.5 Aturan Database

- Semua foreign key harus eksplisit (UUID, bukan auto-increment)
- Gunakan `restrictOnDelete()` untuk data penting (jamaah, internal_users)
- Gunakan `cascadeOnDelete()` untuk data turunan (kloter_members, room_members)
- Gunakan `nullOnDelete()` untuk referensi opsional (created_by, checked_by)

---

## 7. Referensi Endpoint API

### Auth
| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/login` | ❌ | Login admin |
| POST | `/api/logout` | ✅ | Logout (revoke token) |
| GET | `/api/me` | ✅ | Profil user login |

### Packages (Paket Umrah)
| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/packages` | ✅ | List semua paket |
| POST | `/api/packages` | ✅ | Buat paket baru |
| GET | `/api/packages/{id}` | ✅ | Detail paket |
| PUT | `/api/packages/{id}` | ✅ | Update paket |
| DELETE | `/api/packages/{id}` | ✅ | Hapus paket |

### Kloters (Groups / Keberangkatan)
| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/kloters` | ✅ | List kloter (filter: q, status, package_id) |
| POST | `/api/kloters` | ✅ | Buat kloter baru |
| GET | `/api/kloters/{id}` | ✅ | Detail kloter + registrations |
| PUT | `/api/kloters/{id}` | ✅ | Update kloter (kecuali status=completed) |
| DELETE | `/api/kloters/{id}` | ✅ | Hapus kloter (jika belum ada anggota) |

### Rooms (Pembagian Kamar)
| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/kloters/{id}/rooms` | ✅ | List kamar per kloter |
| POST | `/api/kloters/{id}/auto-assign-rooms` | ✅ | Auto-assign kamar per kloter |
| POST | `/api/rooms` | ✅ | Buat kamar manual |
| PUT | `/api/rooms/{id}` | ✅ | Update kamar |
| DELETE | `/api/rooms/{id}` | ✅ | Hapus kamar |
| POST | `/api/rooms/{id}/members` | ✅ | Tambah anggota ke kamar |
| DELETE | `/api/rooms/{id}/members/{reg_id}` | ✅ | Keluarkan anggota dari kamar |

### Registrations (Pendaftaran)
| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/registrations` | ✅ | List semua pendaftaran |
| POST | `/api/registrations` | ✅ | Buat pendaftaran baru |
| GET | `/api/registrations/{id}` | ✅ | Detail pendaftaran |
| PUT | `/api/registrations/{id}` | ✅ | Update data pendaftaran |
| DELETE | `/api/registrations/{id}` | ✅ | Hapus pendaftaran |
| POST | `/api/registrations/{id}/cancel` | ✅ | Batalkan pendaftaran |
| POST | `/api/registrations/{id}/convert-to-jamaah` | ✅ | Konversi ke Jamaah resmi |

### Payments (Keuangan)
| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/payments` | ✅ | Semua transaksi pembayaran |
| GET | `/api/registrations/{id}/payments` | ✅ | Pembayaran per pendaftaran |
| POST | `/api/registrations/{id}/payments` | ✅ | Tambah pembayaran |

### Jamaah (Pilgrims)
| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/jamaah` | ✅ | List semua jamaah |
| POST | `/api/jamaah` | ✅ | Tambah jamaah manual |
| GET | `/api/jamaah/{id}` | ✅ | Detail jamaah |
| PUT | `/api/jamaah/{id}` | ✅ | Update jamaah |
| DELETE | `/api/jamaah/{id}` | ✅ | Hapus jamaah |

---

## Catatan Penutup

- Dokumen ini harus di-**update setiap kali** ada modul baru yang selesai dikerjakan
- Sebelum mengerjakan modul baru, **cek kolom tabel di bagian 3** untuk memastikan tidak ada yang terlewat
- Setiap modul baru wajib mengikuti **konvensi di bagian 6**
- Masalah di **bagian 4** harus diselesaikan sebelum melanjutkan ke Fase 2
