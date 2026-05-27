# Deploy en Plesk (agelaigym.es)

## 1) Dominios y SSL

Configura estos hostnames con SSL valido:

- `agelaigym.es` (web/app publica)
- `dash.agelaigym.es` (dashboard admin)

Ambos pueden apuntar al mismo codigo PHP al principio.

## 2) Crear base de datos MariaDB en Plesk

No se "sube" MariaDB local como servicio. En hosting se crea una BD nueva y se importa esquema/datos.

En Plesk:

1. `Databases` -> `Add Database`
2. Nombre recomendado: `agelaigym_prod`
3. Crear usuario DB dedicado (no usar root)
4. Guardar host, puerto, usuario, password

## 3) Importar esquema y datos desde local

En local, exporta SQL:

```powershell
"F:\wamp64\bin\mysql\mysql8.4.3\bin\mysqldump.exe" -h 127.0.0.1 -P 3306 -u root agelai_dietas > agelai_export.sql
```

Luego en Plesk importa `agelai_export.sql` en `agelaigym_prod` (phpMyAdmin o import DB de Plesk).

## 4) Subir backend

Sube carpeta `backend/api` al docroot del dominio/subdominio (o al path configurado).

Requisito:

- El virtual host debe servir `backend/api/public` como document root.

## 5) Configurar secretos en servidor

En servidor crea:

- `backend/api/.env.local`

Basate en:

- `backend/api/.env.production.example`

Valores clave para tu caso:

- `AGELAI_BASE_URL="https://dash.agelaigym.es"`
- `AGELAI_GOOGLE_REDIRECT_URI="https://dash.agelaigym.es/oauth/google/callback"`

## 6) Verificaciones post-deploy

1. `https://dash.agelaigym.es/dashboard/login`
2. `https://dash.agelaigym.es/manifest.webmanifest`
3. `https://dash.agelaigym.es/service-worker.js`
4. login admin funcional
5. API responde (`/api/me` con token)

## 7) Google OAuth (fase final)

En Google Cloud, cuando toque:

- Authorized redirect URI:
  - `https://dash.agelaigym.es/oauth/google/callback`

## 8) SMTP (fase final)

Cuando definas proveedor SMTP real, completa:

- `AGELAI_SMTP_HOST`
- `AGELAI_SMTP_PORT`
- `AGELAI_SMTP_SECURE`
- `AGELAI_SMTP_USER`
- `AGELAI_SMTP_PASS`
- `AGELAI_SMTP_FROM_EMAIL`
- `AGELAI_SMTP_FROM_NAME`
