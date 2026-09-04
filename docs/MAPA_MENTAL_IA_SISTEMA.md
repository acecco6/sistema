# MAPA MENTAL DEL PROYECTO PARA IA — SISTEMA DE RESERVAS

> Objetivo: que cualquier IA pueda ubicarse rápido en el proyecto, entender qué archivo tocar, qué capas participan y qué otros módulos pueden verse afectados antes de responder o proponer cambios.
>
> Fuente de este mapa: `sistema-master(7).zip`.

---

# 1. CÓMO DEBE LEER ESTE PROYECTO UNA IA

Antes de responder una pregunta sobre una API o funcionalidad, seguir este orden:

```text
routes/api.php
    ↓
Controller
    ↓
FormRequest
    ↓
Command / Query
    ↓
Handler
    ↓
Entidad / Service / Policy de dominio
    ↓
Repository interface
    ↓
EloquentRepository / Gateway
    ↓
Model + Migration
    ↓
Events / Listeners / Jobs si corresponde
    ↓
Tests del módulo
```

No asumir que una regla está solamente en el Controller. En este proyecto las reglas importantes suelen vivir en:

- `app/Domain/...`
- `app/Application/...`
- repositorios
- services/policies
- eventos/jobs
- middleware de autorización

Para cambios financieros revisar SIEMPRE conjuntamente:

```text
Payment
PaymentRefund
ReservationPaymentSummaryService
ReservationPaymentPolicy
RegisterManualPaymentHandler
CreateRefundHandler
ProcessMercadoPagoWebhookHandler
```

Para cambios de reservas revisar SIEMPRE conjuntamente:

```text
Reservation
ReservationValidator
CreateReservationHandler
EloquentReservationRepository
Availability handlers
Cancel/Confirm handlers
ExpirePendingReservationsJob
Events de Reservation
```

---

# 2. ARQUITECTURA GENERAL

```text
HTTP
├── routes/api.php
├── Controllers
├── Requests
└── Middleware
        ↓
APPLICATION
├── Commands / Queries
├── Handlers
├── DTOs
├── Services
├── Resolvers
└── Listeners
        ↓
DOMAIN
├── Entities
├── Enums
├── Repository interfaces
├── Policies / Services
├── Events
└── Exceptions
        ↓
INFRASTRUCTURE
├── Eloquent repositories
├── Mercado Pago gateway
├── Webhook signature validator
├── Sanctum token generator
└── Password hasher
        ↓
PERSISTENCIA / EXTERNOS
├── Eloquent Models
├── Migrations
├── DB
├── Mercado Pago
├── Mail
└── Queue
```

El binding entre interfaces y sus implementaciones está centralizado en:

```text
app/Providers/AppServiceProvider.php
```

También allí se registran los listeners de eventos.

---

# 3. MAPA RÁPIDO DE MÓDULOS

```text
Users/Auth
   ↓
Memberships ──→ Roles ──→ Permissions
   ↓
Clubs
   ↓
Branches
   ↓
Courts ──→ TipoCourt ──→ IntervalTimeTipoCourt
   ↓
Pricing ──→ CourtPrice ──→ CourtPriceRule
   ↓
Availability
   ↓
Reservations
   ├── Guest
   ├── Customer autenticado
   └── Staff
   ↓
Payments
   ├── Mercado Pago checkout
   ├── Webhook
   └── Pago manual
   ↓
Refunds
   ↓
Events / Notifications / EmailLog / Queue
```

---

# 4. AUTH / USERS

## APIs

```text
POST /api/auth/login
name: auth.login
Controller: app/Http/Controllers/Auth/LoginController.php
Request: app/Http/Requests/Auth/LoginRequest.php
Application:
  app/Application/Auth/Login/LoginCommand.php
  app/Application/Auth/Login/LoginHandler.php
Infra:
  app/Infrastructure/Auth/LaravelPasswordHasher.php
  app/Infrastructure/Auth/Sanctum/SanctumTokenGenerator.php
Domain:
  app/Domain/Users/*

POST /api/auth/register
name: auth.register
Controller: app/Http/Controllers/Auth/RegisterController.php
Request: app/Http/Requests/Auth/RegisterRequest.php
Application:
  app/Application/Auth/Register/RegisterCommand.php
  app/Application/Auth/Register/RegisterHandler.php

POST /api/auth/logout
name: auth.logout
Controller: app/Http/Controllers/Auth/LogoutController.php
Auth: Sanctum

GET /api/user
name: user.view
Controller: app/Http/Controllers/Users/ProfileController.php
```

## Archivos clave

```text
app/Domain/Users/Entities/User.php
app/Domain/Users/ValueObjects/Email.php
app/Domain/Users/Repositories/UserRepository.php
app/Infrastructure/Persistence/EloquentUserRepository.php
app/Models/User.php
```

---

# 5. AUTHORIZATION / MEMBERSHIPS / ROLES / PERMISSIONS

## Idea central

El nombre de la ruta se usa como permiso.

Ejemplos:

```text
branch.update
court.create
reservation.confirm
payment.create
refund.complete
reservation.refund.create
```

Las rutas terminadas en `.collection` tienen tratamiento especial: validan scope y no necesariamente representan un permiso persistido.

## Middleware central

```text
app/Http/Middleware/CheckPermission.php
```

Resuelve el scope según el primer segmento del nombre de ruta:

```text
club.*            → resolveClubScope()
branch.*          → resolveBranchScope()
membership.*      → resolveMembershipScope()
court.*           → resolveCourtScope()
court_price.*     → resolveCourtPriceScope()
court_promotion.* → resolveCourtPromotionScope()
reservation.*     → resolveReservationScope()
payment.*         → resolvePaymentScope()
refund.*          → resolveRefundScope()
```

IMPORTANTE:

```text
reservation.refund.create
```

empieza con `reservation`, por lo tanto `{id}` se interpreta como ID de Reservation y el scope se obtiene desde:

```text
Reservation → Court → Branch → Club
```

Mientras que:

```text
refund.view
refund.complete
```

empiezan con `refund`, por lo que `{id}` es un PaymentRefund.

## Servicio de autorización

```text
app/Application/Authorization/AuthorizationService.php
```

Métodos principales:

```text
can()
authorize()
canInClub()
authorizeInClub()
```

## Membership scope

```text
branch_id = null
→ membership global para todo el club

branch_id = X
→ membership limitada a esa branch
```

Repositorio:

```text
app/Domain/Memberships/Repositories/MembershipRepository.php
app/Infrastructure/Persistence/EloquentMembershipRepository.php
```

Métodos importantes:

```text
findActiveForScope()
findActiveForClub()
hasConflictingMembership()
hasActiveMemberships()
```

## APIs memberships

```text
POST  /api/memberships
      membership.create
      CreateMembershipController
      Application/Memberships/Create/*

PATCH /api/memberships/{id}/status
      membership.change_status
      ChangeMembershipStatusController
      Application/Memberships/ChangeStatus/*

PATCH /api/memberships/{id}/role
      membership.change_role
      ChangeMembershipRoleController
      Application/Memberships/ChangeRole/*

PATCH /api/memberships/{id}/branche
      membership.change_branch
      ChangeMembershipBranchController
      Application/Memberships/ChangeBranche/*
```

---

# 6. CLUBS

## Flujo

```text
Route
→ Club Controller
→ Command/Query + Handler
→ ClubRepository
→ EloquentClubRepository
→ Club Model
```

## APIs

```text
GET    /api/clubs
       club.collection
       GetClubController
       Application/Clubs/Get/*

GET    /api/clubs/{id}
       club.view
       ShowClubController
       Application/Clubs/Show/*

POST   /api/clubs
       club.create
       CreateClubController
       Application/Clubs/Store/*
       sin middleware permission

PUT    /api/clubs/{id}
       club.update
       UpdateClubController
       Application/Clubs/Update/*

DELETE /api/clubs/{id}
       club.deactivate
       DesactivateClubController
       Application/Clubs/Desactivate/*
```

## Domain/Infra

```text
app/Domain/Clubs/Entities/Club.php
app/Domain/Clubs/Repositories/ClubRepository.php
app/Infrastructure/Persistence/EloquentClubRepository.php
app/Models/Club.php
```

---

# 7. BRANCHES

```text
Club
  └── Branch
```

Una Branch contiene horario de apertura/cierre y es el scope operativo principal para canchas, precios, reservas y refunds.

## APIs

```text
GET  /api/clubs/{club_id}/branches
     branch.collection
     GetBranchController
     Application/Branches/Get/*

POST /api/clubs/{club_id}/branches
     branch.create
     CreateBranchController
     Application/Branches/Store/*

GET  /api/branches/{id}
     branch.view
     ShowBranchController
     Application/Branches/Show/*

PUT  /api/branches/{id}
     branch.update
     UpdateBranchController
     Application/Branches/Update/*

DELETE /api/branches/{id}
       branch.deactivate
       DesactivateBranchController
       Application/Branches/Desactivate/*
```

## Archivos clave

```text
app/Domain/Branches/Entities/Branch.php
app/Domain/Branches/Repositories/BranchRepository.php
app/Infrastructure/Persistence/EloquentBranchRepository.php
app/Models/Branch.php
```

---

# 8. COURTS / TIPO COURT / INTERVALOS

```text
Branch
  └── Court
       └── TipoCourt

Branch + TipoCourt
  └── interval_minutes
```

El intervalo controla alineación y generación de slots.

## APIs Courts

```text
GET  /api/branches/{branch_id}/courts
     court.collection
     GetCourtController
     Application/Courts/Get/*

POST /api/branches/{branch_id}/courts
     court.create
     CreateCourtController
     Application/Courts/Store/*

GET  /api/courts/{id}
     court.view
     ShowCourtController
     Application/Courts/Show/*

PUT  /api/courts/{id}
     court.update
     UpdateCourtController
     Application/Courts/Update/*

DELETE /api/courts/{id}
       court.deactivate
       DeactivateCourtController
       Application/Courts/Deactivate/*
```

## Domain/Infra

```text
app/Domain/Courts/Entities/Court.php
app/Domain/Courts/Entities/TipoCourt.php
app/Domain/Courts/Repositories/CourtRepository.php
app/Domain/Courts/Repositories/TipoCourtRepository.php
app/Domain/Courts/Repositories/IntervalTimeTipoCourtRepository.php

app/Infrastructure/Persistence/EloquentCourtRepository.php
app/Infrastructure/Persistence/EloquentTipoCourtRepository.php
app/Infrastructure/Persistence/EloquentIntervalTimeTipoCourtRepository.php
```

---

# 9. PRICING / PROMOCIONES

## Modelo mental

```text
Branch + TipoCourt
       ↓
CourtPrice (precio base por 60 min)
       ↓
CourtPriceRule[]
       ↓
PriceResolver
       ↓
ReservationPrice
       └── PriceSegment[]
```

## Precio base

```text
app/Domain/Pricing/Entities/CourtPrice.php
```

## Reglas/promociones

```text
app/Domain/Pricing/Entities/CourtPriceRule.php
```

Una regla puede filtrar por:

```text
day_of_week
specific_date
start_time
end_time
priority
starts_at
ends_at
active
```

`end_time` es EXCLUSIVO.

Ejemplo:

```text
14:00 → 18:00
17:59:59 aplica
18:00:00 no aplica
```

## Resolver de precio

```text
app/Application/Pricing/Resolver/PriceResolver.php
```

Es el archivo principal cuando una pregunta involucra:

- precio final
- promociones parciales
- tramos horarios
- reglas superpuestas
- prioridad de promociones

DTO/resultados relacionados:

```text
PriceSegment.php
ReservationPrice.php
```

## APIs CourtPrice

```text
GET  /api/branches/{branch_id}/prices
     court_price.collection
     GetCourtPriceController

POST /api/branches/{branch_id}/prices
     court_price.create
     CreateCourtPriceController

GET  /api/court_prices/{id}
     court_price.view
     ShowCourtPriceController

PUT  /api/court_prices/{id}
     court_price.update
     UpdateCourtPriceController

PATCH /api/court_prices/{id}/status
      court_price.change_status
      ChangeCourtPriceStatusController
```

## APIs promociones

```text
GET  /api/court_prices/{court_price_id}/promotions
     court_promotion.collection
     GetCourtPromotionController

POST /api/court_prices/{court_price_id}/promotions
     court_promotion.create
     CreateCourtPromotionController

GET  /api/court_promotions/{id}
     court_promotion.view
     ShowCourtPromotionController

PUT  /api/court_promotions/{id}
     court_promotion.update
     UpdateCourtPromotionController

PATCH /api/court_promotions/{id}/status
      court_promotion.change_status
      ChangeCourtPromotionStatusController
```

## Repository

```text
app/Domain/Pricing/Repositories/CourtPriceRepository.php
app/Infrastructure/Persistence/EloquentCourtPriceRepository.php
```

Este único repository maneja precios y reglas.

---

# 10. AVAILABILITY

## APIs públicas

```text
GET /api/public/courts/{court_id}/availability
name: availability.collection
Controller: GetCourtAvailabilityController
Request: GetCourtAvailabilityRequest
Application:
  Reservations/Availability/GetCourtAvailabilityQuery.php
  Reservations/Availability/GetCourtAvailabilityHandler.php

GET /api/public/branches/{branch_id}/availability
name: availability.tipo_court.collection
Controller: GetTipoCourtAvailabilityController
Request: GetTipoCourtAvailabilityRequest
Application:
  Reservations/Availability/GetTipoCourtAvailabilityQuery.php
  Reservations/Availability/GetTipoCourtAvailabilityHandler.php
```

## Archivos críticos

```text
app/Application/Reservations/Support/BranchOperatingWindow.php
app/Application/Reservations/Validation/ReservationValidator.php
app/Domain/Courts/Repositories/IntervalTimeTipoCourtRepository.php
app/Domain/Reservations/Repositories/ReservationRepository.php
```

## Horarios overnight

`BranchOperatingWindow` es obligatorio de revisar para sucursales cuyo cierre es al día siguiente.

Ejemplo:

```text
opening_time = 18:00
closing_time = 02:00
```

El sistema interpreta 02:00 como el día siguiente.

El `start_time` solicitado por el usuario funciona como límite inferior; la grilla sigue alineada desde la apertura real de la sucursal.

## Qué reservas bloquean disponibilidad

```text
CONFIRMED → bloquea
PENDING   → bloquea mientras no haya expirado
CANCELLED → no bloquea
COMPLETED → no bloquea
EXPIRED   → no bloquea
```

---

# 11. RESERVATIONS — NÚCLEO DEL SISTEMA

## Entidad central

```text
app/Domain/Reservations/Entities/Reservation.php
```

Estados:

```text
pending
confirmed
cancelled
completed
expired
```

## Cliente de una reserva

Debe existir exactamente una modalidad:

```text
A) cliente registrado
   customer_user_id != null
   guest_* = null

B) guest
   customer_user_id = null
   guest_name obligatorio
```

No se permiten ambas modalidades simultáneamente.

## created_by_user_id

```text
staff crea reserva → usuario staff
cliente autenticado → mismo cliente
public guest → null
```

## Validación antes de crear

Archivo:

```text
app/Application/Reservations/Validation/ReservationValidator.php
```

Revisar aquí para preguntas sobre:

```text
fecha futura
hora apertura/cierre
horarios overnight
intervalos
mínimo de duración
overlap
disponibilidad
```

## Creación principal

```text
app/Application/Reservations/Create/CreateReservationHandler.php
```

Flujo:

```text
lock Court
  ↓
buscar Branch
  ↓
ReservationValidator
  ↓
PriceResolver
  ↓
crear Reservation
  ↓
guardar
  ↓
guardar ReservationPriceSegment snapshot
```

El lock de Court evita dos reservas simultáneas para la misma cancha/horario.

---

# 12. RESERVAS STAFF

## Crear

```text
POST /api/courts/{court_id}/reservations
name: reservation.create
middleware: auth:sanctum + permission
Controller: CreateReservationController
Request: CreateReservationRequest
Handler: CreateReservationHandler
```

Puede crear PENDING o CONFIRMED según `confirmed`.

## Listar por cancha

```text
GET /api/courts/{court_id}/reservations
name: reservation.collection
Controller: GetCourtReservationsController
Application/Reservations/Collection/*
```

## Ver

```text
GET /api/reservations/{id}
name: reservation.view
Controller: ShowReservationController
Application/Reservations/Show/*
```

## Confirmar

```text
PATCH /api/reservations/{id}/confirm
name: reservation.confirm
Controller: ConfirmReservationController
Application/Reservations/Confirm/*
```

Evento relacionado:

```text
ReservationConfirmed
```

## Cancelar

```text
PATCH /api/reservations/{id}/cancel
name: reservation.cancel
Controller: CancelReservationController
Request: CancelReservationRequest
Handler: CancelReservationHandler
```

La cancelación administrativa puede incluir:

```json
{
  "create_refund": true,
  "refund_reason": "..."
}
```

Si `create_refund=true`, el handler puede crear una obligación de refund `PENDING` dentro de la misma transacción.

Evento:

```text
ReservationCancelled
```

---

# 13. RESERVAS CUSTOMER AUTENTICADO

## Crear + checkout

```text
POST /api/courts/{court_id}/book
name: reservation.customer.create
Controller: BookCourtAuthenticatedController
Request: AuthenticatedCustomerReservationRequest
```

Flujo:

```text
CreateReservationHandler
  ↓
Reservation PENDING + expires_at +15 min
  ↓
CreatePaymentCheckoutHandler
  ↓
Payment PENDING MERCADO_PAGO
  ↓
checkout_url
```

No usa middleware `permission`; usa auth y ownership.

## Mis reservas

```text
GET /api/me/reservations
reservation.customer.collection
GetCustomerReservationsController

GET /api/me/reservations/{id}
reservation.customer.view
ShowCustomerReservationController

PATCH /api/me/reservations/{id}/cancel
reservation.customer.cancel
CancelCustomerReservationController
```

La cancelación del cliente usa regla de 24 horas.

---

# 14. RESERVAS GUEST

## Crear + checkout

```text
POST /api/public/courts/{court_id}/book
name: reservation.guest.create
Controller: BookCourtGuestController
Request: GuestReservationRequest
```

Flujo:

```text
Guest data
  ↓
CreateReservationHandler
  ↓
PENDING + expires_at +15 min
  ↓
CreatePaymentCheckoutHandler
  ↓
checkout Mercado Pago
```

Devuelve `public_token` solamente al crear la reserva guest.

## Consultar por token

```text
GET /api/public/reservations/{token}
reservation.guest.view
ShowGuestReservationController
Application/Reservations/Guest/ShowGuestReservation*
```

## Cancelar por token

```text
PATCH /api/public/reservations/{token}/cancel
reservation.guest.cancel
CancelGuestReservationController
Application/Reservations/Guest/CancelGuestReservation*
```

---

# 15. CICLO DE VIDA DE RESERVATION

```text
                   ┌─────────────┐
                   │   PENDING   │
                   └──────┬──────┘
                          │
             ┌────────────┼────────────┐
             │            │            │
             ▼            ▼            ▼
       CONFIRMED       EXPIRED      CANCELLED
             │
             ▼
        COMPLETED
```

Reglas de dominio están en:

```text
app/Domain/Reservations/Entities/Reservation.php
```

Métodos importantes:

```text
confirm()
confirmFromPayment()
expire()
cancel()
cancelByCustomer()
cancelByStaff()
complete()
blocksAvailability()
```

---

# 16. EXPIRACIÓN DE RESERVAS PENDING

## Job

```text
app/Jobs/ExpirePendingReservationsJob.php
```

Busca:

```text
ReservationRepository::findExpiredPending()
```

Luego:

```text
reservation->expire()
repository->update()
ReservationExpired::dispatch()
```

La reserva PENDING normalmente nace con:

```text
expires_at = now + 15 minutos
```

Revisar también:

```text
routes/console.php
```

para scheduling.

---

# 17. PAYMENTS — MAPA GENERAL

```text
Reservation
   └── Payments[]

Payment
├── amount
├── method
├── status
├── provider
├── provider_preference_id
├── provider_payment_id
├── external_reference
├── checkout_url
├── created_by_user_id
└── paid_at
```

Entidad:

```text
app/Domain/Payments/Entities/Payment.php
```

Repository:

```text
app/Domain/Payments/Repositories/PaymentRepository.php
app/Infrastructure/Persistence/EloquentPaymentRepository.php
```

Métodos críticos:

```text
findByExternalReference()
findByProviderPaymentId()
findByReservation()
sumApprovedByReservation()
findPendingByReservation()
save()
update()
```

## PaymentStatus

```text
PENDING
APPROVED
REJECTED
CANCELLED
REFUNDED
```

## PaymentMethod

```text
CASH
TRANSFER
MERCADO_PAGO
CARD
OTHER
```

---

# 18. MERCADO PAGO CHECKOUT

No tiene una ruta independiente: se ejecuta dentro de las APIs de booking guest/customer.

Archivos:

```text
app/Application/Payments/CreateCheckout/CreatePaymentCheckoutCommand.php
app/Application/Payments/CreateCheckout/CreatePaymentCheckoutHandler.php
app/Application/Payments/Gateways/PaymentGateway.php
app/Infrastructure/Payments/Gateways/MercadoPagoPaymentGateway.php
```

Flujo:

```text
Reservation PENDING
  ↓
CreatePaymentCheckoutHandler
  ↓
ReservationPaymentPolicy::requiredDeposit()
  ↓
PaymentGateway::createCheckout()
  ↓
crear Payment MERCADO_PAGO PENDING
```

Si ya existe un Payment PENDING para la reserva, devuelve ese checkout en vez de duplicarlo.

---

# 19. MERCADO PAGO WEBHOOK

## API

```text
POST /api/webhooks/mercadopago
name: webhook.mercadopago
Controller: MercadoPagoWebhookController
```

## Archivos clave

```text
app/Application/Payments/Webhooks/ProcessMercadoPagoWebhookCommand.php
app/Application/Payments/Webhooks/ProcessMercadoPagoWebhookHandler.php
app/Application/Payments/Webhooks/WebhookSignatureValidator.php
app/Infrastructure/Payments/Webhooks/MercadoPagoWebhookSignatureValidator.php
```

## Flujo aprobado

```text
Mercado Pago webhook
   ↓
validar firma
   ↓
consultar pago real al provider
   ↓
buscar Payment por external_reference
   ↓
validar payment id / ARS / amount
   ↓
DB transaction
   ↓
Payment APPROVED
   ↓
lock Reservation
   ↓
ReservationPaymentPolicy::isDepositCovered()
   ↓
confirmFromPayment()
   ↓
Reservation CONFIRMED
   ↓
ReservationConfirmed event
```

Es idempotente: si el Payment ya está APPROVED no vuelve a procesarlo.

IMPORTANTE: un pago aprobado tarde no debe revivir una reserva expirada.

---

# 20. PAYMENT POLICY — SEÑA

Archivo:

```text
app/Domain/Payments/Services/ReservationPaymentPolicy.php
```

Centraliza:

```text
requiredDeposit(total)
isDepositCovered(total, approved)
percentage()
```

Cuando cambie el porcentaje de seña, revisar este archivo antes que cualquier Controller.

---

# 21. PAGO MANUAL

## API

```text
POST /api/reservations/{id}/payments
name: payment.create
Controller: RegisterManualPaymentController
Request: RegisterManualPaymentRequest
Application:
  RegisterManualPaymentCommand.php
  RegisterManualPaymentHandler.php
```

## Regla importante

Pago manual NO acepta `MERCADO_PAGO`.

Los pagos manuales nacen `APPROVED`.

## Cálculo correcto de saldo

NO usar solamente:

```text
total - approved_amount
```

Porque puede haber refunds COMPLETED.

Debe usarse:

```text
ReservationPaymentSummaryService
```

que calcula:

```text
approved_amount
refunded_amount
net_paid_amount
remaining_amount
```

Ejemplo:

```text
Reserva               30.000
Pagos APPROVED         30.000
Refund COMPLETED       10.000
-----------------------------
Net paid               20.000
Remaining              10.000
```

Un nuevo pago manual por 10.000 debe permitirse.

---

# 22. HISTORIAL DE PAGOS

## API

```text
GET /api/reservations/{id}/payments
name: payment.view
Controller: GetReservationPaymentsController
Application:
  GetReservationPaymentsQuery.php
  GetReservationPaymentsHandler.php
DTO:
  ReservationPaymentsDto.php
```

Devuelve pagos de la reserva junto con resumen financiero.

---

# 23. RESUMEN FINANCIERO DE UNA RESERVA

Archivo principal:

```text
app/Application/Payments/Services/ReservationPaymentSummaryService.php
```

DTO:

```text
app/Application/Payments/DTOs/ReservationPaymentSummary.php
```

Conceptos:

```text
approved_amount
= suma de Payments APPROVED

refunded_amount
= suma de Refunds COMPLETED

net_paid_amount
= approved_amount - refunded_amount

remaining_amount
= max(total_price - net_paid_amount, 0)
```

IMPORTANTE:

```text
PENDING refund   → NO baja net_paid_amount
COMPLETED refund → SÍ baja net_paid_amount
CANCELLED refund → NO baja net_paid_amount
```

## FinancialStatus

```text
UNPAID          = impago
PARTIALLY_PAID  = parcialmente_pagado
DEPOSIT_PAID    = pago_senia
PAID            = pagado
OVERPAID        = pagado_excedido
```

El status financiero debe resolverse con `net_paid_amount`, no con pagos brutos.

---

# 24. REFUNDS — MODELO MENTAL

```text
Reservation
   └── PaymentRefund[]
```

El sistema NO ejecuta físicamente una devolución bancaria.

Un refund representa una obligación/registro administrativo.

```text
PENDING
→ hay dinero pendiente de devolver

COMPLETED
→ el dinero ya fue devuelto manualmente

CANCELLED
→ la obligación fue anulada
```

Entidad:

```text
app/Domain/Payments/Entities/PaymentRefund.php
```

Repository:

```text
app/Domain/Payments/Repositories/PaymentRefundRepository.php
app/Infrastructure/Persistence/EloquentPaymentRefundRepository.php
```

Métodos clave:

```text
findById()
findByIdForUpdate()
findByReservation()
findPending()
sumCommittedByReservation()
sumCompletedByReservation()
findByBranch()
getRefundsByReservationId()
```

## committed refunds

Para evitar devolver más dinero de lo cobrado:

```text
committed = PENDING + COMPLETED
```

`CANCELLED` no cuenta.

---

# 25. CREAR REFUND MANUAL

## API

```text
POST /api/reservations/{id}/refunds
name: reservation.refund.create
Controller: CreateRefundController
Request: CreateRefundRequest
Application:
  Payments/Refunds/CreateRefund/CreateRefundCommand.php
  Payments/Refunds/CreateRefund/CreateRefundHandler.php
```

`{id}` es ID DE RESERVATION.

El nombre `reservation.refund.create` es intencional para que `CheckPermission` resuelva scope por Reservation.

## Regla

```text
approved = pagos APPROVED
committed = refunds PENDING + COMPLETED
refundable = approved - committed
```

No puede crearse un refund mayor a `refundable`.

Nace:

```text
status = PENDING
method = null
completed_at = null
```

No se envía email al crearlo.

---

# 26. REFUND DESDE CANCELACIÓN STAFF

Archivo:

```text
app/Application/Reservations/Cancel/CancelReservationHandler.php
```

Cuando:

```text
create_refund = true
```

la cancelación y creación del refund ocurren en la misma transacción.

La existencia de pagos APPROVED por sí sola NO genera refund automáticamente.

---

# 27. CONSULTAR REFUND

```text
GET /api/refunds/{id}
name: refund.view
Controller: GetRefundController
Application:
  Payments/Refunds/GetRefund/GetRefundQuery.php
  Payments/Refunds/GetRefund/GetRefundHandler.php
```

Aquí `{id}` es ID DE REFUND.

---

# 28. LISTAR REFUNDS POR BRANCH

```text
GET /api/branches/{branch_id}/refunds
name: refund.collection
Controller: ListRefundsController
Request: ListRefundsRequest
Application:
  Payments/Refunds/ListRefunds/ListRefundsQuery.php
  Payments/Refunds/ListRefunds/ListRefundsHandler.php
```

Puede filtrar por status según Request.

Al ser `.collection`, la autorización valida scope de Branch.

---

# 29. COMPLETAR REFUND

## API

```text
PATCH /api/refunds/{id}/complete
name: refund.complete
Controller: CompleteRefundController
Request: CompleteRefundRequest
Application:
  Payments/Refunds/CompleteRefund/CompleteRefundCommand.php
  Payments/Refunds/CompleteRefund/CompleteRefundHandler.php
```

`{id}` es ID DE REFUND.

Flujo:

```text
lock refund
  ↓
PaymentRefund::complete()
  ↓
status COMPLETED
  ↓
method + completed_by_user_id + completed_at
  ↓
update
  ↓
RefundCompleted event
  ↓
email de devolución realizada
```

Solo un refund PENDING puede completarse.

---

# 30. EVENTS / NOTIFICATIONS / QUEUES

## Eventos implementados

```text
app/Domain/Reservations/Events/ReservationConfirmed.php
app/Domain/Reservations/Events/ReservationCancelled.php
app/Domain/Reservations/Events/ReservationExpired.php
app/Domain/Payments/Events/RefundCompleted.php
```

Se registran en:

```text
app/Providers/AppServiceProvider.php
```

## Relación

```text
ReservationConfirmed
→ SendReservationConfirmedNotification
→ ReservationConfirmedNotification

ReservationCancelled
→ SendReservationCancelledNotification
→ ReservationCancelledNotification

ReservationExpired
→ SendReservationExpiredNotification
→ ReservationExpiredNotification

RefundCompleted
→ SendRefundCompletedNotification
→ RefundCompletedNotification
```

Listeners:

```text
app/Application/Notifications/Listeners/*
```

Mail notifications:

```text
app/Application/Notifications/Mail/*
```

Los listeners trabajan con queue para desacoplar el envío de email del caso de uso principal.

---

# 31. EMAIL LOG

Cada intento de email se audita.

```text
app/Domain/Notifications/Entities/EmailLog.php
app/Domain/Notifications/Enums/EmailLogStatus.php
app/Domain/Notifications/Repositories/EmailLogRepository.php
app/Infrastructure/Persistence/EloquentEmailLogRepository.php
app/Models/EmailLog.php
```

Estados:

```text
PENDING
SENT
FAILED
```

Flujo conceptual:

```text
Event
  ↓
Queued Listener
  ↓
EmailLog PENDING
  ↓
Notification::route(...)
  ↓
SENT o FAILED
```

Para guest el destino sale de `guest_email`.

Para cliente registrado se busca el `User` mediante `UserRepository`.

---

# 32. AUDIT LOG HTTP

Middleware:

```text
app/Http/Middleware/AuditLog.php
```

Configuración:

```text
config/audit.php
config/logging.php
```

No confundir:

```text
AuditLog
→ auditoría de requests HTTP

EmailLog
→ auditoría de emails
```

---

# 33. REPOSITORY → IMPLEMENTACIÓN

```text
UserRepository
→ EloquentUserRepository

ClubRepository
→ EloquentClubRepository

BranchRepository
→ EloquentBranchRepository

MembershipRepository
→ EloquentMembershipRepository

RoleRepository
→ EloquentRoleRepository

PermissionRepository
→ EloquentPermissionRepository

CourtRepository
→ EloquentCourtRepository

TipoCourtRepository
→ EloquentTipoCourtRepository

IntervalTimeTipoCourtRepository
→ EloquentIntervalTimeTipoCourtRepository

CourtPriceRepository
→ EloquentCourtPriceRepository

ReservationRepository
→ EloquentReservationRepository

PaymentRepository
→ EloquentPaymentRepository

PaymentRefundRepository
→ EloquentPaymentRefundRepository

EmailLogRepository
→ EloquentEmailLogRepository
```

Bindings:

```text
app/Providers/AppServiceProvider.php
```

---

# 34. GATEWAYS / SERVICIOS EXTERNOS

```text
PaymentGateway
→ MercadoPagoPaymentGateway

WebhookSignatureValidator
→ MercadoPagoWebhookSignatureValidator

TokenGenerator
→ SanctumTokenGenerator

PasswordHasher
→ LaravelPasswordHasher
```

Configuraciones relacionadas:

```text
config/services.php
config/sanctum.php
config/auth.php
config/mail.php
config/queue.php
```

---

# 35. MODELOS ELOQUENT PRINCIPALES

```text
app/Models/User.php
app/Models/Club.php
app/Models/Branch.php
app/Models/Membership.php
app/Models/Role.php
app/Models/Permission.php
app/Models/PermissionRole.php
app/Models/Court.php
app/Models/TipoCourt.php
app/Models/CourtPrice.php
app/Models/CourtPriceRule.php
app/Models/Reservation.php
app/Models/ReservationPriceSegment.php
app/Models/Payment.php
app/Models/PaymentRefund.php
app/Models/EmailLog.php
```

Regla para IA:

```text
Entidad Domain ≠ Eloquent Model
```

No agregar reglas de negocio directamente al Model si ya existe una entidad de dominio para ese concepto.

---

# 36. MIGRACIONES / TABLAS

```text
users
personal_access_tokens
clubs
branches
roles
role_user
permissions
permission_role
tipos_court
courts
interval_time_tipo_court
court_prices
court_price_rules
reservations
reservation_price_segments
payments
payment_refunds
email_logs
jobs
```

Antes de proponer un campo nuevo, revisar la migration real y el Model correspondiente. No inventar columnas.

---

# 37. RELACIONES DE DATOS IMPORTANTES

```text
Club
└── Branch
    ├── Membership scope
    ├── Court
    │   └── Reservation
    │       ├── ReservationPriceSegment
    │       ├── Payment
    │       └── PaymentRefund
    └── CourtPrice
        └── CourtPriceRule
```

Más explícito:

```text
Reservation.court_id
→ Court.id

Court.branch_id
→ Branch.id

Branch.club_id
→ Club.id

Reservation.customer_user_id
→ User.id nullable

Reservation.created_by_user_id
→ User.id nullable

Payment.reservation_id
→ Reservation.id

PaymentRefund.reservation_id
→ Reservation.id

PaymentRefund.payment_id
→ Payment.id nullable
```

---

# 38. FLUJO END-TO-END: BOOKING CUSTOMER/GUEST

```text
GET availability
  ↓
usuario elige slot
  ↓
POST book
  ↓
CreateReservationHandler
  ├── lock Court
  ├── validate availability
  ├── resolve price
  ├── create PENDING
  ├── expires_at +15m
  └── save price snapshot
  ↓
CreatePaymentCheckoutHandler
  ├── required deposit
  ├── Mercado Pago preference
  └── Payment PENDING
  ↓
cliente paga
  ↓
Mercado Pago webhook
  ↓
Payment APPROVED
  ↓
seña cubierta
  ↓
Reservation CONFIRMED
  ↓
ReservationConfirmed
  ↓
queued email
  ↓
EmailLog SENT/FAILED
```

---

# 39. FLUJO END-TO-END: CANCELACIÓN + REFUND

```text
Reservation CONFIRMED
  ↓
Staff cancela
  ↓
CancelReservationHandler
  ├── lock Reservation
  ├── cancel
  └── opcional create_refund
        ↓
      PaymentRefund PENDING
  ↓
ReservationCancelled
  ↓
email de cancelación

Más tarde:

PATCH refund complete
  ↓
PaymentRefund COMPLETED
  ↓
net_paid_amount disminuye
  ↓
RefundCompleted
  ↓
email devolución realizada
```

---

# 40. FLUJO FINANCIERO CORRECTO

Nunca confundir dinero histórico con dinero neto actual.

```text
approved_amount
= todo lo que alguna vez entró mediante Payments APPROVED

refunded_amount
= todo lo realmente devuelto mediante Refunds COMPLETED

net_paid_amount
= approved_amount - refunded_amount
```

Ejemplo:

```text
Reserva total                  30.000
Pago inicial APPROVED          30.000
Refund COMPLETED              -10.000
Nuevo pago APPROVED            10.000
------------------------------------
approved_amount                40.000
refunded_amount                10.000
net_paid_amount                30.000
remaining_amount                    0
financial_status               pagado
```

Cuando una IA responda preguntas sobre saldo, deuda o posibilidad de registrar otro pago, debe partir de `net_paid_amount`.

---

# 41. LOCKS / CONCURRENCIA — ARCHIVOS A REVISAR

Los locks forman parte de las reglas del sistema, no son detalles opcionales.

```text
CreateReservationHandler
→ lock Court
→ evita double booking

RegisterManualPaymentHandler
→ lock Reservation
→ evita dos pagos contra el mismo saldo

CreateRefundHandler
→ lock Reservation
→ evita refunds concurrentes por encima de lo cobrado

CompleteRefundHandler
→ lock Refund
→ evita doble completion

ProcessMercadoPagoWebhookHandler
→ lock Reservation durante confirmación
→ evita carrera webhook vs expiración
```

Al modificar estos handlers no quitar locks sin analizar concurrencia.

---

# 42. TESTS — DÓNDE BUSCAR SEGÚN EL PROBLEMA

## Auth

```text
tests/Feature/Auth/*
```

## Authorization / Branches / Clubs

```text
tests/Feature/Authorization/*
tests/Feature/Branches/*
tests/Feature/Clubs/*
```

## Pricing

```text
tests/Feature/Pricing/*
tests/Unit/Pricing/*
```

## Reservations

```text
tests/Feature/Reservations/AvailabilityTest.php
tests/Feature/Reservations/CustomerReservationTest.php
tests/Feature/Reservations/GuestReservationTest.php
tests/Feature/Reservations/ReservationExpirationTest.php
tests/Feature/Reservations/ReservationPricingTest.php
tests/Feature/Reservations/ReservationTest.php
```

## Payments

```text
tests/Feature/Payments/MercadoPagoWebhookControllerTest.php
tests/Feature/Payments/ProcessMercadoPagoWebhookHandlerTest.php
tests/Feature/Payments/RegisterManualPaymentControllerTest.php
tests/Feature/Payments/RegisterManualPaymentHandlerTest.php
tests/Feature/Payments/GetReservationPaymentsControllerTest.php
tests/Feature/Payments/ReservationPaymentSummaryServiceTest.php
tests/Unit/Payments/ReservationPaymentPolicyTest.php
```

## Refunds

```text
tests/Feature/Payments/CancelReservationRefundTest.php
tests/Feature/Payments/CompleteRefundControllerTest.php
tests/Feature/Payments/CompleteRefundHandlerTest.php
tests/Feature/Payments/EloquentPaymentRefundRepositoryTest.php
tests/Feature/Payments/RefundQueryControllerTest.php
tests/Unit/Payments/PaymentRefundTest.php
```

También revisar `RegisterManualPaymentHandlerTest.php` para interacción pago ↔ refund.

## Notifications

```text
tests/Feature/Notifications/EmailLogRepositoryTest.php
tests/Feature/Notifications/RefundCompletedNotificationTest.php
tests/Feature/Notifications/ReservationCancelledNotificationTest.php
tests/Feature/Notifications/ReservationConfirmedEventTest.php
tests/Feature/Notifications/ReservationExpiredNotificationTest.php
tests/Feature/Notifications/SendReservationConfirmedNotificationTest.php
tests/Unit/Notifications/ReservationConfirmedNotificationTest.php
```

---

# 43. MATRIZ API → ARCHIVOS PRINCIPALES

| API / Route name | Controller | Application principal | Dominio / dependencia crítica |
|---|---|---|---|
| `auth.login` | `LoginController` | `Auth/Login/*` | `UserRepository`, PasswordHasher, TokenGenerator |
| `auth.register` | `RegisterController` | `Auth/Register/*` | `User`, `UserRepository` |
| `club.collection` | `GetClubController` | `Clubs/Get/*` | `ClubRepository` |
| `club.view` | `ShowClubController` | `Clubs/Show/*` | `ClubRepository`, authorization in club |
| `club.create` | `CreateClubController` | `Clubs/Store/*` | `Club` |
| `club.update` | `UpdateClubController` | `Clubs/Update/*` | `Club` |
| `branch.collection` | `GetBranchController` | `Branches/Get/*` | `BranchRepository` |
| `branch.create` | `CreateBranchController` | `Branches/Store/*` | `Branch`, `Club` |
| `branch.update` | `UpdateBranchController` | `Branches/Update/*` | `Branch` |
| `membership.create` | `CreateMembershipController` | `Memberships/Create/*` | scope/conflicts |
| `membership.change_status` | `ChangeMembershipStatusController` | `Memberships/ChangeStatus/*` | `Membership` |
| `membership.change_role` | `ChangeMembershipRoleController` | `Memberships/ChangeRole/*` | `Role` |
| `membership.change_branch` | `ChangeMembershipBranchController` | `Memberships/ChangeBranche/*` | membership conflicts |
| `court.collection` | `GetCourtController` | `Courts/Get/*` | `CourtRepository` |
| `court.create` | `CreateCourtController` | `Courts/Store/*` | `Branch`, `TipoCourt` |
| `court.update` | `UpdateCourtController` | `Courts/Update/*` | `Court` |
| `court_price.collection` | `GetCourtPriceController` | `Pricing/Get/*` | `CourtPriceRepository` |
| `court_price.create` | `CreateCourtPriceController` | `Pricing/Store/*` | Branch + TipoCourt |
| `court_promotion.create` | `CreateCourtPromotionController` | `Pricing/Rules/Store/*` | `CourtPriceRule` |
| `availability.collection` | `GetCourtAvailabilityController` | `Reservations/Availability/*` | operating window + intervals + reservations |
| `availability.tipo_court.collection` | `GetTipoCourtAvailabilityController` | `Reservations/Availability/*` | courts by tipo + availability |
| `reservation.create` | `CreateReservationController` | `Reservations/Create/*` | Validator + PriceResolver + lock |
| `reservation.guest.create` | `BookCourtGuestController` | Reservation Create + Payment Checkout | guest + MP |
| `reservation.customer.create` | `BookCourtAuthenticatedController` | Reservation Create + Payment Checkout | user + MP |
| `reservation.confirm` | `ConfirmReservationController` | `Reservations/Confirm/*` | Reservation status + event |
| `reservation.cancel` | `CancelReservationController` | `Reservations/Cancel/*` | optional refund + event |
| `payment.create` | `RegisterManualPaymentController` | `Payments/RegisterManualPayment/*` | SummaryService + lock |
| `payment.view` | `GetReservationPaymentsController` | `Payments/GetReservationPayments/*` | payment history + summary |
| `webhook.mercadopago` | `MercadoPagoWebhookController` | `Payments/Webhooks/*` | provider + idempotency + confirm |
| `reservation.refund.create` | `CreateRefundController` | `Payments/Refunds/CreateRefund/*` | approved - committed refunds |
| `refund.view` | `GetRefundController` | `Payments/Refunds/GetRefund/*` | PaymentRefund |
| `refund.collection` | `ListRefundsController` | `Payments/Refunds/ListRefunds/*` | branch scope |
| `refund.complete` | `CompleteRefundController` | `Payments/Refunds/CompleteRefund/*` | lock + RefundCompleted |

---

# 44. SI LA PREGUNTA ES SOBRE... ¿QUÉ ARCHIVOS ABRIR PRIMERO?

## "No me deja reservar / aparece ocupado"

```text
ReservationValidator.php
EloquentReservationRepository.php
GetCourtAvailabilityHandler.php
BranchOperatingWindow.php
ReservationStatus.php
```

## "El precio está mal"

```text
PriceResolver.php
CourtPriceRule.php
CourtPriceRepository.php
ReservationPricingTest.php
PriceResolverTest.php
```

## "La promo no entra en cierto horario"

```text
CourtPriceRule::appliesTo()
PriceResolver.php
CourtPriceRuleTest.php
PriceResolverTest.php
```

## "No puedo pagar / saldo incorrecto"

```text
RegisterManualPaymentHandler.php
ReservationPaymentSummaryService.php
PaymentRepository.php
PaymentRefundRepository.php
ReservationPaymentSummaryServiceTest.php
RegisterManualPaymentHandlerTest.php
```

## "El refund supera / no supera el monto disponible"

```text
CreateRefundHandler.php
PaymentRefundRepository.php
PaymentRepository.php
CancelReservationHandler.php
Refund tests
```

## "Mercado Pago pagó pero no confirmó"

```text
MercadoPagoWebhookController.php
ProcessMercadoPagoWebhookHandler.php
MercadoPagoPaymentGateway.php
ReservationPaymentPolicy.php
Reservation.php::confirmFromPayment()
Webhook tests
```

## "La reserva expiró / no expiró"

```text
ExpirePendingReservationsJob.php
ReservationRepository::findExpiredPending()
Reservation.php::expire()
ReservationExpirationTest.php
```

## "No me deja acceder por permisos"

```text
routes/api.php
CheckPermission.php
AuthorizationService.php
MembershipRepository.php
RoleRepository.php
RolePermissionSeeder.php
```

## "No llegó el email"

```text
AppServiceProvider.php
Domain event
Notification Listener
Mail Notification
EmailLogRepository
config/mail.php
config/queue.php
Notification tests
```

---

# 45. REGLAS PARA UNA IA AL PROPONER CAMBIOS

1. No inventar columnas: revisar migration + Model.
2. No usar `float` para dinero; el proyecto usa strings + `bc*`.
3. No duplicar reglas financieras; usar `ReservationPaymentSummaryService` y `ReservationPaymentPolicy`.
4. No mover reglas de dominio a Controllers.
5. No devolver Eloquent directamente si el flujo ya usa DTOs.
6. Mantener locks existentes en operaciones concurrentes.
7. Si cambia una ruta protegida, revisar `CheckPermission` y permisos.
8. Si cambia un estado de Reservation, revisar disponibilidad, jobs y events.
9. Si cambia Payment/Refund, revisar resumen financiero.
10. Si cambia un evento, revisar listener, notification, EmailLog y tests.
11. En guest/customer revisar ownership además de autenticación.
12. En rutas `.collection` revisar autorización de scope especial.
13. Para operaciones dentro de transaction, efectos externos deben permanecer desacoplados mediante eventos/queue cuando corresponda.
14. Antes de responder "el problema está en X", verificar Controller → Handler → Domain → Repository → Test.

---

# 46. ARCHIVOS DE ENTRADA MÁS IMPORTANTES DEL PROYECTO

Si una IA recibe el repo completo y tiene poco tiempo, leer primero:

```text
1. routes/api.php
2. app/Http/Middleware/CheckPermission.php
3. app/Providers/AppServiceProvider.php
4. app/Domain/Reservations/Entities/Reservation.php
5. app/Application/Reservations/Create/CreateReservationHandler.php
6. app/Application/Reservations/Validation/ReservationValidator.php
7. app/Application/Reservations/Support/BranchOperatingWindow.php
8. app/Application/Pricing/Resolver/PriceResolver.php
9. app/Application/Payments/Services/ReservationPaymentSummaryService.php
10. app/Domain/Payments/Services/ReservationPaymentPolicy.php
11. app/Application/Payments/RegisterManualPayment/RegisterManualPaymentHandler.php
12. app/Application/Payments/Refunds/CreateRefund/CreateRefundHandler.php
13. app/Application/Payments/Webhooks/ProcessMercadoPagoWebhookHandler.php
14. app/Application/Reservations/Cancel/CancelReservationHandler.php
15. app/Jobs/ExpirePendingReservationsJob.php
16. app/Providers/AppServiceProvider.php
17. tests/Feature/Reservations/*
18. tests/Feature/Payments/*
19. tests/Feature/Notifications/*
```

---

# 47. ESTADO CONCEPTUAL ACTUAL

```text
Auth                         ✅
Clubs                        ✅
Branches                     ✅
Memberships / Authorization  ✅
Courts                       ✅
Pricing / Promotions         ✅
Availability                 ✅
Reservations                 ✅
Guest / Customer ownership   ✅
Mercado Pago checkout        ✅
Mercado Pago webhook         ✅
Manual payments              ✅
Payment summary              ✅
Refunds                      ✅
Manual refund creation       ✅
Refund completion            ✅
Events / Queue / Emails       ✅
Email audit                  ✅
```

`ReservationStatus::COMPLETED` y `Reservation::complete()` existen en Domain. Si se implementa automatización de finalización de reservas, revisar si ya existe Handler/Job/API antes de agregar otro flujo.

---

# 48. RESUMEN ULTRARRÁPIDO PARA CONTEXTO DE IA

```text
Proyecto Laravel con separación Domain/Application/Infrastructure/Http.
Sanctum para auth.
Route name = permission para acciones administrativas.
Membership null branch = scope global de club.
Reservation pertenece a guest o user, nunca ambos.
Reservation creation bloquea Court para evitar double booking.
Pricing se resuelve por Branch + TipoCourt y genera snapshots por segmentos.
Pending reserva expira a los 15 minutos.
Guest/customer booking crea checkout Mercado Pago por seña.
Webhook aprueba Payment y confirma Reservation si la seña está cubierta y aún no expiró.
Staff puede registrar pagos manuales APPROVED.
Refund PENDING = obligación; COMPLETED = dinero realmente devuelto.
Saldo real = approved payments - completed refunds.
Refunds PENDING cuentan para limitar nuevas devoluciones, pero no reducen net_paid.
Events de confirm/cancel/expire/refund completed disparan emails por listeners en queue.
EmailLog audita PENDING/SENT/FAILED.
```

---

# 49. REGLA FINAL PARA FUTURAS IAs

Cuando el usuario pregunte algo concreto, NO revisar todo el repo indiscriminadamente.

Usar este mapa para reducir la búsqueda:

```text
Pregunta
   ↓
identificar módulo
   ↓
identificar ruta
   ↓
Controller
   ↓
Handler
   ↓
regla de dominio/service
   ↓
Repository
   ↓
Test existente
```

Y solo después proponer el cambio mínimo necesario.
