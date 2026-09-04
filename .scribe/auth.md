# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {token}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Setiap request membutuhkan header `Authorization: Bearer {token}` yang didapatkan setelah berhasil login melalui endpoint `POST /api/login`.
