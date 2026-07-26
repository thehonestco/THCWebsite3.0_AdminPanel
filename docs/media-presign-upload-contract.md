# Media Presigned Upload — Frontend Contract

Backend-only implementation is in this repo (`THCWebsite3.0_AdminPanel`). This doc is the
handoff contract for whichever frontend (React/Next.js) client will call these endpoints —
no frontend code lives in this repo.

Both endpoints require `Authorization: Bearer <sanctum token>` and permission
`bottom-menu.media-center.add`.

## 1. Request presigned upload URLs

`POST /api/media/presign-batch`

```json
{
  "files": [
    { "filename": "banner.jpg", "mime_type": "image/jpeg" },
    { "filename": "brochure.pdf", "mime_type": "application/pdf" }
  ]
}
```

- `mime_type` must be one of the types in `config('media.presign.allowed_mime_types')`
  (images, common video/audio formats, PDF, Word, ZIP). Anything else is rejected with a
  422 for the whole batch (this is request validation, not a per-file confirm error).
- Max files per batch: `config('media.max_files_per_request')` (default 20).

Response (200):

```json
{
  "success": true,
  "data": [
    {
      "original_filename": "banner.jpg",
      "key": "uploads/3f1c9e2a-....jpg",
      "upload_url": "https://<bucket>.s3.<region>.amazonaws.com/uploads/3f1c9e2a-....jpg?X-Amz-...",
      "headers": { "Content-Type": "image/jpeg" },
      "mime_type": "image/jpeg"
    },
    { "...": "one object per input file, same order" }
  ]
}
```

- `upload_url` expires in `config('media.presign.expiry_minutes')` (default 5 minutes).
- **Important:** the `PUT` request to `upload_url` must send exactly the `headers` returned
  (at minimum `Content-Type` matching what was requested) — the signature was computed
  against that content type; mismatched headers cause S3 to reject the upload with 403.

## 2. Frontend uploads directly to S3

For each file, `PUT` the raw file bytes to its `upload_url` with the given `headers`. No
AWS SDK, no credentials, no bucket/region config needed on the frontend. Track progress via
`onUploadProgress` (axios) or the equivalent for your HTTP client.

## 3. Confirm the upload

`POST /api/media/confirm`

```json
{
  "files": [
    { "key": "uploads/3f1c9e2a-....jpg", "mime_type": "image/jpeg", "original_filename": "banner.jpg" }
  ]
}
```

- `key` and `mime_type` must match what `presign-batch` returned for that file.
- `original_filename` is optional (falls back to the key's basename).
- Each file is processed **independently** — one file failing (e.g. it was never actually
  uploaded to S3) does not fail the rest of the batch. Check `data[i].status` per item.

Response (200):

```json
{
  "success": true,
  "data": [
    {
      "key": "uploads/3f1c9e2a-....jpg",
      "status": "ok",
      "data": {
        "id": 42,
        "media_code": "MC-042",
        "media_type": "image",
        "processing_status": "processing",
        "url": null,
        "...": "see full shape below"
      }
    },
    {
      "key": "uploads/missing-file.png",
      "status": "error",
      "message": "Uploaded file was not found in storage for key: uploads/missing-file.png"
    }
  ]
}
```

## 4. Processing status lifecycle

- `pdf`, Word, ZIP, audio and any other non-image/video type: finalized **immediately** on
  confirm — `processing_status: "ready"`, `url` populated right away, no further polling
  needed.
- `image`/`video` (unless already `image/webp` / `video/webm`, which also finalize
  immediately): `processing_status: "processing"` on confirm, `url` is `null`. A background
  job converts the file (image → WebP, video → WebM) and updates the record. Poll
  `GET /api/media-center/{id}` (existing endpoint) until `processing_status` becomes
  `"ready"` (or `"failed"`), then use `url`.
- There is no push/broadcast notification for this yet — polling is the only option today.

## 5. Full asset object shape (same as existing `/api/media-center` endpoints)

```json
{
  "id": 42,
  "media_code": "MC-042",
  "original_name": "banner.jpg",
  "title": "banner",
  "media_type": "image",
  "type_label": "Image",
  "status": "active",
  "status_label": "Active",
  "url": "https://.../media-center/images/2026/07/....webp",
  "disk": "s3",
  "path": "media-center/images/2026/07/....webp",
  "converted_extension": "webp",
  "converted_mime_type": "image/webp",
  "source_extension": "jpg",
  "source_mime_type": "image/jpeg",
  "size_bytes": 123456,
  "width": 1920,
  "height": 1080,
  "duration_seconds": null,
  "processing_status": "ready",
  "created_at": "2026-07-25 20:00:00",
  "uploaded_on": "25/07/2026",
  "uploaded_by": { "id": 1, "name": "Admin" }
}
```
