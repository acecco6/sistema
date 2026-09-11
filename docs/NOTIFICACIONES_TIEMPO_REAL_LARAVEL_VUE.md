# Implementación de Notificaciones en Tiempo Real
## Laravel + Vue + Reverb + Echo

## 1. Objetivo

Implementar un sistema de notificaciones en tiempo real para el sistema de clubes, de modo que un usuario reciba eventos únicamente del **club y/o sucursales correspondientes al contexto que está utilizando actualmente**.

El primer caso de uso será:

- Notificar cuando se crea una nueva reserva.

La misma arquitectura debe quedar preparada para reutilizarse después con:

- Reservas confirmadas.
- Reservas canceladas.
- Pagos creados.
- Pagos aprobados o rechazados.
- Clientes creados.
- Conflictos de reservas fijas.
- Cambios operativos de sucursales o canchas.
- Otros eventos futuros.

---

# 2. Contexto del sistema

Un usuario puede tener múltiples membresías.

Ejemplo:

| membership_id | user_id | role_id | club_id | branch_id | active |
|---:|---:|---:|---:|---:|---:|
| 1 | 1 | 2 | 1 | NULL | 1 |
| 2 | 1 | 3 | 2 | 6 | 1 |
| 3 | 1 | 4 | 2 | 8 | 1 |
| 4 | 1 | 2 | 3 | 13 | 1 |
| 5 | 1 | 3 | 4 | 16 | 0 |

Interpretación:

- `branch_id = NULL`
  - La membresía tiene alcance global sobre todo el club.
- `branch_id != NULL`
  - La membresía tiene alcance únicamente sobre esa sucursal.
- `active = 0`
  - La membresía no debe otorgar acceso ni permitir suscripciones a canales.

Ejemplo conceptual:

```text
Usuario 1

Club 1
└── Membership 1
    └── Alcance global
        └── Recibe eventos de todas las sucursales del Club 1

Club 2
├── Membership 2
│   └── Branch 6
│       └── Manager
└── Membership 3
    └── Branch 8
        └── Employee

Club 3
└── Membership 4
    └── Branch 13
        └── Admin

Club 4
└── Membership 5
    └── Branch 16
        └── Inactiva
```

---

# 3. Regla principal de arquitectura

La seguridad **nunca debe depender del frontend**.

Vue puede solicitar suscribirse a un canal, pero Laravel debe validar siempre:

1. Que el usuario esté autenticado.
2. Que la membresía esté activa.
3. Que la membresía pertenezca al usuario autenticado.
4. Que la membresía pertenezca al club solicitado.
5. Que tenga alcance global o sobre la sucursal solicitada.
6. Si corresponde, que el rol tenga permiso para recibir ese tipo de evento.

El frontend no debe decidir por sí solo qué puede escuchar.

---

# 4. Tecnologías

Backend:

```text
Laravel
Laravel Sanctum
Laravel Broadcasting
Laravel Reverb
Queues
```

Frontend:

```text
Vue
Laravel Echo
pusher-js
Pinia o store equivalente
Sistema de Toast
```

---

# 5. Estructura de canales

Se utilizarán canales privados.

## 5.1 Canal global de club

```text
private-club.{clubId}
```

Ejemplo:

```text
private-club.1
```

Este canal podrá ser escuchado únicamente por usuarios con una membresía activa global del club:

```text
club_id = 1
branch_id = NULL
active = 1
```

---

## 5.2 Canal por sucursal

```text
private-club.{clubId}.branch.{branchId}
```

Ejemplo:

```text
private-club.2.branch.8
```

Este canal podrá ser escuchado únicamente por usuarios que tengan acceso a esa sucursal.

---

# 6. Comportamiento esperado

Supongamos que el usuario tiene:

```text
Club 1
branch_id = NULL

Club 2
branch_id = 6

Club 2
branch_id = 8
```

Si selecciona el Club 1, Vue deberá escuchar:

```text
club.1
```

Si selecciona el Club 2, Vue deberá escuchar:

```text
club.2.branch.6
club.2.branch.8
```

No debe escuchar:

```text
club.2
```

porque no tiene membresía global en ese club.

---

# 7. Flujo general

```text
Usuario inicia sesión
        │
        ▼
Selecciona club/contexto
        │
        ▼
Vue consulta canales autorizados
        │
        ▼
Laravel resuelve memberships activas
        │
        ▼
Laravel devuelve canales autorizados
        │
        ▼
Vue se suscribe mediante Echo
        │
        ▼
Se crea una reserva
        │
        ▼
Laravel dispara ReservationCreated
        │
        ▼
Reverb publica el evento
        │
        ▼
Vue recibe el evento
        │
        ▼
Toast + campana de notificaciones
```

---

# 8. Instalación de Broadcasting y Reverb

En Laravel:

```bash
php artisan install:broadcasting --reverb
```

Luego levantar Reverb:

```bash
php artisan reverb:start
```

Si los broadcasts se procesan mediante cola:

```bash
php artisan queue:work
```

---

# 9. Variables de entorno

Ejemplo:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=local-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret

REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

En producción deberán configurarse los valores correspondientes al dominio y HTTPS.

---

# 10. Evento `ReservationCreated`

Crear el evento:

```bash
php artisan make:event ReservationCreated
```

Ejemplo:

```php
<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                "club.{$this->reservation->club_id}"
            ),

            new PrivateChannel(
                "club.{$this->reservation->club_id}.branch.{$this->reservation->branch_id}"
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reservation.created';
    }

    public function broadcastWith(): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'club_id' => $this->reservation->club_id,
            'branch_id' => $this->reservation->branch_id,
            'starts_at' => $this->reservation->starts_at,
            'ends_at' => $this->reservation->ends_at,
            'status' => $this->reservation->status,

            'court' => [
                'id' => $this->reservation->court?->id,
                'name' => $this->reservation->court?->name,
            ],

            'customer' => [
                'name' => $this->reservation->customer?->name,
            ],
        ];
    }
}
```

La reserva se publica en dos scopes:

```text
club.{clubId}
club.{clubId}.branch.{branchId}
```

Esto permite que:

- una membresía global reciba todas las reservas del club;
- una membresía de sucursal reciba únicamente las reservas de esa sucursal.

---

# 11. Disparar el evento

El evento debe dispararse después de que la reserva haya sido guardada correctamente.

Ejemplo:

```php
$reservation = $this->reservationRepository->save($reservation);

ReservationCreated::dispatch($reservation);

return $reservation;
```

Preferentemente debe dispararse desde la capa de aplicación o desde el caso de uso que crea la reserva.

No debería dispararse desde Vue.

---

# 12. Autorización de canales

Archivo:

```text
routes/channels.php
```

## 12.1 Canal global de club

Ejemplo simple:

```php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'club.{clubId}',
    function ($user, int $clubId) {

        return $user->memberships()
            ->where('club_id', $clubId)
            ->whereNull('branch_id')
            ->where('active', true)
            ->exists();
    }
);
```

---

## 12.2 Canal por sucursal

```php
Broadcast::channel(
    'club.{clubId}.branch.{branchId}',
    function ($user, int $clubId, int $branchId) {

        return $user->memberships()
            ->where('club_id', $clubId)
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->exists();
    }
);
```

---

# 13. Recomendación para el proyecto actual

Como el proyecto ya tiene lógica de memberships, repositories y permisos, evitar duplicar reglas en `channels.php`.

Conviene crear un servicio específico.

Ejemplo:

```text
Application/
└── Notifications/
    └── NotificationChannelResolver.php
```

Ejemplo conceptual:

```php
final class NotificationChannelResolver
{
    public function userCanAccessClub(
        int $userId,
        int $clubId
    ): bool {
        // usar repositorio de memberships
    }

    public function userCanAccessBranch(
        int $userId,
        int $clubId,
        int $branchId
    ): bool {
        // usar repositorio de memberships
    }
}
```

Y luego:

```php
Broadcast::channel(
    'club.{clubId}',
    function ($user, int $clubId) use ($resolver) {
        return $resolver->userCanAccessClub(
            $user->id,
            $clubId
        );
    }
);
```

---

# 14. Endpoint para obtener los canales del contexto activo

En lugar de hacer que Vue calcule los canales leyendo memberships, Laravel debería devolver los canales autorizados.

Endpoint sugerido:

```http
GET /api/me/notification-channels
```

O bien:

```http
GET /api/clubs/{clubId}/notification-channels
```

Respuesta para un usuario con acceso a branches 6 y 8:

```json
{
    "club_id": 2,
    "channels": [
        "club.2.branch.6",
        "club.2.branch.8"
    ]
}
```

Respuesta para membresía global:

```json
{
    "club_id": 1,
    "channels": [
        "club.1"
    ]
}
```

---

# 15. Resolver canales desde memberships

Ejemplo conceptual:

```php
public function resolveForClub(
    int $userId,
    int $clubId
): array {

    $memberships = $this->membershipRepository
        ->findActiveByUserAndClub(
            $userId,
            $clubId
        );

    $hasGlobalMembership = collect($memberships)
        ->contains(
            fn ($membership) =>
                $membership->branchId === null
        );

    if ($hasGlobalMembership) {
        return [
            "club.{$clubId}",
        ];
    }

    return collect($memberships)
        ->filter(
            fn ($membership) =>
                $membership->branchId !== null
        )
        ->map(
            fn ($membership) =>
                "club.{$clubId}.branch.{$membership->branchId}"
        )
        ->unique()
        ->values()
        ->all();
}
```

---

# 16. Regla importante si existe membresía global

Si el usuario tiene:

```text
club_id = 2
branch_id = NULL
```

y además tiene memberships específicas:

```text
club_id = 2
branch_id = 6

club_id = 2
branch_id = 8
```

debe suscribirse solamente a:

```text
club.2
```

No es necesario suscribirlo también a:

```text
club.2.branch.6
club.2.branch.8
```

porque recibiría eventos duplicados.

---

# 17. Vue - instalar dependencias

```bash
npm install laravel-echo pusher-js
```

---

# 18. Configuración de Laravel Echo

Ejemplo:

```javascript
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

window.Echo = new Echo({
    broadcaster: 'reverb',

    key: import.meta.env.VITE_REVERB_APP_KEY,

    wsHost: import.meta.env.VITE_REVERB_HOST,

    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,

    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,

    forceTLS:
        (import.meta.env.VITE_REVERB_SCHEME ?? 'https')
        === 'https',

    enabledTransports: ['ws', 'wss'],
})
```

---

# 19. Variables del frontend

```env
VITE_REVERB_APP_KEY=local-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

---

# 20. Store de notificaciones

Se recomienda centralizar las suscripciones.

Ejemplo:

```text
stores/
└── notificationStore.js
```

Responsabilidades:

```text
connectForContext()
disconnectCurrentChannels()
handleReservationCreated()
addNotification()
markAsRead()
```

---

# 21. Suscripción a canales

Ejemplo conceptual:

```javascript
const activeChannels = []

async function connectForClub(clubId) {

    disconnectCurrentChannels()

    const response = await api.get(
        `/clubs/${clubId}/notification-channels`
    )

    const channels = response.data.channels

    channels.forEach(channelName => {

        Echo.private(channelName)
            .listen(
                '.reservation.created',
                handleReservationCreated
            )

        activeChannels.push(channelName)
    })
}
```

---

# 22. Desconectar canales anteriores

Cuando el usuario cambia de club o contexto, debe abandonar los canales anteriores.

```javascript
function disconnectCurrentChannels() {

    activeChannels.forEach(channelName => {
        Echo.leave(channelName)
    })

    activeChannels.splice(0)
}
```

Esto evita recibir notificaciones del club que ya no está visualizando.

---

# 23. Manejar una nueva reserva

Ejemplo:

```javascript
function handleReservationCreated(event) {

    notificationStore.add({
        type: 'reservation_created',

        title: 'Nueva reserva',

        message:
            `${event.customer?.name ?? 'Cliente'} - ${event.court?.name}`,

        data: event,

        read: false,
    })

    toast.success(
        `Nueva reserva en ${event.court?.name}`
    )
}
```

---

# 24. Notificación visual

Ejemplo:

```text
┌─────────────────────────────────────┐
│ Nueva reserva                       │
│                                     │
│ Juan Pérez                          │
│ Cancha 2                            │
│ 20:00 - 21:00                       │
│                                     │
│                         Ver reserva │
└─────────────────────────────────────┘
```

Idealmente debe aparecer como notificación flotante y desaparecer automáticamente.

---

# 25. Campana de notificaciones

Además del toast, el frontend puede mantener una campana:

```text
🔔 3
```

Ejemplo:

```text
Notificaciones

Nueva reserva
Juan Pérez - Cancha 2
Hace 1 minuto

Nueva reserva
Lucas Fernández - Cancha 1
Hace 8 minutos
```

Inicialmente puede mantenerse solo en memoria.

Más adelante se puede persistir en base de datos.

---

# 26. Persistencia de notificaciones

Fase posterior.

Tabla sugerida:

```text
notifications
```

Campos posibles:

```text
id
user_id
club_id
branch_id
type
title
message
data
read_at
created_at
updated_at
```

Esto permitiría:

- mantener notificaciones después de recargar;
- mostrar historial;
- marcar como leída;
- contar no leídas;
- recuperar notificaciones antiguas.

---

# 27. Diferenciar canal de evento

No crear canales como:

```text
club.1.reservations
club.1.payments
club.1.customers
```

salvo que exista una necesidad fuerte.

Se recomienda mantener el scope en el canal:

```text
club.1
club.1.branch.8
```

Y distinguir el tipo mediante eventos:

```text
.reservation.created
.reservation.cancelled
.reservation.confirmed

.payment.created
.payment.approved
.payment.failed

.customer.created

.fixed_reservation.conflict
```

Esto simplifica las suscripciones.

---

# 28. Permisos y roles

El acceso al canal y el acceso al evento son conceptos distintos.

Ejemplo:

```text
Admin
Manager
Employee
```

Los tres podrían escuchar:

```text
reservation.created
```

Pero únicamente Admin y Manager podrían escuchar:

```text
payment.approved
```

Si fuera necesario restringir eventos por rol o permiso, esa lógica debe resolverse en backend.

No confiar en:

```javascript
if (role === 'admin') {
    mostrarNotificacion()
}
```

Eso solo sería una restricción visual.

---

# 29. Seguridad

Nunca permitir que el frontend decida por sí solo qué canales están autorizados.

Aunque un usuario ejecute manualmente:

```javascript
Echo.private('club.4.branch.16')
```

Laravel debe validar que:

```text
user_id correcto
club_id correcto
branch_id correcto
membership activa
```

Si no cumple:

```text
403 Forbidden
```

---

# 30. Sanctum

Si Vue y Laravel están separados y se utiliza Sanctum, asegurarse de que la autenticación para broadcasting use el mismo mecanismo autorizado del sistema.

La ruta de autorización de broadcasting debe requerir autenticación.

Ejemplo conceptual:

```php
Broadcast::routes([
    'middleware' => ['auth:sanctum'],
]);
```

La configuración exacta dependerá de si el sistema usa cookies SPA o Bearer Token.

---

# 31. Caso concreto del usuario de ejemplo

Datos:

```text
Membership 1
club_id = 1
branch_id = NULL
active = 1

Membership 2
club_id = 2
branch_id = 6
active = 1

Membership 3
club_id = 2
branch_id = 8
active = 1

Membership 4
club_id = 3
branch_id = 13
active = 1

Membership 5
club_id = 4
branch_id = 16
active = 0
```

Resultados:

### Contexto Club 1

```text
Canales:

club.1
```

### Contexto Club 2

```text
Canales:

club.2.branch.6
club.2.branch.8
```

### Contexto Club 3

```text
Canales:

club.3.branch.13
```

### Contexto Club 4

```text
Sin acceso

membership inactive
```

---

# 32. Ejemplo de nueva reserva

Se crea:

```text
reservation_id = 100
club_id = 2
branch_id = 8
court_id = 4
```

Laravel publica:

```text
club.2
club.2.branch.8
```

Reciben:

```text
Usuarios con membresía global del Club 2

o

Usuarios con membresía activa de Branch 8
```

No reciben:

```text
Usuarios que solamente tienen Branch 6
Usuarios sin membresía en Club 2
Usuarios con memberships inactivas
```

---

# 33. Evitar duplicados

Una reserva puede publicarse tanto en:

```text
club.2
```

como:

```text
club.2.branch.8
```

Por eso un usuario global debe estar suscripto solamente a:

```text
club.2
```

y no simultáneamente a ambos.

---

# 34. Tests backend mínimos

Crear Feature/Unit tests para:

```text
usuario global puede escuchar canal de club

usuario de branch puede escuchar su branch

usuario de branch no puede escuchar otra branch

membership inactiva no puede escuchar canal

usuario de otro club no puede escuchar canal

membresía global recibe evento de cualquier branch

membresía específica recibe solamente eventos de su branch

resolver no devuelve canales duplicados

si existe membresía global,
no devuelve canales específicos de branch
```

---

# 35. Tests del evento

Validar que:

```text
ReservationCreated
```

publique en:

```text
club.{clubId}
club.{clubId}.branch.{branchId}
```

y que el payload incluya únicamente información necesaria.

No enviar información sensible innecesaria.

---

# 36. Orden recomendado de implementación

## Fase 1 - Infraestructura

```text
1. Instalar Broadcasting
2. Configurar Reverb
3. Configurar Queue
4. Configurar Echo
```

## Fase 2 - Backend

```text
5. Crear ReservationCreated
6. Crear NotificationChannelResolver
7. Crear autorización de canales
8. Crear endpoint notification-channels
9. Disparar evento al crear reserva
```

## Fase 3 - Frontend

```text
10. Crear configuración Echo
11. Crear notificationStore
12. Suscribirse al contexto activo
13. Desconectarse al cambiar contexto
14. Mostrar toast
15. Agregar campana
```

## Fase 4 - Tests

```text
16. Tests de autorización
17. Tests del resolver
18. Tests del evento
19. Tests de integración
```

## Fase 5 - Persistencia

```text
20. Crear tabla notifications
21. Guardar notificaciones
22. Endpoint de listado
23. Endpoint marcar como leída
24. Contador de no leídas
```

---

# 37. Estructura sugerida

Backend:

```text
app/
├── Application/
│   └── Notifications/
│       ├── NotificationChannelResolver.php
│       └── GetNotificationChannels.php
│
├── Events/
│   └── ReservationCreated.php
│
└── Http/
    └── Controllers/
        └── Notifications/
            └── GetNotificationChannelsController.php

routes/
├── api.php
└── channels.php
```

Frontend:

```text
src/
├── realtime/
│   └── echo.js
│
├── stores/
│   └── notificationStore.js
│
├── components/
│   └── notifications/
│       ├── NotificationBell.vue
│       ├── NotificationList.vue
│       └── NotificationToast.vue
```

---

# 38. Regla final

La regla central de todo el módulo debe ser:

> Laravel publica el evento según el club y sucursal de la reserva.  
> Vue escucha únicamente los canales del contexto seleccionado.  
> Laravel autoriza cada canal utilizando las memberships reales y activas del usuario.

Con esto se logra que:

```text
el usuario vea solamente las notificaciones
que corresponden a su alcance real
```

sin depender de filtros inseguros en el frontend.

---

# 39. Extensión futura

Una vez implementada esta base, pueden agregarse fácilmente eventos como:

```text
ReservationCreated
ReservationConfirmed
ReservationCancelled

PaymentCreated
PaymentApproved
PaymentRejected

CustomerCreated

FixedReservationConflictDetected
```

sin modificar la arquitectura de canales.

La idea es reutilizar:

```text
club.{clubId}
club.{clubId}.branch.{branchId}
```

como scopes generales de comunicación en tiempo real.
