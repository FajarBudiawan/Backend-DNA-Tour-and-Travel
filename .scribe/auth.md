# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {token}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

## Admin / Staff Authentication

Token diperoleh melalui `POST /api/login` menggunakan `email` dan `password`. Token mengandung Sanctum ability **`admin`**.

```http
Authorization: Bearer {token}
```

## Mobile Authentication (Sanctum Ability-Based)

Sistem Mobile App menggunakan **Laravel Sanctum** dengan ability token yang berbeda per role. Token diperoleh dari endpoint login masing-masing role menggunakan `login_id` sebagai credential — **tanpa password**.

| Role | Login Endpoint | Ability Token |
|---|---|---|
| Jamaah | `POST /api/jamaah/login` | `jamaah` |
| Family | `POST /api/family/login` | `family` |
| Tour Leader | `POST /api/tour-leader/login` | `tour_leader` |

Setelah login berhasil, gunakan token yang diterima pada header:

```http
Authorization: Bearer {token}
```

### Authorization Behavior

Setiap token hanya berlaku untuk endpoint yang sesuai dengan ability-nya. Cross-role access akan ditolak dengan HTTP `403 Forbidden`:

- **Tour Leader** → `GET /api/tour-leader/me` = `200 OK`
- **Tour Leader** → `GET /api/jamaah/me` = `403 Forbidden`
- **Tour Leader** → `GET /api/me` (Admin) = `403 Forbidden`
- **Jamaah** → `GET /api/tour-leader/me` = `403 Forbidden`
- **Family** → `GET /api/tour-leader/me` = `403 Forbidden`
