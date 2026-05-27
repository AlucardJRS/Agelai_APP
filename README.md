# Club Agelai - Nuevo Proyecto Base (Local)

Este repositorio arranca desde cero para construir:

- API backend compartida para web y Android
- Dashboard web de administracion
- App Android (Flutter) conectada a la misma base de datos
- Horarios semanales visuales y gestion por sedes (Cala d'Or y Cala Egos)

## Stack Elegido

- Backend/API + Dashboard: PHP 8.3 (sin frameworks externos para pruebas locales)
- Base de datos local y produccion: MariaDB
- App Android: Flutter (codigo unico y escalable a futuro)

## Motivos de la Eleccion

- Flutter permite mantener una sola base de codigo para Android hoy y abrir puerta a iOS manana.
- PHP es ideal para tu hosting habitual y despliegues economicos.
- MariaDB encaja con tu preferencia, es robusta para reservas/cupos y es muy comun en hosting compartido.

## Estructura

- `backend/api`: API REST + dashboard web + seguridad base
- `mobile_flutter/app`: app Flutter para Android conectada al backend
- `mobile_webapp/twa_android`: contenedor Android para la Web App (generado con Bubblewrap)
- `docs/setup_local.md`: guia paso a paso para ejecutar en local
- `docs/deploy_plesk.md`: guia de despliegue a hosting Plesk (agelaigym.es)

## Reglas de Negocio de Esta Iteracion

- Base de datos: MariaDB (local y produccion).
- Aprobacion manual admin: obligatoria para todas las reservas.
- Metodos de pago iniciales: efectivo, Bizum y tarjeta.
- Sedes activas: Cala d'Or (principal) y Cala Egos.

## Acceso Local de Pruebas

- Admin dashboard inicial: `admin / Admin12345!`
- Usuario app local inicial: `clubagelai / clubagelai`

Desde el dashboard puedes editar nombre, permisos/modulos, resetear password local, bloquear/desactivar y eliminar usuarios.

## Seguridad (implementada)

- MariaDB con consultas preparadas PDO y emulacion desactivada.
- Login admin/local con bloqueo temporal por intentos fallidos.
- Passwords hasheadas con perfil fuerte y rehash automatico.
- Sesiones admin reforzadas (fingerprint + timeout + cookies seguras).
- Cabeceras HTTP endurecidas y politica CSP restrictiva.

## Siguiente Paso

Revisa [docs/setup_local.md](C:\Users\mtrfu\Documents\Agelai_Dietas\docs\setup_local.md) para arrancar el entorno.

## Scripts de Automatizacion (Local)

- `tools/setup_android_webapp_env.ps1`: prepara entorno Android Web App (SDK tools, licencias, PATH, variables).
- `tools/build_twa_android.ps1`: genera/actualiza proyecto Android (TWA) desde el `manifest.webmanifest` publicado por HTTPS.

## Produccion (Plesk)

- Ejemplo de secretos: `backend/api/.env.production.example`
- Despliegue paso a paso: [deploy_plesk.md](C:\Users\mtrfu\Documents\Agelai_Dietas\docs\deploy_plesk.md)
