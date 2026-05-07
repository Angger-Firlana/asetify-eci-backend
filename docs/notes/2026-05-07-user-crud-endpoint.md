# 2026-05-07 - User CRUD Endpoint (Admin)

Backend menambah endpoint CRUD user untuk kebutuhan admin mengelola akun scanner/supervisor/admin.

## Endpoint

- `GET /api/v1/users`
- `GET /api/v1/users/{userId}`
- `POST /api/v1/users`
- `PUT /api/v1/users/{userId}`
- `DELETE /api/v1/users/{userId}`

Semua endpoint di atas membutuhkan token `admin` (permission `users.manage`).

