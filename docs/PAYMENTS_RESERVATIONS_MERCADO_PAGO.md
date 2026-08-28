# Módulo Payments --- Reservas y Mercado Pago

## 1. Objetivo

El módulo **Payments** administrará los pagos asociados a las reservas
de canchas. La primera integración online será mediante **Mercado Pago
Checkout Pro**.

Reglas principales:

-   Cliente autenticado y guest crean la reserva en `PENDING`.
-   Deben abonar el **50% del precio total** para confirmar.
-   Tienen **15 minutos** para realizar el pago.
-   Al aprobarse el pago requerido, la reserva pasa a `CONFIRMED`.
-   Si vence el plazo, `ExpirePendingReservationsJob` cambia la reserva
    a `EXPIRED` y libera el horario.
-   El staff puede crear una reserva directamente como `CONFIRMED` sin
    pago previo.

## 2. Reserva y pago son estados independientes

Una reserva confirmada no significa necesariamente que esté pagada.

Ejemplos:

``` text
Reservation: CONFIRMED
Pagado:      $0
```

es válido para una reserva confirmada administrativamente por staff.

También son válidos:

``` text
Reservation: CONFIRMED
Total:       $40.000
Pagado:      $20.000
Saldo:       $20.000
```

y:

``` text
Reservation: CONFIRMED
Total:       $40.000
Pagado:      $40.000
Saldo:       $0
```

## 3. Confirmación de cliente y guest

Ejemplo:

``` text
Precio total:     $40.000
Porcentaje seña:  50%
Monto requerido:  $20.000
```

Al crear la reserva:

``` text
status = PENDING
expires_at = now + 15 minutos
```

Cuando los pagos aprobados alcanzan el monto requerido y la reserva
sigue vigente:

``` text
Payment:     APPROVED
Reservation: PENDING → CONFIRMED
expires_at:  null
```

La regla del 50% debe estar centralizada, no repetida en controllers o
handlers. En el futuro podrá configurarse por club.

## 4. Flujo cliente autenticado

``` text
Cliente
  ↓
POST /api/courts/{court_id}/book
  ↓
Validar horario y disponibilidad
  ↓
Calcular precio con PriceResolver
  ↓
Crear Reservation PENDING
expires_at = +15 minutos
  ↓
Calcular 50%
  ↓
Crear Preference / Checkout Mercado Pago
  ↓
Crear Payment PENDING
  ↓
Devolver checkout_url
  ↓
Cliente paga
  ↓
Webhook Mercado Pago
  ↓
Verificar pago
  ↓
Payment APPROVED
  ↓
Reservation CONFIRMED
```

## 5. Flujo guest

El flujo es equivalente al del cliente autenticado:

``` text
POST /api/public/courts/{court_id}/book
```

La respuesta incluirá:

-   datos públicos de la reserva;
-   `public_token`;
-   monto requerido;
-   porcentaje requerido;
-   `checkout_url`;
-   vencimiento.

El `public_token` sigue siendo la credencial para consultar/cancelar la
reserva guest. **No debe enviarse a Mercado Pago, usarse como
`external_reference`, registrarse en logs ni exponerse
innecesariamente.**

## 6. Flujo staff

El flujo administrativo actual permanece independiente:

``` text
POST /api/courts/{court_id}/reservations
confirmed = true
```

Resultado:

``` text
Reservation = CONFIRMED
Payment = ninguno
```

El staff puede reservar una cancha para un cliente presencial/telefónico
sin exigir el 50% previamente.

## 7. Expiración de 15 minutos

Ejemplo:

``` text
16:00 reserva creada
16:00 PENDING
16:00 checkout generado
16:15 expires_at
```

Si no existe confirmación de pago:

``` text
ExpirePendingReservationsJob
  ↓
PENDING → EXPIRED
```

El horario vuelve a estar disponible.

La **fuente de verdad de disponibilidad es nuestra base de datos**,
mediante `Reservation.status` y `Reservation.expires_at`. Aunque el
checkout del proveedor también tenga vencimiento, no se delega al
proveedor la liberación de la cancha.

## 8. Webhook y aprobación

No se confirma un pago por el redirect del navegador.

La confirmación real se procesa mediante un webhook público,
conceptualmente:

``` text
POST /api/webhooks/mercadopago
```

Flujo:

``` text
Mercado Pago
  ↓
Webhook
  ↓
Validar autenticidad
  ↓
Consultar/verificar pago con proveedor
  ↓
Actualizar Payment
  ↓
Si APPROVED:
    verificar Reservation
    verificar vencimiento
    calcular pagos aprobados
    confirmar si alcanza el 50%
```

El webhook debe ser **idempotente**: recibir varias veces el mismo
evento no puede duplicar pagos ni efectos secundarios.

## 9. Pago tardío

Caso crítico:

``` text
16:00 reserva creada
16:15 Job → EXPIRED
16:16 llega Payment APPROVED
```

Está prohibido hacer:

``` text
EXPIRED → CONFIRMED
```

porque el horario pudo haber sido reservado nuevamente.

El pago puede quedar registrado como `APPROVED`, pero la reserva
permanece `EXPIRED`. El tratamiento financiero posterior se definirá
mediante política de devolución/revisión.

## 10. PaymentStatus

``` php
enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
    case REFUNDED = 'REFUNDED';
}
```

## 11. PaymentMethod

``` php
enum PaymentMethod: string
{
    case CASH = 'CASH';
    case TRANSFER = 'TRANSFER';
    case MERCADO_PAGO = 'MERCADO_PAGO';
    case CARD = 'CARD';
    case OTHER = 'OTHER';
}
```

La primera integración online será `MERCADO_PAGO`. No se almacenarán
números de tarjeta, CVV ni otros datos sensibles de tarjeta.

## 12. Tabla payments

Diseño inicial:

``` text
payments
- id
- reservation_id
- amount
- method
- status
- provider
- provider_preference_id
- provider_payment_id nullable
- external_reference
- checkout_url
- created_by_user_id nullable
- paid_at nullable
- created_at
- updated_at
```

### Relación

``` text
Reservation 1 ---- N Payments
```

Se diseña como 1:N aunque inicialmente haya un checkout por el 50%, para
soportar reintentos, saldo restante, pagos manuales, distintos medios y
reembolsos.

## 13. Estado financiero calculado

No se necesita inicialmente duplicar `payment_status` en `reservations`.

Ejemplo:

``` text
total_price = 40.000
approved = 0       → UNPAID
approved = 20.000  → PARTIALLY_PAID
approved = 40.000  → PAID
```

Los pagos `PENDING`, `REJECTED` y `CANCELLED` no cuentan como dinero
abonado. Los reembolsos deberán descontarse del neto pagado.

## 14. Regla de seña

Inicialmente:

``` text
REQUIRED_DEPOSIT_PERCENTAGE = 50
```

Conceptualmente:

``` text
requiredDeposit = totalPrice × 50 / 100
```

A futuro podrá configurarse por club:

``` text
Club A → 50%
Club B → 30%
Club C → 100%
```

## 15. PaymentGateway

La lógica de aplicación no debe depender directamente de Mercado Pago.

``` php
interface PaymentGateway
{
    public function createCheckout(...): CheckoutResult;

    public function getPayment(...): PaymentGatewayResult;
}
```

Implementación:

``` text
Application / Domain
       ↑
PaymentGateway
       ↑
MercadoPagoPaymentGateway
```

Esto permitirá cambiar/agregar proveedores sin modificar la lógica
central.

## 16. Creación del checkout

Para cliente/guest:

1.  Validar cancha y horario.
2.  Validar disponibilidad.
3.  Calcular precio mediante `PriceResolver`.
4.  Crear `Reservation PENDING`.
5.  Asignar `expires_at = now + 15 minutos`.
6.  Guardar snapshot de segmentos de precio.
7.  Calcular el 50%.
8.  Crear checkout en Mercado Pago.
9.  Crear `Payment PENDING`.
10. Devolver `checkout_url`.

**El frontend nunca decide el monto.** El backend calcula el importe a
enviar al proveedor.

## 17. Respuesta conceptual

``` json
{
  "status": true,
  "message": "Reserva creada. Tenés 15 minutos para realizar el pago.",
  "data": {
    "court_id": 4,
    "starts_at": "2026-08-31 18:00:00",
    "ends_at": "2026-08-31 19:00:00",
    "total_price": "40000.00",
    "status": "PENDING",
    "expires_at": "2026-08-28 16:15:00",
    "payment": {
      "amount": "20000.00",
      "percentage": 50,
      "checkout_url": "https://..."
    }
  },
  "code": 201
}
```

Para guest se agrega `public_token`.

## 18. Concurrencia

Puede ocurrir simultáneamente:

``` text
ExpirePendingReservationsJob
```

y:

``` text
Webhook Payment APPROVED
```

Las operaciones críticas deberán ejecutarse dentro de transacciones y
utilizar bloqueo cuando corresponda. Nunca se debe permitir que dos
procesos tomen decisiones incompatibles sobre la misma reserva.

## 19. Cancelaciones y refunds

Las reglas actuales de cancelación continúan:

-   cliente autenticado: mínimo 24 horas;
-   guest: mínimo 24 horas;
-   staff: cancelación administrativa según reglas existentes.

Payments agrega la política financiera de devolución.

No se debe asumir automáticamente:

``` text
Reservation CANCELLED → Payment REFUNDED
```

hasta implementar explícitamente la política de reembolso.

## 20. Seguridad

-   No almacenar datos completos de tarjetas.
-   Credenciales de Mercado Pago mediante variables de entorno.
-   No confiar en montos enviados por frontend.
-   No confiar en el redirect del navegador como confirmación.
-   Validar la autenticidad del webhook.
-   Verificar el pago con el proveedor.
-   No usar `public_token` como referencia de pago.
-   No registrar access tokens, `public_token` ni payloads sensibles en
    logs.

## 21. Auditoría

El middleware HTTP existente podrá incluir:

``` php
'routes' => [
    'reservation.*',
    'payment.*',
],
```

para operaciones administrativas de Payments.

Los webhooks/jobs son procesos automáticos y podrán tener logging
técnico separado.

## 22. Arquitectura propuesta

``` text
app/
├── Application/
│   └── Payments/
│       ├── DTOs/
│       ├── Create/
│       ├── Approve/
│       ├── Refund/
│       └── Webhook/
├── Domain/
│   └── Payments/
│       ├── Entities/
│       │   └── Payment.php
│       ├── Enums/
│       │   ├── PaymentStatus.php
│       │   └── PaymentMethod.php
│       ├── Repositories/
│       │   └── PaymentRepository.php
│       ├── Services/
│       │   └── ReservationPaymentPolicy.php
│       └── Exceptions/
└── Infrastructure/
    └── Payments/
        ├── Repositories/
        │   └── EloquentPaymentRepository.php
        └── Gateways/
            └── MercadoPagoPaymentGateway.php
```

## 23. Endpoints previstos

Existentes que incorporarán checkout:

``` text
POST /api/courts/{court_id}/book
POST /api/public/courts/{court_id}/book
```

Staff:

``` text
POST /api/courts/{court_id}/reservations
```

Webhook:

``` text
POST /api/webhooks/mercadopago
```

Administración futura:

``` text
GET   /api/reservations/{reservation_id}/payments
GET   /api/payments/{id}
POST  /api/reservations/{reservation_id}/payments
PATCH /api/payments/{id}/refund
```

Permisos administrativos previstos:

``` text
payment.create
payment.view
payment.refund
```

Un cliente/guest nunca puede marcar arbitrariamente un pago como
`APPROVED`.

## 24. Tests necesarios

### Creación

-   Cliente obtiene checkout.
-   Guest obtiene checkout.
-   Checkout equivale al 50%.
-   Staff puede confirmar sin checkout.
-   El monto enviado al gateway coincide con el backend.

### Expiración

-   PENDING vigente bloquea.
-   PENDING vencida deja de bloquear.
-   Job cambia PENDING vencida a EXPIRED.

### Webhook

-   APPROVED confirma reserva vigente.
-   REJECTED no confirma.
-   PENDING no confirma.
-   Webhook repetido es idempotente.
-   APPROVED tardío no revive EXPIRED.
-   APPROVED sobre CANCELLED no confirma.

### Seguridad

-   Cliente/guest no eligen monto.
-   Webhook inválido se rechaza.
-   No se exponen secretos.
-   `public_token` no se usa como referencia de pago.

### Staff

-   Puede confirmar sin pago.
-   Reserva CONFIRMED puede tener \$0 abonado.
-   Estado de reserva y estado financiero son independientes.

## 25. Decisiones tomadas

1.  Cliente y guest requieren 50% para confirmar.
2.  La reserva autogestionada comienza `PENDING`.
3.  El plazo de pago es 15 minutos.
4.  `Reservation.expires_at` y el job local controlan la liberación de
    la cancha.
5.  La primera integración será Mercado Pago Checkout Pro.
6.  La aprobación se procesa mediante webhook verificado.
7.  Staff puede confirmar sin pago.
8.  `CONFIRMED` no significa necesariamente `PAID`.
9.  Una Reservation puede tener múltiples Payments.
10. Una reserva `EXPIRED` nunca se revive automáticamente por pago
    tardío.
11. La regla del 50% será centralizada y podrá configurarse por club.
12. Mercado Pago quedará detrás de `PaymentGateway`.
13. No se almacenan datos sensibles de tarjetas.
14. Payment y Reservation mantienen estados independientes.

## 26. Orden de implementación

``` text
1. Migration payments
2. PaymentStatus
3. PaymentMethod
4. Payment entity
5. PaymentRepository
6. Eloquent Payment model/repository
7. ReservationPaymentPolicy (50%)
8. PaymentGateway
9. DTOs del gateway
10. MercadoPagoPaymentGateway
11. Crear checkout al reservar cliente/guest
12. Persistir Payment PENDING
13. Devolver checkout_url
14. Webhook Mercado Pago
15. Verificación de pago
16. Confirmación automática de Reservation
17. Concurrencia e idempotencia
18. Tests
19. Refunds
20. Administración de pagos
```

## 27. Mejoras futuras

-   Porcentaje de seña configurable por club.
-   Tiempo de expiración configurable por club.
-   Pago del saldo restante.
-   Pagos manuales en efectivo/transferencia.
-   Reembolsos automáticos.
-   Crédito a favor.
-   Reportes de caja.
-   Conciliación con Mercado Pago.
-   Emails/WhatsApp con link de pago.
-   Regeneración controlada de checkout.
-   Reintentos sin duplicar la reserva.
