# Club Agelai - Nuevo Proyecto Base (Local)

Este repositorio arranca desde cero para construir:

- API backend compartida para web y Android
- Dashboard web de administracion
- App Android (Flutter) conectada a la misma base de datos

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
- `docs/setup_local.md`: guia paso a paso para ejecutar en local

## Siguiente Paso

Revisa [docs/setup_local.md](C:\Users\mtrfu\Documents\Agelai_Dietas\docs\setup_local.md) para arrancar el entorno.
