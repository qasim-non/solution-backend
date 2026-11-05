# Firebase setup (Analytics + Web Push)

This document lists **exactly** which values must be added to enable Firebase
Analytics and FCM web push. The integration is already coded; only the secrets
and keys below are missing.

> Nothing in this file is a secret. The service-account JSON and the VAPID key
> must **never** be committed. They live only in local `.env` files / hosting
> dashboards.

---

## 1. Architecture at a glance

| Piece | Location | Purpose |
| --- | --- | --- |
| Admin SDK wrapper | `app/Services/FirebaseService.php` | Sends FCM messages, removes stale tokens |
| Notification orchestration | `app/Services/NotificationService.php` | Admin + customer notification flows |
| Queued sender | `app/Jobs/SendPushNotification.php` | Sends outside the HTTP request |
| Device tokens | `app/Http/Controllers/DeviceTokenController.php` | Public register / remove API |
| In-app notifications | `app/Http/Controllers/NotificationController.php` | Admin notification centre |
| Web SDK init | `frontend/src/lib/firebase.ts` | Firebase app + Analytics |
| Push helpers | `frontend/src/lib/notifications.ts` | Permission, token, registration |
| Service worker | `frontend/public/firebase-messaging-sw.js` | Background push handling |

Push is **opt-in**. Browsers require a user gesture, so the app only asks for
permission when the user presses the bell button in the customer navbar or the
admin sidebar.

---

## 2. Backend environment variables

Add these to `backend/.env` (and to your hosting provider's environment):

```dotenv
# Path to the service-account JSON, relative to the backend root (or absolute).
FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json

# Alternative for hosts without a persistent disk (Railway, Render, Heroku):
# paste the full JSON on one line. Takes precedence over FIREBASE_CREDENTIALS.
# FIREBASE_CREDENTIALS_JSON={"type":"service_account", ... }

FIREBASE_PROJECT_ID=solution-92dce

# Public URL of the React app. Used as the click target of notifications.
FRONTEND_URL=http://localhost:3000
```

`FIREBASE_PROJECT_ID` is optional when the service account is supplied (it is
read from the JSON), but setting it keeps behaviour predictable.

### Add the service-account JSON

1. Firebase Console → **Project settings** → **Service accounts**.
2. Click **Generate new private key** → download the `.json` file.
3. Save it as:

   ```
   backend/storage/app/firebase/service-account.json
   ```

   The `storage/app/firebase/` directory is tracked (via `.gitkeep`) but
   `*.json` inside it is git-ignored.

### Restart the queue worker

Push notifications are queued (`QUEUE_CONNECTION=database`). The worker must be
running or messages stay in the `jobs` table:

```bash
# Docker (development stack)
docker compose restart queue

# Production stack
docker compose -f docker-compose.prod.yml restart queue
```

---

## 3. Frontend environment variables

Add these to `frontend/.env.development` and `frontend/.env.production`
(these files are git-ignored):

```dotenv
VITE_API_URL=/api

VITE_FIREBASE_API_KEY=...
VITE_FIREBASE_AUTH_DOMAIN=solution-92dce.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=solution-92dce
VITE_FIREBASE_STORAGE_BUCKET=solution-92dce.firebasestorage.app
VITE_FIREBASE_MESSAGING_SENDER_ID=157489948196
VITE_FIREBASE_APP_ID=1:157489948196:web:6bfb5b3018446772481ab1
VITE_FIREBASE_MEASUREMENT_ID=G-NP09WDQSTX

# Web Push certificate key pair — REQUIRED for push.
VITE_FIREBASE_VAPID_KEY=...
```

The Firebase web config values are **public by design** (they ship to every
browser). The only missing value is the VAPID key.

### Get the VAPID key

Firebase Console → **Project settings** → **Cloud Messaging** →
**Web Push certificates** → **Generate key pair**. Copy the key into
`VITE_FIREBASE_VAPID_KEY`.

Without it the bell button shows **"الإشعارات غير مهيأة"** and Analytics still
works.

### Rebuild the frontend

Vite inlines `VITE_*` values at build time, so a rebuild is required:

```bash
cd frontend
npm run build
```

For the Docker image, pass the values as build args (already declared in
`frontend/Dockerfile`):

```bash
docker build \
  --build-arg VITE_FIREBASE_API_KEY=... \
  --build-arg VITE_FIREBASE_AUTH_DOMAIN=... \
  --build-arg VITE_FIREBASE_PROJECT_ID=... \
  --build-arg VITE_FIREBASE_STORAGE_BUCKET=... \
  --build-arg VITE_FIREBASE_MESSAGING_SENDER_ID=... \
  --build-arg VITE_FIREBASE_APP_ID=... \
  --build-arg VITE_FIREBASE_MEASUREMENT_ID=... \
  --build-arg VITE_FIREBASE_VAPID_KEY=... \
  -t solution-frontend .
```

---

## 4. API endpoints

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/device-tokens` | Public (optional Sanctum) | Register / refresh an FCM token |
| `DELETE` | `/api/device-tokens` | Public | Remove a token |
| `GET` | `/api/notifications` | Sanctum | Paginated in-app notifications |
| `PATCH` | `/api/notifications/{id}/read` | Sanctum | Mark one as read |
| `PATCH` | `/api/notifications/read-all` | Sanctum | Mark all as read |

When a request is registered with a Sanctum bearer token, the token is linked
to the admin so the dashboard receives **new request** pushes. Anonymous
customers get a **request completed** push via the stable `device_id` stored on
the request.

---

## 5. Test checklist

1. `php artisan migrate --force` (creates `device_tokens`, adds
   `requests.device_id`).
2. Start the queue worker.
3. Open the app, press the bell button, allow notifications.
4. Confirm a row exists in `device_tokens`.
5. Submit a customer request → admins receive an in-app row in `notifications`
   and (once configured) a push.
6. Mark the request complete → the submitting browser receives a push.
7. In the Firebase console, **Cloud Messaging** → send a test message to the
   token to verify delivery independently of the app.

If push is skipped, check `storage/logs/laravel.log` for
`Firebase is not configured; skipping push notification.` — that means the
service-account file or `FIREBASE_CREDENTIALS_JSON` is missing.

---

## 6. Security notes

- `storage/app/firebase/*.json` and both `.env*` files are git-ignored.
- The backend never logs the service-account JSON or full FCM tokens.
- Invalid/unknown FCM tokens are deleted automatically so the table does not
  accumulate dead entries.
- Push failures are caught and logged; they never roll back a customer request
  or an admin action.
- Analytics events only include non-sensitive metadata (page path, request
  counts), never phone numbers or descriptions.
