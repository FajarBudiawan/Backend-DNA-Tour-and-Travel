# 📋 Checklist Fitur & Progress Backend — DNA Tour & Travel

Dokumen ini digunakan untuk melacak status pengerjaan fitur backend, integrasi, dan infrastruktur proyek **DNA Tour & Travel**.

*Terakhir Diperbarui:* 30 Agustus 2026
*Status DB Schema:* **100% Migrated (36 Tabel)**
*Status API Controller:* **9 / 22 Modul Selesai (41%)**

---

## 🛠️ 1. Infrastruktur, Keamanan & Repositori

- [x] **Database PostgreSQL Setup** (42 file migration / 36 tabel berhasil di-migrate)
- [x] **Session Table Migration** (Driver session database aktif tanpa error)
- [x] **Git Repository Remote** (`https://github.com/FajarBudiawan/Backend-DNA-Tour-and-Travel.git`)
- [x] **Dokumen Pedoman Pengembangan** (`DEVELOPMENT_GUIDE.md` dengan skema DB & alokasi 4 developer)
- [x] **Auth Guard Fix** — `config/auth.php` dikonfigurasi custom guard `internal_users` dengan model `InternalUser`
- [x] **RouteNotFoundException Fix** — Middleware `Authenticate` dikonfigurasi agar API mengembalikan `401` bukan redirect ke `login`
- [ ] **Middleware Security Role/Permission** (Pemasangan middleware `CheckRole` pada `bootstrap/app.php`)
- [ ] **Penyelarasan Skema Keuangan** (Konsolidasi tabel `payments` vs `registration_payments`)
- [ ] **Skema Pivot Kloter** (Penentuan penggunaan `kloter_members` vs `registrations.kloter_id`)

---

## ✅ 2. Modul API Selesai (Siap Diintegrasikan ke Frontend)

> Modul yang sudah memiliki **Migration + Model + Form Request + Controller + Route** lengkap.

### 🔑 2.1 Autentikasi (`AuthController`)
- [x] `POST /api/login` — Login internal user & generate Sanctum token
- [x] `POST /api/logout` — Revoke token & logout
- [x] `GET /api/me` — Ambil data profil user yang sedang login
- [x] **Bug Fix:** `RouteNotFoundException [login]` — Guard API dikonfigurasi agar return `401 JSON` bukan redirect

### 📦 2.2 Master Paket Umrah (`PackageController`)
- [x] `GET /api/packages` — List semua paket umrah
- [x] `POST /api/packages` — Buat paket umrah baru
- [x] `GET /api/packages/{id}` — Detail paket umrah
- [x] `PUT /api/packages/{id}` — Update paket umrah
- [x] `DELETE /api/packages/{id}` — Hapus paket umrah

### 👥 2.3 Kloter Keberangkatan / Groups (`KloterController`)
- [x] `GET /api/kloters` — List kloter (dengan search `q`, filter `status` & `package_id`)
- [x] `POST /api/kloters` — Buat kloter baru
- [x] `GET /api/kloters/{id}` — Detail kloter + data pendaftaran
- [x] `PUT /api/kloters/{id}` — Update kloter (terkunci jika status `completed`)
- [x] `DELETE /api/kloters/{id}` — Hapus kloter (jika belum ada anggota)

### 📝 2.4 Pendaftaran Jamaah (`RegistrationController`)
- [x] `GET /api/registrations` — List pendaftaran (pencarian & filter)
- [x] `POST /api/registrations` — Pendaftaran jamaah baru
- [x] `GET /api/registrations/{id}` — Detail pendaftaran
- [x] `PUT /api/registrations/{id}` — Update data pendaftaran
- [x] `DELETE /api/registrations/{id}` — Hapus pendaftaran
- [x] `POST /api/registrations/{id}/cancel` — Pembatalan pendaftaran
- [x] `POST /api/registrations/{id}/convert-to-jamaah` — Konversi pendaftaran menjadi Jamaah resmi

### 💳 2.5 Keuangan & Pembayaran (`RegistrationPaymentController`)
- [x] `GET /api/payments` — Laporan seluruh transaksi pembayaran
- [x] `GET /api/registrations/{id}/payments` — Riwayat pembayaran per pendaftaran
- [x] `POST /api/registrations/{id}/payments` — Tambah pembayaran (Otomatis update status `unpaid` -> `dp_paid` -> `fully_paid`)

### 🕋 2.6 Manajemen Jamaah / Pilgrims (`JamaahController`)
- [x] `GET /api/jamaah` — List jamaah resmi
- [x] `POST /api/jamaah` — Tambah jamaah manual
- [x] `GET /api/jamaah/{id}` — Detail jamaah
- [x] `PUT /api/jamaah/{id}` — Update data jamaah
- [x] `DELETE /api/jamaah/{id}` — Hapus data jamaah

### 🏨 2.7 Pembagian Kamar & Roommate (`RoomController`)
- [x] `GET /api/kloters/{id}/rooms` — List kamar & jamaah belum dapat kamar per kloter
- [x] `POST /api/kloters/{id}/auto-assign-rooms` — Pembagian kamar otomatis per kloter
- [x] `POST /api/rooms` — Buat kamar manual
- [x] `PUT /api/rooms/{id}` — Update kamar
- [x] `DELETE /api/rooms/{id}` — Hapus kamar
- [x] `POST /api/rooms/{id}/members` — Tambah jamaah ke kamar
- [x] `DELETE /api/rooms/{id}/members/{reg_id}` — Keluarkan jamaah dari kamar

### 💸 2.8 Pengeluaran Kas (`ExpenseController`) ⭐ *BARU — Dikerjakan Terakhir*
- [x] **Migration** `create_expenses_table` — Tabel `expenses` dengan field `vendor`, `category`, `amount`, `payment_method`, `expense_date`, `reference_number`, `notes`, `recorded_by`
- [x] **Model** `Expense.php` — Relationship ke `InternalUser` via `recorded_by`
- [x] **Form Request** `StoreExpenseRequest.php` — Validasi create
- [x] **Form Request** `UpdateExpenseRequest.php` — Validasi update (sometime optional)
- [x] `GET /api/expenses` — List & filter pengeluaran (search `q`, filter `category`, `payment_method`, `date_from`, `date_to`) + ringkasan per kategori
- [x] `POST /api/expenses` — Catat pengeluaran baru (auto-generate `reference_number` jika kosong: format `TRX-YYYY-XXX`)
- [x] `GET /api/expenses/{id}` — Detail pengeluaran
- [x] `PUT /api/expenses/{id}` — Update data pengeluaran
- [x] `DELETE /api/expenses/{id}` — Hapus catatan pengeluaran
- [x] **Dokumentasi API** `EXPENSES_AND_FINANCE_API.md` — Payload request/response lengkap

### 📊 2.9 Ringkasan Keuangan (`FinanceSummaryController`) ⭐ *BARU — Dikerjakan Terakhir*
- [x] `GET /api/finance/summary` — Ringkasan global keuangan:
  - `total_pemasukan` (SUM dari `registration_payments`)
  - `total_pengeluaran` (SUM dari `expenses`)
  - `total_piutang` (SUM `remaining_cost` pendaftaran aktif)
  - `saldo_bersih` (pemasukan - pengeluaran)

---

## 📋 3. Sesi Terakhir — Ringkasan Pekerjaan (26–27 Agustus 2026)

> Berikut adalah rekap seluruh pekerjaan yang dilakukan dalam sesi-sesi terakhir.

### Sesi: Implementasi Modul Expenses & Finance Summary
- [x] Membuat migration `2026_08_26_000000_create_expenses_table.php`
- [x] Membuat model `Expense.php` dengan `$fillable` & relasi `recordedBy()`
- [x] Membuat `StoreExpenseRequest.php` & `UpdateExpenseRequest.php`
- [x] Membuat `ExpenseController.php` (full CRUD + auto reference_number)
- [x] Membuat endpoint `GET /api/finance/summary` di controller terpisah / method tambahan
- [x] Mendaftarkan route di `routes/api.php`
- [x] Membuat dokumentasi `EXPENSES_AND_FINANCE_API.md`

### Sesi: Integrasi Frontend Finance Module (Sisi Frontend)
- [x] Migrasi tab "Catatan Pengeluaran" dari Zustand mock state ke API real (`expenseService.ts`)
- [x] Implementasi search & filter pengeluaran di frontend
- [x] Implementasi delete confirmation flow yang memanggil `deleteExpense` backend
- [x] Fix type mismatch `ReceivableRegistrationItem` di `Finance.tsx`
- [x] Cleanup dead code: hapus variabel legacy `filteredTransactions`, `allReceivables`
- [x] Verifikasi `npx tsc --noEmit` berhasil tanpa error

### Sesi: Audit & Review Modul Jamaah
- [x] Audit `JamaahController` — verifikasi endpoint CRUD sudah lengkap
- [x] Verifikasi lifecycle: `Registration` → `convert-to-jamaah` → `Jamaah`
- [x] Dokumentasi field mapping jamaah untuk integrasi frontend (`jamaahService.ts`)

### Sesi: Fix Auth RouteNotFoundException
- [x] Identifikasi akar masalah: middleware `Authenticate` mencoba redirect ke route `login` (web route) saat API request tidak terautentikasi
- [x] Fix di `config/auth.php` — guard `api` / `sanctum` dikonfigurasi benar
- [x] Verifikasi `GET /api/me` mengembalikan `401 JSON` bukan redirect error

### Sesi: Analisis & Dokumentasi Logic Project
- [x] Analisis komprehensif modul Autentikasi, Registrasi, dan Keuangan
- [x] Dokumentasi alur data & relasi DB untuk keperluan presentasi ke mentor

---

## ⚠️ 4. Modul dengan DB Siap (Belum Ada Controller & Route)

> Skema tabel di PostgreSQL **sudah ada**, tinggal membuat **Model + Controller + Route**.

### 👨‍💼 4.1 Master Data & User Management (Developer 1)
- [ ] **Master Hotel** (`hotels`) — Model `Hotel` ada, butuh `HotelController`
- [ ] **Master Bus** (`buses`) — Butuh Model `Bus` & `BusController`
- [ ] **Sub-Resource Paket Detail** — Facilities, Pricings, Itineraries, Quotas
- [ ] **Role Management** (`roles`) — CRUD Role system
- [ ] **User Management** (`internal_users`) — CRUD User Internal admin/operator

### 📋 4.2 Administrasi Jamaah & Dokumen (Developer 2)
- [ ] **Hubungan Keluarga / Families** (`family_relations`) — CRUD relasi keluarga per jamaah
- [ ] **Checklist Dokumen Jamaah** (`document_checklists`) — Verifikasi KTP, Paspor, KK, Buku Nikah, Vaksin
- [ ] **Laporan Keuangan & Registrasi** (`ReportController`) — Export & statistik laporan keuangan

### 🚩 4.3 Operasional Lapangan & Tour Leader (Developer 3)
- [ ] **Tour Leaders** (`tour_leaders`) — CRUD Data pembimbing/ketua rombongan
- [ ] **Penugasan Tour Leader** (`kloter_leader_assignments`) — Assign TL ke kloter keberangkatan
- [ ] **Anggota Kloter Pivot** (`kloter_members`) — Endpoint manajemen anggota kloter resmi
- [ ] **Jadwal Perjalanan / Journey** (`kloter_schedules`) — Schedule/Itinerary harian per kloter
- [ ] **Dashboard Analytics** (`DashboardController`) — Ringkasan statistik jamaah, kloter, & kamar

### 📡 4.4 Monitoring, IoT & Emergency (Developer 4)
- [ ] **Pesan Massal / Broadcast** (`broadcasts`, `broadcast_recipient_kloters`, `broadcast_logs`)
- [ ] **Kejadian Darurat / Emergency SOS** (`sos_incidents`, `sos_status_histories`, `early_warning_events`)
- [ ] **Pelacakan Lokasi Real-time** (`trackings`, `geofence_zones`) — Tracking GPS & batas area
- [ ] **Absensi & Meeting Point** (`attendance_sessions`, `attendances`, `meeting_points`) — Sesi absensi jamaah
- [ ] **Chat & Pesan** (`chat_messages`) — Chat grup/privat kloter
- [ ] **Log Notifikasi** (`notification_logs`) — Log pengiriman push notification
- [ ] **Audit Trail / Device Logs** (`audit_logs`) — Read-only API log aktivitas (DB Trigger Protected)

---

## ❌ 5. Modul Baru (Perlu Desain Skema DB Baru)

> Modul yang belum memiliki tabel di database.

- [ ] **Mutawifs (Pembimbing Ibadah)** — Migration + Model + Controller
- [ ] **Staff Stock (Stok Perlengkapan Staff)** — Migration + Model + Controller
- [ ] **System Settings (Pengaturan Global)** — Migration + Model + Controller

---

## 🔄 6. Progress Pembagian Tugas Tim 4 Developer

| Developer | Domain Tanggung Jawab | Modul Selesai | Total Modul Target | Progress |
|---|---|---|---|---|
| **Dev 1** | Master Data, User Security & System Setup | 2 (Package, Auth*) | 7 Modul | 28% |
| **Dev 2** | Pendaftaran, Jamaah & Keuangan | 5 (Reg, Payment, Jamaah, **Expense**, **Finance Summary**) | 5 Modul | **100%** ✅ |
| **Dev 3** | Operasional Kloter, Kamar & Tour Leader | 2 (Kloter, Room) | 6 Modul | 33% |
| **Dev 4** | Real-time Monitoring, SOS & Logs | 0 | 7 Modul | 0% |
