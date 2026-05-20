# Setup Local - Club Agelai

## 1) Requisitos

- PHP 8.3+
- MariaDB 10.6+ (o MySQL compatible)
- Extensiones PHP: `pdo_mysql`, `openssl`, `curl`
- Flutter SDK
- Android Studio / emulador Android

## 2) Preparar MariaDB Local

Crea la base de datos (por ejemplo desde phpMyAdmin o consola SQL):

```sql
CREATE DATABASE IF NOT EXISTS agelai_dietas
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Configura credenciales mediante variables de entorno antes de levantar el backend:

```powershell
$env:AGELAI_DB_HOST="127.0.0.1"
$env:AGELAI_DB_PORT="3306"
$env:AGELAI_DB_NAME="agelai_dietas"
$env:AGELAI_DB_USER="root"
$env:AGELAI_DB_PASS=""
```

## 3) Configurar Secretos Locales (Google + SMTP)

Define primero una clave interna para cifrar tokens OAuth en MariaDB:

```powershell
$env:AGELAI_APP_SECRET="cambia-esta-clave-local-larga-y-unica"
```

### Google OAuth + Calendar

En Google Cloud Console crea credenciales OAuth 2.0 (tipo Web) y configura:

- Redirect URI: `http://127.0.0.1:8000/oauth/google/callback`
- Scope usado por la app: `https://www.googleapis.com/auth/calendar.events`

Variables de entorno:

```powershell
$env:AGELAI_GOOGLE_CLIENT_ID="tu-client-id.apps.googleusercontent.com"
$env:AGELAI_GOOGLE_CLIENT_SECRET="tu-client-secret"
$env:AGELAI_GOOGLE_REDIRECT_URI="http://127.0.0.1:8000/oauth/google/callback"
```

### SMTP real (correo de confirmacion)

```powershell
$env:AGELAI_SMTP_HOST="smtp.tu-proveedor.com"
$env:AGELAI_SMTP_PORT="587"
$env:AGELAI_SMTP_SECURE="tls"
$env:AGELAI_SMTP_USER="usuario-smtp"
$env:AGELAI_SMTP_PASS="password-smtp"
$env:AGELAI_SMTP_FROM_EMAIL="noreply@tu-dominio.com"
$env:AGELAI_SMTP_FROM_NAME="Club Agelai"
```

## 4) Iniciar Backend + Dashboard Web

Desde la raiz del proyecto:

```powershell
cd backend/api
php -S 127.0.0.1:8000 -t public
```

Con esto queda disponible:

- Dashboard web: `http://127.0.0.1:8000/dashboard`
- API: `http://127.0.0.1:8000/api/...`

Credenciales admin iniciales:

- usuario: `admin`
- password: `Admin12345!`

Las tablas se crean automaticamente al primer arranque.

## 5) Iniciar App Flutter Android

Si es la primera vez y faltan carpetas nativas (`android/`, etc):

```powershell
cd mobile_flutter/app
flutter create .
```

Luego:

```powershell
flutter pub get
flutter run
```

La app usa por defecto `http://10.0.2.2:8000` (correcto para emulador Android).

Si pruebas en movil fisico, cambia `ApiClient._baseUrl` en:

- `mobile_flutter/app/lib/main.dart`

## 6) Flujo de Prueba Recomendado

1. Inicia sesion en dashboard admin.
2. Crea actividades con cupos y sede desde **Actividades**:
   - `Cala d'Or (rotonda Farash)` (principal)
   - `Cala Egos (delante del SYP)`
3. En la app Flutter, registra un usuario por Google ID local.
4. Veras estado pendiente de perfil.
5. En dashboard, asigna modulos y estado `active`.
6. En app, abre **Perfil** y pulsa **Vincular Google** para autorizar Calendar.
7. Reserva una actividad y confirma el codigo recibido por email.
8. En dashboard, valida manualmente la reserva final.
9. Tras aprobar:
   - se envia email de confirmacion final al usuario
   - se crea evento en Google Calendar del usuario vinculado
   - se bloquea la plaza de forma definitiva

## 7) Seguridad Implementada en Esta Version

- Consultas preparadas con PDO (mitigacion SQL injection).
- CSRF token en formularios del dashboard.
- Hash de password admin (`password_hash`).
- Tokens API hasheados en base de datos.
- Cabeceras HTTP de seguridad.
- Limitador de peticiones por IP/ruta.
- Escapado HTML en vistas (mitigacion XSS).
- Tokens Google cifrados en MariaDB con AES-256-GCM.
- Estados OAuth efimeros (anti-CSRF en callback OAuth).
- Log de integraciones (OAuth/SMTP/Calendar) para auditoria.
- Regla de cancelacion minima de 2 horas.
- Aprobacion manual admin obligatoria en todas las reservas.

## 8) Pendiente para Produccion (fase siguiente)

- HTTPS obligatorio + HSTS.
- Rotacion de secretos y cuentas de servicio segun hosting final.
- Ajuste fino de indices y backups sobre MariaDB del hosting final.
- Monitorizacion y alertas operativas de correo/API externas.
