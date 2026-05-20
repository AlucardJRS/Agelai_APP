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

## 3) Configurar Secretos Sin Editar Archivos a Mano

Ejecuta el asistente interactivo:

```powershell
cd tools
.\setup_local_secrets.ps1
```

Este asistente te pedira por pantalla:

- MariaDB local
- Google OAuth (`client_id`, `client_secret`, redirect URI)
- SMTP (si quieres correo real en pruebas)
- `AGELAI_APP_SECRET` (si no lo introduces, se genera automaticamente)

Guarda todo en `backend/api/.env.local` y el backend lo carga automaticamente al arrancar.

## 4) Google Cloud (una sola vez)

En Google Cloud Console crea credenciales OAuth 2.0 tipo **Web** y registra:

- Redirect URI: `http://127.0.0.1:8000/oauth/google/callback`

Con eso, cuando pulses `Entrar con Google` en la app, Google mostrara la pantalla real de login (usuario + contraseña o cuenta ya iniciada).

## 5) Iniciar Backend + Dashboard Web

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

Usuario local de pruebas (clientes app):

- username: `clubagelai`
- password: `clubagelai`

Las tablas se crean automaticamente al primer arranque.

## 6) Iniciar App Flutter Android

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

## 7) Flujo de Prueba Recomendado

1. Inicia sesion en dashboard admin.
2. Crea actividades con cupos y sede desde **Actividades**:
   - `Cala d'Or (rotonda Farash)` (principal)
   - `Cala Egos (delante del SYP)`
3. En la app Flutter pulsa **Entrar con Google** y completa tus credenciales reales.
   - Alternativa de pruebas: **Entrar con usuario local** (`clubagelai`).
4. Veras estado pendiente de perfil si aun no tienes modulos.
5. En dashboard, asigna modulos y estado `active`.
6. En app, abre **Perfil** y pulsa **Vincular Google** para autorizar Calendar.
7. Reserva una actividad y confirma el codigo recibido por email.
8. En dashboard, valida manualmente la reserva final.
9. Tras aprobar:
   - se envia email de confirmacion final al usuario
   - se crea evento en Google Calendar del usuario vinculado
   - se bloquea la plaza de forma definitiva

## 8) Seguridad Implementada en Esta Version

- Consultas preparadas con PDO (mitigacion SQL injection).
- CSRF token en formularios del dashboard.
- Hash de password admin (`password_hash`).
- Hash de password para usuarios locales (`password_hash`).
- Rehash automatico de passwords a perfil fuerte (Argon2id cuando esta disponible).
- Tokens API hasheados en base de datos.
- Cabeceras HTTP de seguridad.
- Cabeceras extra: CSP reforzada, COOP/CORP, no-cache y HSTS en HTTPS.
- Limitador de peticiones por IP/ruta.
- Bloqueo temporal anti-fuerza-bruta por usuario+IP en login admin y login local.
- Escapado HTML en vistas (mitigacion XSS).
- Tokens Google cifrados en MariaDB con AES-256-GCM.
- Estados OAuth efimeros (anti-CSRF en callback OAuth).
- Log de integraciones (OAuth/SMTP/Calendar) para auditoria.
- Endurecimiento de sesion admin: fingerprint, timeout por inactividad, cookie Strict HttpOnly.
- Regla de cancelacion minima de 2 horas.
- Aprobacion manual admin obligatoria en todas las reservas.

## 9) Pendiente para Produccion (fase siguiente)

- HTTPS obligatorio + HSTS.
- Rotacion de secretos y cuentas de servicio segun hosting final.
- Ajuste fino de indices y backups sobre MariaDB del hosting final.
- Monitorizacion y alertas operativas de correo/API externas.
