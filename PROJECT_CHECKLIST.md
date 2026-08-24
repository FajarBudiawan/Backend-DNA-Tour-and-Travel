# 📋 Checklist Fitur & Progress Backend — DNA Tour & Travel

Dokumen ini digunakan untuk melacak status pengerjaan fitur backend, integrasi, dan infrastruktur proyek **DNA Tour & Travel**.

*Terakhir Diperbarui:* 24 Agustus 2026  
*Status DB Schema:* **100% Migrated (36 Tabel)**  
*Status API Controller:* **7 / 22 Modul Selesai (32%)**

---

## 🛠️ 1. Infrastruktur, Keamanan & Repositori

- [x] **Database PostgreSQL Setup** (42 file migration / 36 tabel berhasil di-migrate)
- [x] **Session Table Migration** (Driver session database aktif tanpa error)
- [x] **Git Repository Remote** (`https://github.com/FajarBudiawan/Backend-DNA-Tour-and-Travel.git`)
- [x] **Dokumen Pedoman Pengembangan** (`DEVELOPMENT_GUIDE.md` dengan skema DB & alokasi 4 developer)
- [ ] **Middleware Security Role/Permission** (Pemasangan middleware `CheckRole` pada `bootstrap/app.php`)
- [ ] **Penyelarasan Skema Keuangan** (Konsolidasi tabel `payments` vs `registration_payments`)
- [ ] **Skema Pivot Kloter** (Penentuan penggunaan `kloter_members` vs `registrations.kloter_id`)

---

## ✅ 2. Modul API Selesai (Siap Diintegrasikan ke Frontend)

> Modul yang sudah memiliki **Migration + Model + Form Request + Controller + Route** lengkap (34 endpoint).

### 🔑 2.1 Autentikasi (`AuthController`)
- [x] `POST /api/login` — Login internal user & generate Sanctum token
- [x] `POST /api/logout` — Revoke token & logout
- [x] `GET /api/me` — Ambil data profil user yang sedang login

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

---

## ⚠️ 3. Modul dengan DB Siap (Belum Ada Controller & Route)

> Skema tabel di PostgreSQL **sudah ada**, tinggal membuat **Model + Controller + Route**.

### 👨‍💼 3.1 Master Data & User Management (Developer 1)
- [ ] **Master Hotel** (`hotels`) — Model `Hotel` ada, butuh `HotelController`
- [ ] **Master Bus** (`buses`) — Butuh Model `Bus` & `BusController`
- [ ] **Sub-Resource Paket Detail** — Facilities (`package_facilities`), Pricings (`package_pricings`), Itineraries (`package_itineraries`), Quotas (`package_quotas`)
- [ ] **Role Management** (`roles`) — CRUD Role system
- [ ] **User Management** (`internal_users`) — CRUD User Internal admin/operator

### 📋 3.2 Administrasi Jamaah & Dokumen (Developer 2)
- [ ] **Hubungan Keluarga / Families** (`family_relations`) — CRUD relasi keluarga per jamaah
- [ ] **Checklist Dokumen Jamaah** (`document_checklists`) — Verifikasi KTP, Paspor, KK, Buku Nikah, Vaksin
- [ ] **Laporan Keuangan & Registrasi** (`ReportController`) — Export & statistik laporan keuangan

### 🚩 3.3 Operasional Lapangan & Tour Leader (Developer 3)
- [ ] **Tour Leaders** (`tour_leaders`) — CRUD Data pembimbing/ketua rombongan
- [ ] **Penugasan Tour Leader** (`kloter_leader_assignments`) — Assign TL ke kloter keberangkatan
- [ ] **Anggota Kloter Pivot** (`kloter_members`) — Endpoint manajemen anggota kloter resmi
- [ ] **Jadwal Perjalanan / Journey** (`kloter_schedules`) — Schedule/Itinerary harian per kloter
- [ ] **Dashboard Analytics** (`DashboardController`) — Ringkasan statistik jamaah, kloter, & kamar

### 📡 3.4 Monitoring, IoT & Emergency (Developer 4)
- [ ] **Pesan Massal / Broadcast** (`broadcasts`, `broadcast_recipient_kloters`, `broadcast_logs`)
- [ ] **Kejadian Darurat / Emergency SOS** (`sos_incidents`, `sos_status_histories`, `early_warning_events`)
- [ ] **Pelacakan Lokasi Real-time** (`trackings`, `geofence_zones`) — Tracking GPS & batas area
- [ ] **Absensi & Meeting Point** (`attendance_sessions`, `attendances`, `meeting_points`) — Sesi absensi jamaah
- [ ] **Chat & Pesan** (`chat_messages`) — Chat grup/privat kloter
- [ ] **Log Notifikasi** (`notification_logs`) — Log pengiriman push notification
- [ ] **Audit Trail / Device Logs** (`audit_logs`) — Read-only API log aktivitas (DB Trigger Protected)

---

## ❌ 4. Modul Baru (Perlu Desain Skema DB Baru)

> Modul yang belum memiliki tabel di database.

- [ ] **Mutawifs (Pembimbing Ibadah)** — Migration + Model + Controller
- [ ] **Staff Stock (Stok Perlengkapan Staff)** — Migration + Model + Controller
- [ ] **System Settings (Pengaturan Global)** — Migration + Model + Controller

---

## 🔄 5. Progress Pembagian Tugas Tim 4 Developer

| Developer | Domain Tanggung Jawab | Modul Selesai | Total Modul Target | Progress |
|---|---|---|---|---|
| **Dev 1** | Master Data, User Security & System Setup | 2 (Package, Auth*) | 7 Modul | 28% |
| **Dev 2** | Pendaftaran, Jamaah & Keuangan | 3 (Reg, Payment, Jamaah) | 5 Modul | 60% |
| **Dev 3** | Operasional Kloter, Kamar & Tour Leader | 2 (Kloter, Room) | 6 Modul | 33% |
| **Dev 4** | Real-time Monitoring, SOS & Logs | 0 | 7 Modul | 0% |
