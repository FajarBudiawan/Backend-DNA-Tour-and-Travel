# Rencana Kerja: Modul Tour Leader, Muthawwif & Monitoring Stok

> Disusun berdasarkan hasil audit backend & frontend per 31 Agustus 2026.
> Ketiga modul ini berada di bawah section **"Staf"** pada sidebar Web Admin.

---

## Ringkasan Status

| Modul | Frontend | Backend | Kesiapan |
|---|---|---|---|
| **Tour Leader** | 100% mock Zustand, service layer belum ada | Migration ada (`tour_leaders`, `kloter_leader_assignments`), Model/Controller/Route **belum ada** | ⚠️ Skema DB siap, logic API belum dibuat |
| **Muthawwif** | 100% mock Zustand, service layer belum ada | Tidak ada sama sekali (tidak ada migration/tabel) | ❌ Perlu dibangun dari nol total |
| **Monitoring Stok** | 100% mock Zustand, service layer belum ada | Tidak ada modul stok/inventory. Yang ada hanya `RegistrationEquipment` (konsep berbeda — hanya mencatat status terima perlengkapan per jamaah, bukan stok gudang) | ❌ Perlu dibangun dari nol total |

---

## 1. Modul Tour Leader

### 1.1 Struktur Backend yang Sudah Ada (Migration)

**Tabel `tour_leaders`:**
| Kolom | Tipe |
|---|---|
| `id` | UUID |
| `login_id` | string |
| `full_name` | string |
| `certification_number` | string |
| `phone` | string |
| `status` | enum |

**Tabel `kloter_leader_assignments`** (pivot):
| Kolom | Tipe |
|---|---|
| `id` | UUID |
| `kloter_id` | UUID (FK) |
| `tour_leader_id` | UUID (FK) |
| `assigned_at` | timestamp |

> Terdapat constraint PostgreSQL `EXCLUDE USING gist (tour_leader_id WITH =, kloter_id WITH <>)` — mencegah 1 Tour Leader ditugaskan ke 2 kloter berbeda secara bersamaan (overlapping).

### 1.2 Field Frontend (`TourLeaders.tsx`) yang Perlu Dipetakan

| Field Frontend | Field Backend Tersedia? | Catatan |
|---|---|---|
| `id` | ✅ `id` / `login_id` | |
| `name` | ✅ `full_name` | |
| `phone` | ✅ `phone` | |
| `status` (Active/Resting/Standby) | ⚠️ `status` ada, tapi enum perlu disamakan | Backend perlu didefinisikan: `active`, `resting`, `standby` (atau sesuai kesepakatan) |
| `group` (Kloter Penugasan) | ✅ via tabel `kloter_leader_assignments` | Perlu endpoint assign/unassign |
| `experience` | ❌ Tidak ada di backend | Perlu keputusan: tambah kolom baru, atau field ini dihapus dari frontend |
| `performance` | ❌ Tidak ada di backend | Perlu keputusan: tambah kolom baru, atau field ini dihapus dari frontend |
| `certification_number` | ✅ Ada di backend, tidak dipakai frontend | Backend punya field ini, frontend belum menampilkannya |

### 1.3 Pekerjaan yang Perlu Dilakukan

**Backend:**
- [ ] Buat Model `TourLeader.php` + relasi ke `Kloter` via pivot `kloter_leader_assignments`
- [ ] Buat `StoreTourLeaderRequest.php` & `UpdateTourLeaderRequest.php`
- [ ] Buat `TourLeaderController.php` (index, store, show, update, destroy)
- [ ] Buat endpoint assignment: `POST /api/kloters/{kloter}/assign-leader`, `DELETE /api/kloters/{kloter}/unassign-leader`
- [ ] Putuskan field `experience` & `performance` — tambah kolom baru atau drop dari frontend
- [ ] Putuskan mapping enum `status` yang final
- [ ] Daftarkan route di `api.php`
- [ ] Testing via Postman (termasuk test constraint anti-overlap)

**Frontend:**
- [ ] Buat `tourLeaderService.ts`
- [ ] Integrasi List, Form Create/Edit, Detail, Delete
- [ ] Integrasi assignment ke Kloter (dropdown/pilih Kloter di form)
- [ ] Sembunyikan/sesuaikan field yang keputusannya belum final (`experience`, `performance`)

---

## 2. Modul Muthawwif

### 2.1 Status Backend
**Tidak ada sama sekali** — tidak ada migration, tabel, model, atau endpoint apapun.

### 2.2 Field Frontend (`Mutawifs.tsx`) sebagai Acuan Desain Tabel Baru

| Field Frontend | Tipe yang Disarankan |
|---|---|
| `id` | UUID (auto) |
| `name` | string, required |
| `language` (Penguasaan Bahasa) | string atau JSON array |
| `group` (Kloter Penugasan) | relasi ke `kloters` (perlu tabel pivot baru, mirip `kloter_leader_assignments`) |
| `experience` | string/text |
| `status` (Active/Standby) | enum |

### 2.3 Pekerjaan yang Perlu Dilakukan

**Backend (dari nol):**
- [ ] Desain & buat migration tabel `muthawwifs`
- [ ] Desain & buat migration tabel pivot `kloter_muthawwif_assignments` (pola serupa `kloter_leader_assignments`, termasuk pertimbangan constraint anti-overlap)
- [ ] Buat Model `Muthawwif.php`
- [ ] Buat `StoreMuthawwifRequest.php` & `UpdateMuthawwifRequest.php`
- [ ] Buat `MuthawwifController.php` (index, store, show, update, destroy)
- [ ] Buat endpoint assignment ke Kloter
- [ ] Daftarkan route
- [ ] Testing via Postman

**Frontend:**
- [ ] Buat `muthawwifService.ts`
- [ ] Integrasi List, Form Create/Edit, Detail, Delete
- [ ] Integrasi assignment ke Kloter

---

## 3. Modul Monitoring Stok

### 3.1 Status Backend
**Tidak ada** modul stok/inventory yang sesuai. Yang tersedia hanya `RegistrationEquipment` — konsepnya berbeda (mencatat status terima perlengkapan per jamaah individual, bukan stok gudang agregat).

### 3.2 Field Frontend (`StaffStock.tsx`) sebagai Acuan Desain Tabel Baru

| Field Frontend | Tipe yang Disarankan |
|---|---|
| `id` | UUID (auto) |
| `name` (Nama Barang) | string, required |
| `category` | string atau enum |
| `quantity` (Stok Saat Ini) | integer |
| `minStock` | integer |
| `unit` (Satuan) | string |
| `location` (Gudang Lokasi) | string |
| `notes` | text, nullable |
| `lastUpdated` | timestamp (auto) |
| Status Stok (Aman/Menipis/Habis) | dihitung otomatis dari `quantity` vs `minStock`, tidak perlu kolom terpisah |

### 3.3 Pekerjaan yang Perlu Dilakukan

**Backend (dari nol):**
- [ ] Desain & buat migration tabel `stock_items` (atau nama serupa)
- [ ] Buat Model `StockItem.php`
- [ ] Buat `StoreStockItemRequest.php` & `UpdateStockItemRequest.php`
- [ ] Buat `StockItemController.php` (index, store, show, update, destroy)
- [ ] Buat endpoint khusus untuk adjust quantity cepat (`PATCH /api/stock-items/{id}/adjust`) — mendukung tombol `+`/`-` di UI
- [ ] Daftarkan route
- [ ] Testing via Postman

**Frontend:**
- [ ] Buat `stockService.ts`
- [ ] Integrasi List (termasuk tombol adjust cepat `+`/`-`), Form Create/Edit, Detail, Delete
- [ ] Hitung status stok (Aman/Menipis/Habis) di frontend dari `quantity` vs `minStock`

---

## Urutan Pengerjaan yang Disarankan

1. **Tour Leader dulu** — paling ringan karena skema database sudah ada, tinggal bangun Model/Controller/Route di atasnya
2. **Muthawwif** — pola pengerjaannya mirip Tour Leader (bisa dicontek), tapi perlu desain tabel dari nol dulu
3. **Monitoring Stok** — paling independen (tidak terkait Kloter), bisa dikerjakan kapan saja, cocok dikerjakan terakhir atau paralel

## Keputusan yang Perlu Diambil Sebelum Mulai

- [ ] Field `experience` & `performance` di Tour Leader — tambah kolom baru di backend, atau dihapus dari frontend?
- [ ] Field `language` di Muthawwif — disimpan sebagai string tunggal atau array (multi-bahasa)?
- [ ] Apakah assignment Tour Leader/Muthawwif ke Kloter perlu constraint anti-overlap seperti yang sudah dirancang untuk Tour Leader?
- [ ] Nama tabel & konvensi penamaan untuk modul Stok (`stock_items`, `inventory_items`, atau nama lain sesuai preferensi tim)
