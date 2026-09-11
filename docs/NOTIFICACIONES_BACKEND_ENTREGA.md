# Entrega backend — Notificaciones

## Alcance implementado

Se completó la base backend para publicar en tiempo real cada reserva creada, respetando el alcance real de las membresías activas.

El frontend, la campana, los toast y la persistencia de notificaciones leídas quedan fuera de esta entrega.

## Flujo de una nueva reserva

```text
CreateReservationHandler
    -> guarda reserva y segmentos de precio
    -> COMMIT exitoso
    -> ReservationCreated
    -> job de broadcasting en Queue
    -> Laravel Reverb
    -> canales privados autorizados
```

`ReservationCreated` implementa `ShouldBroadcast` y `ShouldDispatchAfterCommit`. Por lo tanto:

- la petición HTTP no espera la publicación WebSocket;
- nunca se publica una reserva que terminó en rollback;
- el evento transporta un snapshot de valores escalares y no serializa la entidad de dominio completa.

## Canales

Canal global del club:

```text
club.{clubId}
```

Canal de sucursal:

```text
club.{clubId}.branch.{branchId}
```

En Echo se consumen con `Echo.private(nombre)`. Reverb envía el nombre físico `private-club...`, pero Laravel normaliza ese prefijo antes de buscar el callback; por eso `routes/channels.php` registra `club...`. Los callbacks declaran explícitamente el guard `sanctum`, igual que la ruta `/broadcasting/auth`.

Una membresía global activa autoriza el canal global y cualquier sucursal del club. Una membresía limitada solamente autoriza su sucursal. Una membresía inactiva o de otro club no autoriza el canal.

## Endpoint de canales autorizados

```http
GET /api/clubs/{club_id}/notification-channels
Authorization: Bearer {token}
```

Respuesta con membresía global:

```json
{
  "status": true,
  "data": {
    "club_id": 1,
    "channels": ["club.1"]
  }
}
```

Respuesta con membresías en sucursales 6 y 8:

```json
{
  "status": true,
  "data": {
    "club_id": 2,
    "channels": [
      "club.2.branch.6",
      "club.2.branch.8"
    ]
  }
}
```

Si el usuario no tiene una membresía activa en el club, responde `403`.

## Evento publicado

Nombre:

```text
reservation.created
```

Payload:

```json
{
  "reservation_id": 150,
  "club_id": 2,
  "branch_id": 8,
  "starts_at": "2030-09-10 18:00:00",
  "ends_at": "2030-09-10 19:00:00",
  "status": "PENDING",
  "total_price": "25000.00",
  "court": {
    "id": 9,
    "name": "Cancha 1"
  },
  "customer": {
    "name": "Juan Pérez"
  }
}
```

No se transmiten email, teléfono, token público ni otros datos sensibles.

## Configuración local

Variables requeridas:

```env
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=local-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:3000
```

Después de modificar el `.env`:

```bash
php artisan optimize:clear
```

El comando ya configurado levanta HTTP, Queue, Scheduler, Reverb y Vite:

```bash
composer dev
```

También pueden levantarse por separado:

```bash
php artisan serve
php artisan queue:work database --sleep=1 --tries=3 --timeout=90
php artisan schedule:work
php artisan reverb:start
```

## Emails existentes preservados

La entrega mantiene los flujos en cola y posteriores al commit ya implementados para:

- reserva confirmada;
- reserva cancelada;
- reserva expirada;
- devolución completada.

Cada intento mantiene la auditoría `EmailLog` con estados `PENDING`, `SENT` o `FAILED`.

## Pruebas cubiertas

- autorización de canal global;
- autorización por sucursal;
- rechazo de otra sucursal, otro club y membresía inactiva;
- resolución de canales sin duplicados;
- prioridad del canal global sobre canales específicos;
- endpoint autenticado y rechazo sin membresía activa;
- canales y payload de `ReservationCreated`;
- ausencia de datos sensibles;
- contrato de cola y ejecución posterior al commit.

## Fuera de alcance

- implementación Vue/Echo;
- toast y campana;
- tabla de notificaciones internas;
- listado, contador y marcado como leído;
- nuevos broadcasts para pagos, clientes o conflictos de reservas fijas.

Estas extensiones pueden reutilizar los mismos canales y el mismo esquema de autorización.
