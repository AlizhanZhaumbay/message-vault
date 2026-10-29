# Message Vault API

Base URL: `http://localhost:8000/api`

- Requests and responses use JSON. The only exception is `POST /messages`, which is `multipart/form-data`.
- Send `Accept: application/json`. API routes always answer with JSON, but the header is good practice.
- IDs are UUIDs. A malformed ID returns `404`.
- Dates are ISO 8601. The API returns them in UTC, for example `2026-11-05T12:00:00.000000Z`.

## Headers

| Header | Used by | Meaning |
|---|---|---|
| `X-Owner-Token` | preview, update, delete | Token returned once by `POST /messages` |
| `X-Message-Password` | `GET /messages/{id}` | Password of a protected message |

## Error format

Every error has a human-readable `message`. Validation errors also include `errors`:

```json
{
  "message": "max_views is required when destroy_mode is after_views.",
  "errors": {
    "max_views": ["max_views is required when destroy_mode is after_views."]
  }
}
```

| Code | When |
|---|---|
| `403` | Wrong/missing `X-Owner-Token`, wrong/missing `X-Message-Password`, invalid/expired download signature |
| `404` | Unknown message/attachment, or the attachment was already purged |
| `409` | Update of a message that was already read |
| `410` | Message destroyed (response contains a tombstone) |
| `413` | Request larger than PHP `post_max_size` |
| `422` | Validation error |
| `423` | Message is sealed until `unlock_at` |

## Message object

Returned by create, read, preview and update.

```json
{
  "id": "9f1c2a7e-1b7a-4c1e-9d55-2f1f0f6c8a11",
  "title": "Burn after reading",
  "teaser": "Only for you",
  "body": "The treasure is under the oak.",
  "status": "available",
  "has_password": false,
  "unlock_at": null,
  "destroy_mode": "on_read",
  "max_views": null,
  "destroy_at": null,
  "views_count": 0,
  "remaining_views": 1,
  "first_viewed_at": null,
  "destroyed_at": null,
  "destroyed_reason": null,
  "attachments": [
    {
      "id": "9f1c2a7f-0c0e-4a5b-8f0e-0b7f1d2c3e44",
      "original_name": "image.png",
      "mime_type": "image/png",
      "size": 1543,
      "download_url": "http://localhost:8000/api/attachments/9f1c2a7f-.../download?expires=1793240000&signature=4b1e...",
      "download_url_expires_at": "2026-10-29T12:05:00.000000Z"
    }
  ],
  "created_at": "2026-10-29T12:00:00.000000Z",
  "updated_at": "2026-10-29T12:00:00.000000Z"
}
```

- `status`: `sealed` | `available` | `destroyed`. It is computed and never stored.
- `remaining_views`: `null` for `at_date` and `never`, which have no view limit.
- `destroyed_reason`: `read_once` | `view_limit_reached` | `destroy_date_reached`.

---

## POST /messages

Creates a message.

**Body** (`multipart/form-data`):

| Field | Type | Rules |
|---|---|---|
| `title` | string | required, max 120 |
| `teaser` | string | optional, max 255; always visible |
| `body` | string | max 20000; required if no `attachments[]` |
| `password` | string | optional, 4–100 chars |
| `unlock_at` | datetime | optional, must be in the future |
| `destroy_mode` | enum | required: `on_read`, `after_views`, `at_date`, `never` |
| `max_views` | integer | required for `after_views` (1–1000), ignored otherwise |
| `destroy_at` | datetime | required for `at_date`, must be in the future and after `unlock_at`; ignored otherwise |
| `attachments[]` | file | optional, up to `VAULT_MAX_FILES` files, each ≤ `VAULT_MAX_FILE_SIZE_MB`, MIME type in `VAULT_ALLOWED_MIME_TYPES` |

**201 Created**: the message object plus the owner token. The token is shown only in this response.

```json
{
  "id": "9f1c2a7e-1b7a-4c1e-9d55-2f1f0f6c8a11",
  "title": "Burn after reading",
  "...": "...",
  "owner_token": "q3S0b9mY1kQ7n2Zc8xV4tR6pL0wE5hJ2uA9dF1gK"
}
```

**Errors:** `422` (for example, `after_views` without `max_views`, `destroy_at` before `unlock_at`, a disallowed file type), `413`.

---

## GET /messages

Lists messages, newest first. The list **never** includes bodies or files.

**Query:**

| Param | Values |
|---|---|
| `status` | optional: `sealed`, `available`, `destroyed` |
| `page` | optional, ≥ 1 |

**200 OK**

```json
{
  "data": [
    {
      "id": "9f1c...",
      "title": "Time capsule",
      "teaser": "Come back in a week",
      "status": "sealed",
      "has_password": false,
      "unlock_at": "2026-11-05T12:00:00.000000Z",
      "seconds_until_unlock": 604734,
      "destroy_mode": "on_read",
      "remaining_views": 1,
      "attachments": { "count": 2, "types": ["image/png", "application/pdf"] },
      "created_at": "2026-10-29T12:00:00.000000Z"
    }
  ],
  "links": { "first": "...?page=1", "last": "...?page=1", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 15, "total": 6, "last_page": 1, "...": "..." }
}
```

**Errors:** `422` for an unknown `status`.

---

## GET /messages/{id}

Reads a message. This is the main endpoint: on success it **spends one view**.

**Headers:** `X-Message-Password` (only for protected messages).

The steps run inside one transaction, with the row locked (`SELECT … FOR UPDATE`):

1. If `destroy_mode=at_date` and `destroy_at` has passed, the message is destroyed now, without waiting for the scheduler.
2. destroyed → `410`
3. sealed → `423`
4. password missing or wrong → `403`
5. Otherwise a view is spent. If it was the last allowed view, the body is wiped from the DB, `destroyed_at` and `destroyed_reason` are set, and this response still carries the content.

**200 OK**: the message object, with `download_url` for each attachment (a signed URL valid for `VAULT_DOWNLOAD_LINK_TTL_MINUTES`). After the last view, `status` is already `destroyed` and `remaining_views` is `0`.

**423 Locked**

```json
{
  "message": "This message is sealed until its unlock date.",
  "unlock_at": "2026-11-05T12:00:00.000000Z",
  "seconds_remaining": 604734
}
```

**403 Forbidden**

```json
{ "message": "This message is password protected: the X-Message-Password header is missing or wrong." }
```

**410 Gone**

```json
{
  "message": "This message has been destroyed.",
  "tombstone": {
    "id": "9f1c...",
    "title": "Burn after reading",
    "created_at": "2026-10-29T12:00:00.000000Z",
    "first_viewed_at": "2026-10-29T12:01:10.000000Z",
    "destroyed_at": "2026-10-29T12:01:10.000000Z",
    "destroyed_reason": "read_once"
  }
}
```

**404 Not Found**: `{ "message": "Resource not found." }`

---

## GET /messages/{id}/preview

The author's view. It ignores `unlock_at` and does not spend views. It also works for sealed and destroyed messages; for a destroyed one, `body` is `null`.

**Headers:** `X-Owner-Token` (required).

**200 OK**: the message object.

**Errors:** `403` `{ "message": "Missing or invalid X-Owner-Token header." }`, `404`.

---

## PUT/PATCH /messages/{id}

Updates a message. Both methods do a **partial** update: only the fields you send change, and the rules are re-validated against the stored values. This is allowed only while the message has never been read (`first_viewed_at` is `null`).

**Headers:** `X-Owner-Token` (required), `Content-Type: application/json`.

**Body:** any of `title`, `teaser`, `body`, `password` (`null` removes it), `unlock_at`, `destroy_mode`, `max_views`, `destroy_at`. The rules are the same as for create. Values that do not apply to the final `destroy_mode` are cleared, for example `max_views` for `never`.

Attachments cannot be changed here. PHP parses `multipart/form-data` only for `POST`, so a `PUT`/`PATCH` request with files would arrive with an empty body.

```json
{ "title": "New title", "destroy_mode": "after_views", "max_views": 2 }
```

**200 OK**: the updated message object.

**409 Conflict**

```json
{ "message": "The message has already been read and is sealed forever." }
```

**Errors:** `403`, `404`, `422`.

---

## DELETE /messages/{id}

Deletes the message record and its files right away.

**Headers:** `X-Owner-Token` (required).

**204 No Content**

**Errors:** `403`, `404`.

---

## GET /attachments/{id}/download?expires=…&signature=…

Streams the file from the app's `attachments` disk, with its original name and `Content-Type`. Use the exact `download_url` returned by `GET /messages/{id}` or the preview. Changing any part of the URL, or using it after the TTL, breaks the signature.

Files of a destroyed message stay downloadable until the links from the last view expire. After that, the scheduler purges them.

**200 OK**: the file (`Content-Disposition: attachment`).

**403 Forbidden**: `{ "message": "The download link is invalid or has expired." }`

**404 Not Found**: the attachment was deleted or purged.

---

## GET /stats

**200 OK**

```json
{
  "messages": { "sealed": 1, "available": 4, "destroyed": 1 },
  "next_unlock": {
    "id": "9f1c...",
    "title": "Time capsule",
    "unlock_at": "2026-11-05T12:00:00.000000Z",
    "seconds_remaining": 604734
  },
  "files": { "count": 3, "total_size_bytes": 48211 }
}
```

`next_unlock` is `null` when no message is sealed.

---

## GET /up

Laravel health endpoint, outside `/api`. The Docker healthcheck of `app` calls it. It returns `200` when the application boots.
