# Módulo de Reservas — Plan de Implementación

## Objetivo

Implementar un módulo de reservas de canchas que permita crear reservas desde tres contextos distintos:

- Personal del club o sucursal.
- Cliente autenticado.
- Cliente invitado sin autenticación.

El módulo debe integrarse con:

- Clubs.
- Branches.
- Courts.
- Memberships.
- Roles y permisos.
- Intervalos por tipo de cancha.
- Pricing.
- Promociones.
- PriceResolver.
- Disponibilidad.
- Futuro módulo de Payments.

La prioridad es construir primero una versión funcional y bien testeada. Después se agregará protección contra concurrencia y doble reserva.

---

# 1. Tipos de creación de reserva

## Personal del club

Un usuario administrativo autenticado puede crear una reserva para:

- Un cliente registrado.
- Un cliente sin cuenta.

Debe pasar por:

```text
auth:sanctum
+
Membership
+
Scope
+
Role
+
reservation.create
```

Ejemplo:

```text
Empleado de Branch 3
        ↓
POST /courts/5/reservations
        ↓
Court 5 pertenece a Branch 3
        ↓
Tiene scope + reservation.create
        ↓
Reserva permitida
```

---

## Cliente autenticado

Un usuario registrado puede reservar para sí mismo.

No necesita una Membership administrativa.

Conceptualmente:

```text
customer_user_id = auth()->id()
created_by_user_id = auth()->id()
```

El cliente no puede enviar otro `customer_user_id` para reservar en nombre de otra persona.

Ruta prevista:

```text
POST /courts/{court_id}/book
```

---

## Cliente invitado

Un usuario puede crear una reserva sin autenticarse.

Debe enviar datos de contacto:

```text
guest_name
guest_email
guest_phone
```

Conceptualmente:

```text
customer_user_id = NULL
created_by_user_id = NULL
```

Ruta prevista:

```text
POST /public/courts/{court_id}/book
```

Inicialmente estas reservas deberían crearse con estado:

```text
PENDING
```

Más adelante podrán confirmarse mediante pago, email, teléfono u otro mecanismo.

---

# 2. Tabla reservations

La tabla principal tendrá aproximadamente:

```text
reservations
├── id
├── court_id
├── customer_user_id nullable
├── created_by_user_id nullable
├── guest_name nullable
├── guest_email nullable
├── guest_phone nullable
├── starts_at
├── ends_at
├── total_price
├── status
├── public_token
├── notes nullable
├── cancelled_at nullable
├── created_at
└── updated_at
```

---

# 3. Diferencia entre customer_user_id y created_by_user_id

`customer_user_id` representa a quién pertenece la reserva.

`created_by_user_id` representa quién ejecutó la acción de crearla.

Ejemplos:

```text
Cliente registrado reserva para sí mismo

customer_user_id = 25
created_by_user_id = 25
```

```text
Empleado crea reserva para cliente registrado

customer_user_id = 25
created_by_user_id = 8
```

```text
Empleado crea reserva para invitado

customer_user_id = NULL
created_by_user_id = 8

guest_name = Juan Pérez
guest_phone = ...
```

```text
Invitado reserva desde endpoint público

customer_user_id = NULL
created_by_user_id = NULL

guest_name = Juan Pérez
guest_email = ...
```

---

# 4. Regla de identidad del cliente

Una reserva debe pertenecer a:

```text
cliente registrado
OR
cliente invitado
```

No debería existir una reserva donde no podamos determinar al cliente.

Inválido:

```text
customer_user_id = NULL
guest_name = NULL
```

También debe evitarse una situación ambigua donde se intente representar simultáneamente dos clientes distintos.

Esta validación se realizará en Application.

---

# 5. public_token

Las reservas tendrán un identificador público aleatorio:

```text
public_token
```

Se utilizará especialmente para invitados.

Más adelante permitirá:

```text
ver reserva
cancelar reserva
pagar reserva
confirmar reserva
```

sin exponer IDs secuenciales.

Debe ser único.

---

# 6. Estados de Reservation

Se creará:

```text
Domain/Reservations/Enums/ReservationStatus.php
```

Estados iniciales:

```php
enum ReservationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
}
```

## PENDING

Reserva creada pero todavía no confirmada.

Principalmente útil para:

- Clientes públicos.
- Futuro flujo de Payments.

## CONFIRMED

Reserva confirmada y válida.

## CANCELLED

Reserva cancelada.

No debe bloquear disponibilidad.

## COMPLETED

Reserva que ya fue realizada/finalizada.

---

# 7. Estado inicial según origen

Inicialmente:

```text
Personal del club
→ CONFIRMED
```

```text
Cliente autenticado
→ inicialmente definir entre PENDING/CONFIRMED según futuro flujo comercial
```

```text
Invitado
→ PENDING
```

Cuando se implemente Payments probablemente:

```text
PENDING
   ↓
pago aprobado
   ↓
CONFIRMED
```

---

# 8. Precio histórico

Una Reservation debe almacenar:

```text
total_price
```

El precio se calcula al momento de crear la reserva mediante:

```text
PriceResolver
```

y después queda congelado.

Ejemplo:

```text
Hoy
Promo = $18.000

Reserva creada
total_price = $18.000
```

Mañana:

```text
Promo cambia a $20.000
```

La reserva anterior sigue teniendo:

```text
total_price = $18.000
```

Nunca se recalculan reservas históricas utilizando precios actuales.

---

# 9. reservation_price_segments

Como PriceResolver puede dividir una reserva en varios precios, crearemos:

```text
reservation_price_segments
├── id
├── reservation_id
├── starts_at
├── ends_at
├── hourly_price
├── subtotal
├── court_price_rule_id nullable
├── rule_name nullable
├── created_at
└── updated_at
```

Ejemplo:

```text
Reserva
17:00 → 19:00

Precio base
$25.000 / 60 min

Promo
14:00 → 18:00
$18.000 / 60 min
```

Resultado:

```text
Segmento 1
17:00 → 18:00
$18.000/h
subtotal = $18.000
rule = Happy Hour

Segmento 2
18:00 → 19:00
$25.000/h
subtotal = $25.000
rule = NULL

TOTAL = $43.000
```

Esto permite auditar cómo se calculó una reserva incluso si posteriormente las promociones cambian.

---

# 10. Intervalos de reserva

Ya existe:

```text
interval_time_tipo_court
```

El intervalo depende de:

```text
Branch + TipoCourt
```

Ejemplo:

```text
Palermo + Pádel
interval_minutes = 60
```

Las reservas deben respetar los múltiplos permitidos.

Ejemplo con 60 minutos:

```text
14:00 → 15:00 ✅
14:00 → 16:00 ✅
14:00 → 17:00 ✅

14:00 → 15:30 ❌
```

Ejemplo con 90 minutos:

```text
14:00 → 15:30 ✅
14:00 → 17:00 ✅

14:00 → 15:00 ❌
```

---

# 11. Horarios de Branch

Una reserva debe estar completamente dentro del horario permitido de la sucursal.

Ejemplo:

```text
Branch
08:00 → 23:00
```

Válidas:

```text
08:00 → 09:00
15:00 → 17:00
22:00 → 23:00
```

Inválidas:

```text
07:00 → 08:00
22:30 → 23:30
```

---

# 12. Disponibilidad

Una cancha no puede tener dos reservas vigentes superpuestas.

La condición de solapamiento será:

```text
existing.starts_at < new.ends_at
AND
existing.ends_at > new.starts_at
```

Ejemplo:

```text
Reserva existente
14:00 → 16:00
```

Permitidas:

```text
13:00 → 14:00 ✅
16:00 → 17:00 ✅
```

No permitidas:

```text
13:30 → 14:30 ❌
14:00 → 15:00 ❌
15:00 → 17:00 ❌
13:00 → 17:00 ❌
```

---

# 13. Estados que bloquean disponibilidad

Inicialmente bloquean una Court:

```text
PENDING
CONFIRMED
```

No bloquea:

```text
CANCELLED
```

`COMPLETED` representa una reserva histórica y naturalmente corresponde a un horario pasado.

---

# 14. CreateReservationHandler

El flujo previsto:

```text
CreateReservationCommand
        ↓
buscar Court
        ↓
validar que exista
        ↓
validar Court activa
        ↓
buscar Branch
        ↓
validar Branch activa
        ↓
validar identidad del cliente
        ↓
validar startsAt < endsAt
        ↓
validar horario Branch
        ↓
obtener interval_minutes
        ↓
validar duración
        ↓
validar disponibilidad
        ↓
PriceResolver
        ↓
ReservationPrice
├── total
└── segments
        ↓
TRANSACTION
├── crear Reservation
└── guardar PriceSegments
        ↓
Reservation creada
```

---

# 15. Domain

Crear:

```text
app/Domain/Reservations/
├── Entities/
│   ├── Reservation.php
│   └── ReservationPriceSegment.php
│
├── Enums/
│   └── ReservationStatus.php
│
├── Repositories/
│   └── ReservationRepository.php
│
└── Exceptions/
    ├── ReservationNotFoundException.php
    ├── CourtNotAvailableException.php
    ├── InvalidReservationTimeException.php
    ├── ReservationOutsideBranchHoursException.php
    └── InvalidReservationDurationException.php
```

---

# 16. Comportamiento de Reservation

La Entity debería ser responsable de operaciones como:

```text
cancel()
confirm()
complete()
```

No queremos modificar `status` libremente desde Application.

Ejemplo:

```php
$reservation->cancel();
```

y que la propia entidad determine si la transición es válida.

---

# 17. Application

Primera estructura:

```text
app/Application/Reservations/
├── Create/
├── Show/
├── Collection/
├── Cancel/
└── Availability/
```

Inicialmente no se implementará Update de horario.

Para cambiar una reserva:

```text
cancelar reserva
+
crear una nueva
```

Esto evita introducir demasiadas reglas complejas desde la primera versión.

---

# 18. Availability

Se implementará un caso de uso para consultar disponibilidad.

Ruta prevista:

```text
GET /courts/{court_id}/availability?date=2026-09-10
```

Respuesta aproximada:

```json
{
    "court_id": 3,
    "date": "2026-09-10",
    "interval_minutes": 60,
    "slots": [
        {
            "starts_at": "08:00",
            "ends_at": "09:00",
            "available": true
        },
        {
            "starts_at": "09:00",
            "ends_at": "10:00",
            "available": false
        }
    ]
}
```

Más adelante puede agregarse:

```text
price
```

a cada slot utilizando PriceResolver.

---

# 19. Rutas administrativas

Para personal del club:

```text
POST  /courts/{court_id}/reservations
GET   /courts/{court_id}/reservations
GET   /reservations/{id}
PATCH /reservations/{id}/cancel
```

Permisos:

```text
reservation.create
reservation.view
reservation.cancel
```

La colección seguirá nuestra convención:

```text
reservation.collection
```

pero NO se almacenará como Permission.

---

# 20. Autorización administrativa

El scope seguirá la arquitectura actual:

```text
Reservation
     ↓
Court
     ↓
Branch
     ↓
Club
     ↓
Membership
     ↓
Role
     ↓
Permission
```

CheckPermission deberá agregar:

```text
resolveReservationScope()
```

Para colecciones:

```text
reservation.collection
```

se validará scope, igual que hacemos con Courts, Pricing y Promotions.

---

# 21. Rutas para cliente autenticado

Ruta prevista:

```text
POST /courts/{court_id}/book
```

El cliente reserva exclusivamente para sí mismo.

Internamente:

```text
customer_user_id = auth()->id()
created_by_user_id = auth()->id()
```

No se utiliza el permiso administrativo:

```text
reservation.create
```

---

# 22. Rutas públicas

Para invitados:

```text
POST /public/courts/{court_id}/book
```

Datos:

```json
{
    "name": "Juan Pérez",
    "email": "juan@gmail.com",
    "phone": "1122334455",
    "starts_at": "2026-09-10 17:00:00",
    "ends_at": "2026-09-10 19:00:00"
}
```

Internamente:

```text
customer_user_id = NULL
created_by_user_id = NULL
```

---

# 23. Seguridad para invitados

No se implementará todo inicialmente, pero el diseño debe contemplar:

```text
rate limiting
confirmación email/teléfono
captcha si fuera necesario
expiración de reservas PENDING
public_token
```

Nunca se permitirá administrar una reserva pública solamente con un ID secuencial.

---

# 24. Concurrencia

La primera versión implementará:

```text
check disponibilidad
↓
crear reserva
```

Después se estudiará el problema:

```text
Request A
verifica disponibilidad → libre

Request B
verifica disponibilidad → libre

A crea
B crea

DOBLE RESERVA
```

La segunda etapa agregará:

```text
DB::transaction()
lockForUpdate()
índices
manejo de deadlocks
retries si corresponde
```

La concurrencia se implementará después de tener la versión básica cubierta por tests.

---

# 25. Índices iniciales

Reservas serán consultadas frecuentemente por:

```text
court_id
starts_at
ends_at
status
```

Índice inicial candidato:

```php
$table->index([
    'court_id',
    'starts_at',
    'ends_at',
]);
```

También:

```php
$table->index([
    'customer_user_id',
    'starts_at',
]);
```

Los índices adicionales se evaluarán posteriormente con:

```text
EXPLAIN
```

No se agregarán índices sin medir las consultas reales.

---

# 26. Testing

Se crearán Unit Tests para:

```text
Reservation Entity
ReservationStatus
reglas temporales
transiciones de status
detección de overlap
```

Feature Tests para:

```text
personal crea reserva
cliente autenticado reserva para sí mismo
invitado crea reserva
Court fuera de scope → 403
Court ocupada → conflicto
duración inválida
fuera del horario de Branch
precio histórico correcto
segmentos históricos correctos
cancelación libera disponibilidad
```

Después se agregarán tests específicos de concurrencia.

---

# 27. Orden de implementación

## Etapa 1

```text
Migration reservations
Migration reservation_price_segments
ReservationStatus
```

## Etapa 2

```text
Reservation Entity
ReservationPriceSegment Entity
Exceptions
ReservationRepository
```

## Etapa 3

```text
Eloquent Models
EloquentReservationRepository
Bindings
```

## Etapa 4

```text
Validaciones temporales
Intervalos
Horarios Branch
Overlap
Availability
```

## Etapa 5

```text
CreateReservationCommand
CreateReservationHandler
PriceResolver
Persistencia de PriceSegments
Transaction
```

## Etapa 6

```text
Show
Collection
Cancel
Availability
```

## Etapa 7

```text
Controllers
Requests
Routes
Permissions
CheckPermission
```

## Etapa 8

```text
Factories
Unit Tests
Feature Tests
```

## Etapa 9

```text
Race conditions
Transactions
lockForUpdate
Deadlocks
Retries
Índices
EXPLAIN
```

## Etapa 10

```text
Payments
Confirmación de reservas públicas
Expiración de PENDING
```

---

# Próximo paso

Comenzar creando:

```text
1. migration reservations
2. migration reservation_price_segments
3. ReservationStatus.php
```

Después continuar con las entidades de Domain antes de implementar Application o Controllers.
