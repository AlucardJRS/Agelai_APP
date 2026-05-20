# Setup Local - Club Agelai

## 1) Requisitos

- PHP 8.3+
- MariaDB 10.6+ (o MySQL compatible)
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

## 3) Iniciar Backend + Dashboard Web

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

## 4) Iniciar App Flutter Android

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

## 5) Flujo de Prueba Recomendado

1. Inicia sesion en dashboard admin.
2. Crea actividades con cupos y sede (Cala d'Or o Cala Egos) desde **Actividades**.
3. En la app Flutter, registra un usuario por Google ID local.
4. Veras estado pendiente de perfil.
5. En dashboard, asigna modulos y estado `active`.
6. En app, actualiza, revisa el tab **Horarios** y reserva actividad.
7. Confirma codigo de usuario (modo local).
8. En dashboard, valida manualmente la reserva final.

## 6) Seguridad Implementada en Esta Version

- Consultas preparadas con PDO (mitigacion SQL injection).
- CSRF token en formularios del dashboard.
- Hash de password admin (`password_hash`).
- Tokens API hasheados en base de datos.
- Cabeceras HTTP de seguridad.
- Limitador de peticiones por IP/ruta.
- Escapado HTML en vistas (mitigacion XSS).
- Regla de cancelacion minima de 2 horas.
- Aprobacion manual admin obligatoria en todas las reservas.

## 7) Pendiente para Produccion (fase siguiente)

- Login Google OAuth real.
- Google Calendar API real.
- Envio de email SMTP real.
- HTTPS obligatorio + HSTS.
- Ajuste fino de indices y backups sobre MariaDB del hosting final.
- Registro de auditoria ampliado y backups programados.
