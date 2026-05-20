# App Flutter - Club Agelai

App Android de pruebas conectada a la API local.

## Arranque rapido

```powershell
flutter create .
flutter pub get
flutter run
```

## Flujo funcional actual

- Login/registro local con ID de Google simulado
- Mensaje de perfil pendiente sin modulos asignados
- Tablon de horarios semanales por sede
- Vista de actividades segun permisos
- Reserva con eleccion de metodo de pago (efectivo/Bizum/tarjeta) y confirmacion de codigo (modo local)
- Mis reservas con opcion de cancelacion
- Avisos/promociones publicados desde dashboard web

## URL backend

Definida en:

- `lib/main.dart` dentro de `ApiClient._baseUrl`

Valor por defecto para emulador Android:

- `http://10.0.2.2:8000`
