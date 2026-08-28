# Estado del proyecto — Sistema de gestión de clubes

> Documento de continuidad para retomar el proyecto en futuras conversaciones.
> Actualizado: **27/08/2026**.
> Fuente de esta actualización: estado real del proyecto entregado en `sistema-master(4).zip`.

---

## 1. Objetivo del proyecto

Proyecto Laravel usado como práctica de backend avanzado.

La idea no es solamente construir CRUDs, sino profundizar en:

- PHP moderno.
- Laravel internamente.
- DDD / Clean Architecture de forma pragmática.
- Separación `Domain`, `Application`, `Infrastructure` y `Http`.
- Repository Pattern.
- Commands / Queries / Handlers.
- DTOs.
- Excepciones de dominio.
- Autenticación con Laravel Sanctum.
- Autorización por roles, permisos, memberships y scope.
- Pricing y promociones.
- Reservas.
- Historial de precios.
- Transacciones.
- `lockForUpdate()`.
- Race conditions y concurrencia.
- Jobs y Scheduler.
- Feature Tests y Unit Tests.
- Más adelante: Payments, webhooks, idempotencia, índices y `EXPLAIN`.

El proyecto actualmente usa:

```text
PHP ^8.3
Laravel ^13.17
Laravel Sanctum ^4.0
Pest/PHPUnit mediante php artisan test
```

---

# 2. Arquitectura actual

La estructura principal sigue este criterio:

```text
app/
├── Domain/
│   ├── Users/
│   ├── Clubs/
│   ├── Branches/
│   ├── Memberships/
│   ├── Roles/
│   ├── Permissions/
│   ├── Courts/
│   ├── Pricing/
│   ├── Reservations/
│   ├── Payments/
│   └── Products/
│
├── Application/
│   ├── Auth/
│   ├── Authorization/
│   ├── Clubs/
│   ├── Branches/
│   ├── Memberships/
│   ├── Roles/
│   ├── Courts/
│   ├── Pricing/
│   └── Reservations/
│
├── Infrastructure/
│   ├── Auth/
│   └── Persistence/
│
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
│
├── Jobs/
├── Models/
└── Shared/
```

## Domain

Contiene reglas y conceptos del negocio:

- Entities.
- Repository interfaces.
- Enums.
- Domain Exceptions.
- Value Objects cuando corresponde.

Regla importante:

```text
Domain NO debe depender de Eloquent ni de HTTP.
```

## Application

Contiene los casos de uso.

Patrón principal:

```text
Command / Query
        ↓
Handler
        ↓
Repository interfaces / Domain Services
        ↓
DTO
```

Ejemplos actuales:

```text
Application/Clubs/Store
Application/Branches/Update
Application/Courts/Store
Application/Pricing/Resolver
Application/Reservations/Create
Application/Reservations/Availability
```

## Infrastructure

Implementa los contratos del Domain.

Ejemplo:

```text
Domain\Reservations\Repositories\ReservationRepository
                        ↑
Infrastructure\Persistence\EloquentReservationRepository
```

## HTTP

Los Controllers:

1. reciben la request;
2. validan mediante `FormRequest` o `$request->validate()`;
3. crean Commands/Queries;
4. ejecutan Handlers;
5. devuelven DTOs mediante `ApiResponse`.

No concentrar reglas de negocio importantes en Controllers.

---

# 3. Bindings del Service Container

`App\Providers\AppServiceProvider` registra actualmente:

```text
UserRepository                     → EloquentUserRepository
TokenGenerator                     → SanctumTokenGenerator
PasswordHasher                     → LaravelPasswordHasher
ClubRepository                     → EloquentClubRepository
BranchRepository                   → EloquentBranchRepository
RoleRepository                     → EloquentRoleRepository
MembershipRepository               → EloquentMembershipRepository
PermissionRepository               → EloquentPermissionRepository
CourtRepository                    → EloquentCourtRepository
TipoCourtRepository                → EloquentTipoCourtRepository
CourtPriceRepository               → EloquentCourtPriceRepository
ReservationRepository              → EloquentReservationRepository
IntervalTimeTipoCourtRepository    → EloquentIntervalTimeTipoCourtRepository
```

---

# 4. Respuesta estándar de API

Existe:

```text
App\Shared\Http\Responses\ApiResponse
```

Los Controllers devuelven respuestas con estructura conceptual:

```json
{
    "status": true,
    "message": "...",
    "data": {},
    "code": 200
}
```

Las excepciones de dominio se manejan globalmente para evitar repetir `try/catch` en cada Controller.

---

# 5. Autenticación

Está implementada con Laravel Sanctum.

Rutas públicas:

```text
POST /api/auth/login
POST /api/auth/register
```

Ruta autenticada:

```text
POST /api/auth/logout
```

También existe:

```text
GET /api/user
```

para consultar el perfil del usuario autenticado.

Tests existentes:

```text
tests/Feature/Auth/RegisterTest.php
tests/Feature/Auth/LoginTest.php
tests/Feature/Auth/LogoutTest.php
```

Para testear expiración de tokens Sanctum se configuró explícitamente la expiración en tests y se manipula `created_at` del token.

---

# 6. Clubs y Branches

Jerarquía:

```text
Club
└── Branch
```

## Clubs

Casos de uso implementados:

```text
Collection
Show
Create
Update
Deactivate
```

Rutas:

```text
GET    /api/clubs
GET    /api/clubs/{id}
POST   /api/clubs
PUT    /api/clubs/{id}
DELETE /api/clubs/{id}
```

`POST /clubs` es un caso especial y actualmente usa:

```php
->withoutMiddleware('permission')
```

porque al crear el primer Club todavía no existe una Membership de ese Club para autorizar contra ella.

## Branches

Casos de uso:

```text
Collection por Club
Show
Create
Update
Deactivate
```

Rutas principales:

```text
GET    /api/clubs/{club_id}/branches
POST   /api/clubs/{club_id}/branches
GET    /api/branches/{id}
PUT    /api/branches/{id}
DELETE /api/branches/{id}
```

Branches tienen:

```text
opening_time
closing_time
active
```

Actualmente no se soportan reservas que crucen medianoche.

---

# 7. Memberships

`Membership` es la base del scope de autorización.

Campos conceptuales:

```text
user_id
club_id
rol_id
branch_id nullable
active
```

IMPORTANTE:

```text
La FK se llama rol_id, NO role_id.
```

## Membership global

```text
branch_id = NULL
```

Concede el Role para todas las Branches del Club.

## Membership específica de Branch

```text
branch_id = X
```

Concede el Role solamente para esa Branch.

Un usuario puede tener memberships diferentes en varias Branches del mismo Club.

Ejemplo:

```text
Club A
├── Branch 2 → Manager
└── Branch 5 → Employee
```

## Conflictos

Reglas implementadas:

```text
GLOBAL + cualquier branch específica del mismo User/Club = conflicto

branch específica + GLOBAL del mismo User/Club = conflicto

branch específica + misma branch = conflicto

branch específica + otra branch distinta = permitido
```

Método importante:

```php
hasConflictingMembership(...)
```

## Repository

Métodos relevantes:

```php
findById(...)
findForUserAndClub(...)
hasConflictingMembership(...)
findActiveForScope(...)
findActiveForClub(...)
hasActiveMemberships(...)
```

`findActiveForScope()`:

```text
branchId = NULL
→ busca membership global

branchId concreta
→ acepta membership global O membership exacta de esa branch
```

`findActiveForClub()` devuelve:

```php
array
```

NO `?Membership`.

---

# 8. Roles y Permissions

Roles seed iniciales:

```text
1 SuperAdmin
2 Admin
3 Manager
4 Employee
```

Relación:

```text
roles
permissions
permission_role
```

La FK de Role en `permission_role` usa:

```text
rol_id
```

## Convención central

Los nombres de permisos coinciden con los nombres de rutas administrativas.

Ejemplos:

```text
club.view
branch.update
court.create
court_price.update
court_promotion.create
reservation.cancel
```

`CheckPermission` obtiene:

```php
$request->route()?->getName()
```

y utiliza ese nombre como permiso requerido.

## Permisos actuales

```text
club.view
club.create
club.update
club.deactivate

branch.view
branch.create
branch.update
branch.deactivate

membership.create
membership.change_status
membership.change_role
membership.change_branch

court.view
court.create
court.update
court.deactivate

court_price.view
court_price.create
court_price.update
court_price.change_status

court_promotion.view
court_promotion.create
court_promotion.update
court_promotion.change_status

reservation.create
reservation.view
reservation.cancel
reservation.confirm
```

## Roles actuales

### SuperAdmin

Recibe todos los permisos existentes (`*`).

### Admin

Administración amplia de Club/Branches/Memberships/Courts/Pricing/Promotions/Reservations.

### Manager

Puede operar Courts, Pricing, Promotions y Reservations, con menos acciones destructivas que Admin.

### Employee

Principalmente lectura + operación básica.

Actualmente tiene:

```text
club.view
branch.view
court.view
court_price.view
court_promotion.view
reservation.create
reservation.view
```

No tiene por defecto:

```text
reservation.cancel
reservation.confirm
```

---

# 9. Convención especial `.collection`

Las rutas con nombre terminado en:

```text
.collection
```

NO se almacenan como permisos individuales en la tabla `permissions`.

Ejemplos:

```text
club.collection
branch.collection
court.collection
court_price.collection
court_promotion.collection
reservation.collection
```

`CheckPermission` detecta:

```php
str_ends_with($routeName, '.collection')
```

y aplica autorización de scope especial.

Esto separa:

```text
autorización
≠
filtrado de resultados
```

Una colección autorizada igualmente debe filtrar lo que el usuario puede ver.

---

# 10. AuthorizationService

Archivo:

```text
App\Application\Authorization\AuthorizationService
```

Métodos principales:

```php
can(...)
authorize(...)
canInClub(...)
authorizeInClub(...)
```

## `can()`

Usa:

```text
MembershipRepository::findActiveForScope()
```

para validar un recurso dentro de un Club/Branch concreto.

## `canInClub()`

Usa:

```text
MembershipRepository::findActiveForClub()
```

Recorre todas las memberships activas del usuario dentro del Club y alcanza con que una tenga un Role con el permiso requerido.

## Caso especial `club.view`

Una membership específica de Branch puede ver la información general del Club padre.

Por eso `club.view` usa:

```text
authorizeInClub()
```

No implica que ese usuario pueda ejecutar:

```text
club.update
club.deactivate
```

---

# 11. CheckPermission

Middleware:

```text
App\Http\Middleware\CheckPermission
```

Actualmente soporta resolvers para:

```text
club
branch
membership
court
court_price
court_promotion
reservation
```

También soporta collections para:

```text
club.collection
branch.collection
court.collection
court_price.collection
court_promotion.collection
reservation.collection
```

Para Reservation, el scope se resuelve conceptualmente:

```text
Reservation
→ Court
→ Branch
→ Club
→ Membership
→ Role
→ Permission
```

La autorización de `reservation.collection` valida scope pero no requiere que exista un permiso llamado `reservation.collection` en DB.

---

# 12. Courts / Canchas

Jerarquía:

```text
Club
└── Branch
    ├── Court
    │   └── TipoCourt
    │
    └── IntervalTimeTipoCourt
        └── TipoCourt
```

Court contiene actualmente:

```text
id
branch_id
tipo_court_id
name
active
```

Casos de uso:

```text
Collection por Branch
Show
Create
Update
Deactivate
```

Rutas:

```text
GET    /api/branches/{branch_id}/courts
POST   /api/branches/{branch_id}/courts
GET    /api/courts/{id}
PUT    /api/courts/{id}
DELETE /api/courts/{id}
```

`CourtRepository` incluye actualmente:

```php
findById(int $id): ?Court
findByBranchId(int $branchId): array
save(Court $court): Court
update(Court $court): Court
findActiveByBranchAndTipo(int $branchId, int $tipoCourtId): array
findByIdForUpdate(int $id): ?Court
```

`findByIdForUpdate()` usa Eloquent:

```php
->lockForUpdate()
```

Esto es usado por creación de reservas para evitar dobles reservas concurrentes sobre la misma cancha.

---

# 13. Intervalos por tipo de cancha

Tabla:

```text
interval_time_tipo_court
```

Clave conceptual:

```text
branch_id + tipo_court_id
```

Valor:

```text
interval_minutes
```

Repository:

```php
findIntervalMinutes(
    int $branchId,
    int $tipoCourtId
): ?int;
```

El intervalo controla la grilla posible de inicio de una reserva.

Ejemplo:

```text
opening_time = 08:00
interval_minutes = 30

inicios válidos:
08:00
08:30
09:00
09:30
...
```

---

# 14. Pricing

Pricing ya está implementado.

Tablas principales:

```text
court_prices
court_price_rules
```

## CourtPrice

Precio base por:

```text
branch_id + tipo_court_id
```

Campos principales:

```text
branch_id
tipo_court_id
price
active
```

El precio representa una tarifa por **60 minutos**.

## CourtPriceRule / promociones

Campos:

```text
court_price_id
name
price
day_of_week nullable
specific_date nullable
start_time nullable
end_time nullable
priority
starts_at nullable
ends_at nullable
active
```

Permite:

- promoción por día de semana;
- fecha específica;
- rango horario;
- ventana de vigencia;
- prioridad;
- activación/desactivación.

Cuando coinciden varias promociones gana la de mayor:

```text
priority
```

El final del rango horario se trata como límite exclusivo para resolver la tarifa en ese instante.

---

# 15. PriceResolver

Archivo:

```text
App\Application\Pricing\Resolver\PriceResolver
```

Flujo actual:

```text
buscar precio base activo
↓
buscar reglas/promociones activas
↓
recorrer la reserva minuto a minuto
↓
resolver qué regla aplica en cada minuto
↓
elegir la de mayor prioridad
↓
agrupar minutos consecutivos con misma tarifa/regla
↓
calcular subtotal proporcional
↓
devolver total + segmentos
```

El cálculo evita usar `float` directamente para dinero.

Convierte a centavos y luego vuelve a string decimal.

Ejemplo:

```text
$18.000/hora × 30 minutos / 60
= $9.000
```

Esto hace que reservas de 90, 120 minutos, etc. se cobren proporcionalmente y puedan cruzar promociones.

## Resultado

`ReservationPrice` contiene:

```text
total
segments[]
```

Cada `PriceSegment` contiene:

```text
startsAt
endsAt
hourlyPrice
subtotal
ruleId nullable
ruleName nullable
```

---

# 16. Rutas de Pricing

Por Branch:

```text
GET  /api/branches/{branch_id}/prices
POST /api/branches/{branch_id}/prices
```

Individuales:

```text
GET   /api/court_prices/{id}
PUT   /api/court_prices/{id}
PATCH /api/court_prices/{id}/status
```

Promociones:

```text
GET  /api/court_prices/{court_price_id}/promotions
POST /api/court_prices/{court_price_id}/promotions

GET   /api/court_promotions/{id}
PUT   /api/court_promotions/{id}
PATCH /api/court_promotions/{id}/status
```

---

# 17. Reservations — estado actual

Reservations ya está implementado y es uno de los módulos más avanzados del proyecto.

Tablas:

```text
reservations
reservation_price_segments
```

## `reservations`

Campos:

```text
id
court_id
customer_user_id nullable
created_by_user_id nullable
guest_name nullable
guest_email nullable
guest_phone nullable
starts_at
ends_at
total_price
status
public_token UUID unique
notes nullable
cancelled_at nullable
expires_at nullable
created_at
updated_at
```

Índices actuales:

```text
(court_id, starts_at, ends_at)
(customer_user_id, starts_at)
```

## `reservation_price_segments`

Snapshot histórico del precio aplicado.

Campos:

```text
reservation_id
starts_at
ends_at
hourly_price
subtotal
court_price_rule_id nullable
rule_name nullable
```

Objetivo:

```text
Cambiar un precio/promoción en el futuro
NO modifica el precio histórico de una reserva ya creada.
```

---

# 18. Identidad del cliente en Reservation

Una Reservation puede pertenecer a:

## Cliente registrado

```text
customer_user_id != NULL
guest_* = NULL
```

## Invitado

```text
customer_user_id = NULL
guest_name obligatorio
email o phone exigido por GuestReservationRequest
```

La Entity impide mezclar simultáneamente:

```text
cliente registrado + datos de guest
```

`created_by_user_id` representa quién ejecutó la creación.

Ejemplos:

```text
cliente reserva para sí mismo:
customer_user_id = 25
created_by_user_id = 25

personal crea para cliente:
customer_user_id = 25
created_by_user_id = ID del empleado

reserva pública guest:
customer_user_id = NULL
created_by_user_id = NULL
```

---

# 19. Estados de Reservation

Enum actual:

```text
PENDING
CONFIRMED
CANCELLED
COMPLETED
EXPIRED
```

Transiciones principales en la Entity:

```text
PENDING → CONFIRMED
PENDING → EXPIRED
PENDING/CONFIRMED → CANCELLED
CONFIRMED → COMPLETED
```

Reglas importantes:

- una reserva no puede confirmarse si ya expiró;
- solamente una `PENDING` puede confirmarse;
- solamente una `PENDING` puede expirar;
- una `COMPLETED` no puede cancelarse;
- volver a cancelar una `CANCELLED` lanza excepción.

---

# 20. Qué reservas bloquean disponibilidad

Semántica actual del repository:

```text
CONFIRMED
→ bloquea siempre

PENDING con expires_at > now()
→ bloquea

PENDING vencida
→ NO bloquea, incluso antes de que corra el Job

CANCELLED
COMPLETED
EXPIRED
→ no bloquean
```

Condición de overlap:

```text
existing.starts_at < requested.ends_at
AND
existing.ends_at > requested.starts_at
```

Esto permite adyacencia:

```text
14:00 → 15:00
15:00 → 16:00
```

sin considerarlas superpuestas.

---

# 21. ReservationValidator

Archivo:

```text
App\Application\Reservations\Validation\ReservationValidator
```

Valida actualmente:

1. `ends_at > starts_at`.
2. la reserva debe ser futura.
3. no puede cruzar de día.
4. debe estar completamente dentro de `opening_time` / `closing_time`.
5. debe existir `interval_minutes` para Branch + TipoCourt.
6. el inicio debe estar alineado con la grilla del intervalo desde la apertura.
7. duración mínima de **60 minutos**.
8. duración múltiplo de `interval_minutes`.
9. no puede existir overlap con una reserva que bloquee.

Ejemplo con intervalo de 30:

```text
30 min   ❌
60 min   ✅
90 min   ✅
120 min  ✅
```

Ejemplo con intervalo de 60:

```text
60 min   ✅
90 min   ❌
120 min  ✅
```

IMPORTANTE: actualmente el sistema decidió mantener la duración como múltiplo del intervalo.

---

# 22. Creación de Reservation y concurrencia

Caso de uso:

```text
App\Application\Reservations\Create\CreateReservationHandler
```

Flujo actual:

```text
DB::transaction(attempts: 3)
↓
CourtRepository::findByIdForUpdate()
↓
SELECT ... FOR UPDATE sobre Court
↓
validar Court activa
↓
buscar y validar Branch
↓
ReservationValidator dentro del lock
↓
PriceResolver
↓
determinar PENDING / CONFIRMED
↓
crear Reservation
↓
persistir Reservation
↓
persistir snapshot reservation_price_segments
↓
COMMIT
↓
se libera lock
```

La validación de overlap ocurre **después** de tomar el lock.

Objetivo:

```text
Request A y Request B no pueden crear simultáneamente
la misma franja de la misma Court viendo ambos disponibilidad libre.
```

Laravel usa:

```php
DB::transaction(..., attempts: 3)
```

para poder reintentar ciertos deadlocks.

SQLite de tests no reproduce realmente el locking de producción; la protección real está pensada para MySQL/PostgreSQL.

---

# 23. PENDING y expiración

Una reserva creada como `PENDING` recibe actualmente:

```text
expires_at = now() + 15 minutos
```

Una reserva creada `CONFIRMED` recibe:

```text
expires_at = NULL
```

Job:

```text
App\Jobs\ExpirePendingReservationsJob
```

Busca reservas `PENDING` vencidas y ejecuta:

```php
$reservation->expire();
```

Scheduler:

```php
Schedule::job(
    new ExpirePendingReservationsJob()
)->everyMinute();
```

ubicado en:

```text
routes/console.php
```

En producción debe existir un runner de Scheduler, por ejemplo:

```text
php artisan schedule:run
```

por cron cada minuto, o `schedule:work` según el entorno.

IMPORTANTE: la disponibilidad no depende de esperar al Job; un `PENDING` vencido deja de bloquear en la query de overlap inmediatamente.

---

# 24. Tipos de creación de Reservation

Existen tres flujos.

## A. Personal del club

Ruta:

```text
POST /api/courts/{court_id}/reservations
```

Protección:

```text
auth:sanctum
+
permission middleware
+
reservation.create
+
scope de Court
```

Request:

```text
customer_user_id opcional
guest_name/email/phone opcionales según modalidad
starts_at
ends_at
notes
confirmed boolean opcional
```

El personal puede elegir crear:

```text
PENDING
CONFIRMED
```

mediante `confirmed`.

## B. Cliente autenticado

Ruta:

```text
POST /api/courts/{court_id}/book
```

No pasa por permiso administrativo.

Reserva solamente para sí mismo:

```text
customer_user_id = auth()->id()
created_by_user_id = auth()->id()
confirmed = false
```

`confirmed` está prohibido en el Request.

La reserva nace:

```text
PENDING
```

## C. Guest público

Ruta:

```text
POST /api/public/courts/{court_id}/book
```

No requiere autenticación.

Requiere:

```text
name
email o phone al menos uno
starts_at
ends_at
notes opcional
```

`confirmed` está prohibido.

La reserva nace:

```text
PENDING
```

Además se genera:

```text
public_token UUID
```

---

# 25. Confirmación y cancelación administrativa

Rutas:

```text
GET   /api/reservations/{id}
PATCH /api/reservations/{id}/cancel
PATCH /api/reservations/{id}/confirm
```

Todas pasan por:

```text
auth:sanctum
permission
scope de Reservation
```

Permisos:

```text
reservation.view
reservation.cancel
reservation.confirm
```

Nota estructural actual:

Los archivos:

```text
ConfirmReservationCommand.php
ConfirmReservationHandler.php
```

están físicamente dentro de:

```text
app/Domain/Reservations/Confirm/
```

pero su namespace es:

```php
App\Application\Reservations\Confirm
```

Por PSR-4 esto es una inconsistencia de ubicación que conviene corregir en algún momento moviéndolos a:

```text
app/Application/Reservations/Confirm/
```

sin cambiar su namespace.

No moverlos automáticamente sin revisar que la suite siga en verde.

---

# 26. Availability — implementación actual

Existen dos endpoints públicos.

## Por Court

```text
GET /api/public/courts/{court_id}/availability
```

Parámetros:

```text
date=YYYY-MM-DD                     requerido
duration_minutes=60|90|120...       opcional, default 60
```

## Por Branch + TipoCourt

```text
GET /api/public/branches/{branch_id}/availability
```

Parámetros:

```text
tipo_court_id                       requerido
date=YYYY-MM-DD                     requerido
duration_minutes                    opcional, default 60
start_time=HH:mm:ss                 opcional
end_time=HH:mm:ss                   opcional
```

La búsqueda por tipo devuelve solamente Courts activas de esa Branch y TipoCourt.

---

# 27. Diferencia entre `interval_minutes` y `duration_minutes`

Esta regla fue implementada el 27/08/2026 y es importante conservarla.

## `interval_minutes`

Controla cada cuánto puede **empezar** una reserva.

Ejemplo:

```text
interval_minutes = 30

08:00
08:30
09:00
09:30
...
```

## `duration_minutes`

Controla cuánto tiempo quiere jugar el usuario.

Reglas actuales:

```text
default = 60
mínimo = 60
debe ser múltiplo de interval_minutes
```

Ejemplo:

```text
interval_minutes = 30
duration_minutes = 90
```

Slots generados:

```text
08:00 → 09:30
08:30 → 10:00
09:00 → 10:30
09:30 → 11:00
...
```

La lógica correcta es:

```text
slotEnd = slotStart + duration_minutes
cursor  = cursor + interval_minutes
```

NO avanzar el cursor usando `duration_minutes`.

Cada slot verifica overlap usando todo el rango solicitado.

Si el `slotEnd` supera el cierre de la Branch, ese slot no se genera.

---

# 28. DTOs de Availability

`CourtAvailabilityDto` devuelve:

```text
court_id
date
interval_minutes
duration_minutes
slots[]
```

Cada slot:

```text
starts_at
ends_at
available
```

`TipoCourtAvailabilityDto` devuelve:

```text
branch_id
tipo_court_id
date
interval_minutes
duration_minutes
courts[]
```

Cada Court contiene sus slots.

---

# 29. ReservationRepository

Contrato actual:

```php
findById(int $id): ?Reservation

findByPublicToken(string $token): ?Reservation

findByCourt(int $courtId): array

hasOverlap(
    int $courtId,
    DateTimeImmutable $startsAt,
    DateTimeImmutable $endsAt,
    ?int $excludeReservationId = null
): bool

save(Reservation $reservation): Reservation

update(Reservation $reservation): Reservation

savePriceSegments(int $reservationId, array $segments): void

findPriceSegments(int $reservationId): array

findBlockingReservationsBetween(
    int $courtId,
    DateTimeImmutable $startsAt,
    DateTimeImmutable $endsAt
): array

findExpiredPending(): array
```

`findByPublicToken()` ya existe aunque todavía no hay endpoints públicos de consulta/cancelación por token.

---

# 30. Rutas actuales completas a nivel funcional

## Públicas

```text
POST /api/auth/login
POST /api/auth/register

POST /api/public/courts/{court_id}/book
GET  /api/public/courts/{court_id}/availability
GET  /api/public/branches/{branch_id}/availability
```

## Auth

```text
POST /api/auth/logout
GET  /api/user
```

## Clubs

```text
GET    /api/clubs
GET    /api/clubs/{id}
POST   /api/clubs
PUT    /api/clubs/{id}
DELETE /api/clubs/{id}
```

## Branches

```text
GET  /api/clubs/{club_id}/branches
POST /api/clubs/{club_id}/branches

GET    /api/branches/{id}
PUT    /api/branches/{id}
DELETE /api/branches/{id}
```

## Memberships

```text
POST  /api/memberships
PATCH /api/memberships/{id}/status
PATCH /api/memberships/{id}/role
PATCH /api/memberships/{id}/branche
```

Nota: la ruta actual usa literalmente `branche` en singular incorrecto:

```text
/{id}/branche
```

No cambiarla sin revisar consumidores/tests.

## Courts

```text
GET  /api/branches/{branch_id}/courts
POST /api/branches/{branch_id}/courts

GET    /api/courts/{id}
PUT    /api/courts/{id}
DELETE /api/courts/{id}
```

## Pricing

```text
GET  /api/branches/{branch_id}/prices
POST /api/branches/{branch_id}/prices

GET   /api/court_prices/{id}
PUT   /api/court_prices/{id}
PATCH /api/court_prices/{id}/status

GET  /api/court_prices/{court_price_id}/promotions
POST /api/court_prices/{court_price_id}/promotions

GET   /api/court_promotions/{id}
PUT   /api/court_promotions/{id}
PATCH /api/court_promotions/{id}/status
```

## Reservations

```text
GET  /api/courts/{court_id}/reservations
POST /api/courts/{court_id}/reservations

POST /api/courts/{court_id}/book

GET   /api/reservations/{id}
PATCH /api/reservations/{id}/cancel
PATCH /api/reservations/{id}/confirm
```

---

# 31. Factories actuales

Existen factories para:

```text
User
Club
Branch
Membership
Role
Permission
PermissionRole
TipoCourt
Court
CourtPrice
CourtPriceRule
Reservation
ReservationPriceSegment
```

Preferir en tests:

```php
$model = Model::factory()->createOne();
```

cuando se quiera evitar inferencia `Model|Collection` del IDE.

---

# 32. Seeders

Seeders actuales:

```text
RoleSeeder
PermissionSeeder
RolePermissionSeeder
TipoCourtSeeder
AlejoDemoSeeder
MassiveDemoDataSeeder
```

`DatabaseSeeder` ejecuta:

```text
RoleSeeder
PermissionSeeder
RolePermissionSeeder
TipoCourtSeeder
AlejoDemoSeeder
MassiveDemoDataSeeder
```

También quedaron archivos viejos:

```text
AlejoDemoSeederViejo.php
MassiveDemoDataSeederviejo.php
```

No son llamados por `DatabaseSeeder`.

## Demo data

Los seeders de demo crean escenarios con:

- Clubs.
- Branches.
- Courts.
- Tipos de Court.
- Intervalos.
- CourtPrices.
- promociones.
- Users.
- Memberships.
- Reservations.
- ReservationPriceSegments.

Se respetan las reglas principales de conflictos de Memberships en la generación de datos.

---

# 33. Tests actuales

Feature Tests:

```text
tests/Feature/Auth/LoginTest.php
tests/Feature/Auth/LogoutTest.php
tests/Feature/Auth/RegisterTest.php

tests/Feature/Authorization/BranchAuthorizationTest.php

tests/Feature/Branches/BranchTest.php
tests/Feature/Clubs/ClubTest.php

tests/Feature/Pricing/CourtPriceTest.php
tests/Feature/Pricing/CourtPromotionTest.php

tests/Feature/Reservations/AvailabilityTest.php
tests/Feature/Reservations/ReservationExpirationTest.php
tests/Feature/Reservations/ReservationPricingTest.php
tests/Feature/Reservations/ReservationTest.php
```

Unit Tests:

```text
tests/Unit/Pricing/CourtPriceRuleTest.php
tests/Unit/Pricing/PriceResolverTest.php
```

## Escenarios importantes cubiertos en Reservations

- personal con scope + permiso crea reserva;
- scope incorrecto devuelve denegación;
- cliente autenticado reserva para sí mismo;
- guest reserva sin autenticación;
- guest requiere email o teléfono;
- overlap bloquea;
- cancelada no bloquea;
- duración debe respetar intervalo;
- inicio debe estar alineado con grilla;
- cancelación con permiso;
- cancelación sin permiso;
- guest no puede forzar `confirmed`;
- cliente no puede forzar `confirmed`;
- availability por Court;
- availability por TipoCourt;
- Court inactiva no aparece en búsqueda por tipo;
- snapshot de precio base;
- promoción completa;
- reserva que entra/sale de promoción;
- cambio posterior de promoción no modifica historial;
- `PENDING` vigente bloquea;
- `PENDING` vencida no bloquea;
- Job expira pendientes vencidas;
- Job no expira pendientes vigentes.

## Pricing Unit Tests

Cubren:

- precio base;
- promociones;
- límites de horario;
- prorrateo parcial;
- múltiples promociones;
- prioridad;
- ausencia/inactividad de precio base;
- reglas por weekday;
- specific date;
- horario;
- vigencia;
- active/inactive.

La regla del proyecto sigue siendo:

```bash
php artisan test
```

antes de cerrar cambios importantes.

Último estado informado por el usuario al 27/08/2026: **todo funcionando correctamente** después del cambio de duración configurable en Availability.

El ZIP entregado para actualizar este contexto no incluye `vendor/`, por lo que esta actualización inspeccionó el código pero no volvió a ejecutar la suite dentro del entorno de revisión.

---

# 34. Availability: tests que conviene agregar

La implementación de `duration_minutes` ya está en código.

Los tests actuales de `AvailabilityTest` cubren disponibilidad general, pero conviene dejar explícitamente cubiertos en una próxima iteración:

```text
sin duration_minutes → usa 60

duration_minutes=60 → OK

duration_minutes=90 con interval 30 → OK

duration_minutes=120 → OK

duration_minutes<60 → 422

duration_minutes no múltiplo del intervalo → error

slotEnd usa duration_minutes
cursor sigue usando interval_minutes
```

Esto es una mejora de cobertura, no una indicación de que la implementación actual esté fallando.

---

# 35. Próximo bloque recomendado

El próximo bloque funcional que había quedado planteado es permitir que el dueño de la reserva pueda gestionarla sin usar permisos administrativos.

## Cliente autenticado

Propuesta:

```text
GET   /api/me/reservations
GET   /api/me/reservations/{id}
PATCH /api/me/reservations/{id}/cancel
```

Regla:

```text
customer_user_id debe ser auth()->id()
```

No usar permisos RBAC administrativos para estas rutas.

Si la reserva no pertenece al usuario, preferir respuesta tipo `404` para no filtrar existencia.

## Guest mediante `public_token`

Propuesta:

```text
GET   /api/public/reservations/{token}
PATCH /api/public/reservations/{token}/cancel
```

El `public_token` funciona como capacidad/bearer secret.

No exponer operaciones públicas basadas solamente en ID secuencial.

El repository ya tiene:

```php
findByPublicToken(string $token): ?Reservation
```

Faltaría agregar, para cliente autenticado, algo como:

```php
findByCustomerUser(int $userId): array
```

si se implementa la colección propia.

---

# 36. Payments — módulo futuro

`Domain/Payments/Entities/Payment.php` existe como placeholder, pero Payments todavía no está implementado funcionalmente.

Objetivo futuro:

```text
Reservation
↓
Payment
↓
Payment Provider
↓
Webhook
↓
Confirm Reservation
```

Temas a estudiar allí:

- idempotency keys;
- webhooks duplicados;
- eventos fuera de orden;
- retries;
- state machine;
- transactions;
- consistencia;
- confirmación de Reservation solamente después del pago cuando corresponda.

---

# 37. Índices / SQL / performance pendientes

Ya existe índice de disponibilidad sobre:

```text
reservations(court_id, starts_at, ends_at)
```

Más adelante evaluar con datos reales:

```text
EXPLAIN
EXPLAIN ANALYZE
```

antes de agregar índices adicionales.

Candidatos a analizar:

```text
court_id + status + starts_at + ends_at
expires_at
branch_id + tipo_court_id en pricing/courts
```

No agregar índices solamente porque “parecen útiles”; medir consultas reales.

---

# 38. Decisiones de diseño que NO hay que olvidar

1. Eloquent no entra al Domain.
2. Controllers coordinan HTTP, no contienen reglas centrales.
3. Las reglas importantes viven en Entity / Handler / Validator / Service según corresponda.
4. Los Handlers dependen de Repository interfaces.
5. `.collection` no representa un permiso guardado en DB.
6. Autorización y filtrado de colección son problemas distintos.
7. `rol_id` es el nombre real de la FK de roles en memberships/pivots relevantes.
8. Una membership global cubre todas las Branches del Club.
9. Membership global y específicas no pueden coexistir para el mismo User/Club.
10. Branch memberships diferentes sí pueden coexistir.
11. `club.view` tiene comportamiento especial a nivel Club.
12. Pricing es histórico: una Reservation guarda `total_price` + segmentos.
13. `PriceResolver` trabaja minuto a minuto y prorratea sobre tarifa horaria.
14. Toda creación de Reservation funcional debe pasar por `CreateReservationHandler`.
15. `CreateReservationHandler` toma `lockForUpdate()` antes de validar disponibilidad.
16. `PENDING` expira en 15 minutos actualmente.
17. Un `PENDING` vencido deja de bloquear aunque el Job todavía no lo haya marcado `EXPIRED`.
18. Guest y customer autenticado no pueden forzar `confirmed`.
19. El personal sí puede crear `PENDING` o `CONFIRMED` mediante el request administrativo.
20. La duración mínima de una Reservation es 60 minutos.
21. `duration_minutes` de Availability tiene default 60.
22. `duration_minutes` debe ser múltiplo de `interval_minutes` en el código actual.
23. `interval_minutes` define los posibles inicios; `duration_minutes` define el final del turno.
24. En generación de slots: avanzar cursor por `interval_minutes`, no por duración.
25. No se soportan reservas cruzando medianoche por ahora.
26. `public_token` existe para evitar administrar reservas públicas por IDs secuenciales.

---

# 39. Estado actual resumido

Implementado:

```text
✅ Auth con Sanctum
✅ Clubs
✅ Branches
✅ Memberships
✅ Roles
✅ Permissions
✅ AuthorizationService
✅ CheckPermission por scope
✅ Courts
✅ TipoCourt
✅ IntervalTimeTipoCourt
✅ Pricing base
✅ Promotions / CourtPriceRules
✅ PriceResolver
✅ Unit Tests de Pricing
✅ Reservations
✅ Guest booking
✅ Authenticated customer booking
✅ Staff booking
✅ Reservation pricing snapshot
✅ Availability por Court
✅ Availability por Branch + TipoCourt
✅ duration_minutes configurable en Availability
✅ duración mínima 60
✅ Cancel Reservation administrativa
✅ Confirm Reservation administrativa
✅ PENDING expiration
✅ ExpirePendingReservationsJob
✅ Scheduler cada minuto
✅ protección de doble reserva con transaction + lockForUpdate
✅ Feature Tests de Reservations/Pricing/Auth/Authorization
```

Pendiente / próximo:

```text
⬜ tests específicos de duration_minutes en Availability
⬜ cliente autenticado: listar/ver/cancelar reservas propias
⬜ guest: ver/cancelar mediante public_token
⬜ Payments
⬜ webhooks e idempotencia
⬜ eventos/notifications/queues posteriores a confirmación
⬜ análisis MySQL con EXPLAIN
⬜ performance e índices adicionales basados en medición
```

---

# 40. Cómo retomar el proyecto en una próxima conversación

NO empezar de cero.

Primero leer este archivo y después inspeccionar el código real si hubo cambios posteriores.

Modelo mental principal:

```text
HTTP Request
↓
Controller / FormRequest
↓
Command o Query
↓
Handler
↓
Domain + Repository interfaces
↓
Infrastructure / Eloquent
↓
DTO
↓
ApiResponse
```

Para autorización administrativa:

```text
Resource
↓
Court/Branch/Club correspondiente
↓
Membership activa que cubre el scope
↓
Role
↓
Permission = route name
```

Para crear Reservation:

```text
Request
↓
CreateReservationHandler
↓
Transaction
↓
lock Court
↓
ReservationValidator
↓
PriceResolver
↓
Reservation + historical price segments
↓
Commit
```

Para Availability:

```text
date
+
duration_minutes (default 60)
↓
interval_minutes determina inicios
↓
duration_minutes determina fin de cada slot
↓
se valida el rango completo contra blocking reservations
```

Próxima dirección recomendada:

```text
Customer/Guest ownership de reservas
↓
Payments
↓
webhooks/idempotencia
↓
notifications/events/queues
↓
performance/EXPLAIN
```
