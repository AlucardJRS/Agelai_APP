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
- `GET /api/activities`
- `GET /api/schedule`
- `POST /api/activities/{id}/reserve`
- `POST /api/reservations/{id}/confirm`
- `POST /api/reservations/{id}/cancel`
- `GET /api/reservations`
- `GET /api/announcements`

## Nota

La integracion real con Google OAuth, Google Calendar y correo SMTP se deja para la siguiente fase (produccion).

`POST /api/activities/{id}/reserve` recibe `payment_method` con valores:

- `cash` (efectivo)
- `bizum`
- `card` (tarjeta)
