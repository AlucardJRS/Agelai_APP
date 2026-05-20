# Backend API + Dashboard (PHP)

## Ejecutar

Configura todo con asistente interactivo (sin editar secretos a mano):

```powershell
cd tools
.\setup_local_secrets.ps1
```

Esto genera `backend/api/.env.local`, que se carga automaticamente en `bootstrap.php`.

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
- `POST /dashboard/users/create`
- `POST /dashboard/users/block`
- `POST /dashboard/users/deactivate`
- `POST /dashboard/users/reset-password`
- `POST /dashboard/users/delete`
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

- `POST /api/auth/google-login/start`
- `GET /api/auth/google-login/status?state=...`
- `POST /api/auth/google-login` (fallback legacy local)
- `POST /api/auth/local-login` (username/password local)
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

## Usuario local de pruebas

En local, al arrancar backend se crea automaticamente (si no existe):

- `username`: `clubagelai`
- `password`: `clubagelai`

Puedes cambiar este seed en `backend/api/config.php` (`local_user_seed`).
