# Backend API + Dashboard (PHP)

## Ejecutar

Define las variables de conexion MariaDB:

```powershell
$env:AGELAI_DB_HOST="127.0.0.1"
$env:AGELAI_DB_PORT="3306"
$env:AGELAI_DB_NAME="agelai_dietas"
$env:AGELAI_DB_USER="root"
$env:AGELAI_DB_PASS=""
```

Configura secreto interno y, si quieres integraciones reales, Google + SMTP:

```powershell
$env:AGELAI_APP_SECRET="cambia-esta-clave-local-larga-y-unica"
$env:AGELAI_GOOGLE_CLIENT_ID="tu-client-id.apps.googleusercontent.com"
$env:AGELAI_GOOGLE_CLIENT_SECRET="tu-client-secret"
$env:AGELAI_GOOGLE_REDIRECT_URI="http://127.0.0.1:8000/oauth/google/callback"
$env:AGELAI_SMTP_HOST="smtp.tu-proveedor.com"
$env:AGELAI_SMTP_PORT="587"
$env:AGELAI_SMTP_SECURE="tls"
$env:AGELAI_SMTP_USER="usuario-smtp"
$env:AGELAI_SMTP_PASS="password-smtp"
$env:AGELAI_SMTP_FROM_EMAIL="noreply@tu-dominio.com"
$env:AGELAI_SMTP_FROM_NAME="Club Agelai"
```

Luego inicia el servidor:

```powershell
php -S 127.0.0.1:8000 -t public
```

Nota: al primer arranque se crean automaticamente las tablas en MariaDB.

## Dashboard

- `GET /dashboard/login`
- `POST /dashboard/login`
- `GET /dashboard`
- `GET /dashboard/users`
- `POST /dashboard/users/update`
- `GET /dashboard/activities`
- `GET /dashboard/schedule`
- `POST /dashboard/activities/create`
- `POST /dashboard/activities/toggle`
- `GET /dashboard/reservations`
- `POST /dashboard/reservations/approve`
- `POST /dashboard/reservations/reject`
- `GET /dashboard/announcements`
- `POST /dashboard/announcements/create`

## API (JSON)

- `POST /api/auth/google-login`
- `GET /api/me`
- `POST /api/google/connect/start`
- `GET /api/google/connect/status`
- `POST /api/google/disconnect`
- `GET /api/activities`
- `GET /api/schedule`
- `POST /api/activities/{id}/reserve`
- `POST /api/reservations/{id}/confirm`
- `POST /api/reservations/{id}/cancel`
- `GET /api/reservations`
- `GET /api/announcements`

## OAuth Callback (Google)

- `GET /oauth/google/callback`
- Debe estar registrado como redirect URI en Google Cloud Console.

## Reservas y Pagos

`POST /api/activities/{id}/reserve` recibe `payment_method` con valores:

- `cash` (efectivo)
- `bizum`
- `card` (tarjeta)

Flujo actual:

1. Usuario crea pre-reserva.
2. Backend envia codigo por SMTP (si esta configurado).
3. Usuario confirma codigo.
4. Reserva pasa a `pending_admin_approval`.
5. Admin aprueba/rechaza en dashboard.
6. Si aprueba: se envia correo final y se crea evento en Google Calendar si el usuario esta vinculado.
