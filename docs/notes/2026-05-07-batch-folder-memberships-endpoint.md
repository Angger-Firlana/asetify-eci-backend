# 2026-05-07 - Batch Folder Membership Endpoint

Backend menambah endpoint batch untuk mengecek folder membership banyak asset sekaligus, terutama untuk kebutuhan halaman list agar tidak terjadi N+1 request.

## Endpoint

- `POST /api/v1/folders/memberships`

Body:

```json
{
  "asset_ids": [10, 11, 12],
  "type": "lokasi"
}
```

Catatan:

- `type` opsional (filter folder berdasarkan type).
- Maksimum `500` asset id per request.

Response:

```json
{
  "success": true,
  "message": "Folder memberships fetched",
  "data": {
    "memberships": {
      "10": [1, 4],
      "11": [],
      "12": [7]
    }
  }
}
```

