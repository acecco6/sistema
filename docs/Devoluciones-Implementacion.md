# Devoluciones de pagos — Implementación actual

> Estado: **implementado y cubierto por tests**.
> Actualizado: **01/09/2026**.

## 1. Objetivo

El módulo de devoluciones registra y administra dinero que debe ser devuelto a un cliente cuando corresponde.

El sistema **no ejecuta movimientos de dinero**. Una transferencia, devolución manual por Mercado Pago, efectivo, tarjeta u otro medio ocurre fuera del backend. El backend registra la obligación y luego la confirmación administrativa de que el dinero fue devuelto.

Conceptos separados:

```text
Payment       = dinero recibido
PaymentRefund = dinero que debe ser o fue devuelto
```

El `Payment` original se conserva para mantener el historial financiero.

## 2. Estados

`RefundStatus`:

```text
PENDING
COMPLETED
CANCELLED
```

- `PENDING`: existe una obligación de devolver dinero, pero todavía no fue confirmada como realizada.
- `COMPLETED`: el personal confirmó que el dinero fue devuelto.
- `CANCELLED`: la obligación fue anulada sin borrar el historial.

Las transiciones de estado inválidas lanzan `InvalidRefundStatusTransitionException`.

## 3. Modelo PaymentRefund

La tabla `payment_refunds` contiene:

```text
id
reservation_id
payment_id nullable
amount decimal(12,2)
status
reason nullable
method nullable
notes nullable
created_by_user_id nullable
completed_by_user_id nullable
completed_at nullable
created_at
updated_at
```

`reservation_id` es obligatorio. `payment_id` es nullable porque la devolución puede representar una obligación global sobre el dinero cobrado de la reserva y no necesariamente sobre un único `Payment`.

La entidad de dominio `PaymentRefund` no depende de Eloquent.

## 4. Cancelación administrativa con devolución

La cancelación administrativa acepta:

```json
{
    "create_refund": true,
    "refund_reason": "Cancelación autorizada por administración"
}
```

La existencia de pagos no genera automáticamente una devolución. La decisión es explícita mediante `create_refund`.

Cuando `create_refund = true`, `CancelReservationHandler` ejecuta en una misma transacción:

```text
lock Reservation
↓
cancelar Reservation
↓
calcular monto todavía reembolsable
↓
crear PaymentRefund PENDING si corresponde
↓
commit
```

Si no existe dinero aprobado disponible para devolver, no se crea un refund.

## 5. Cálculo del monto reembolsable

Solo se considera dinero efectivamente aprobado:

```text
approved_payments = suma de Payments APPROVED
```

Para impedir obligaciones duplicadas:

```text
committed_refunds = Refunds PENDING + COMPLETED

refundable_amount = approved_payments - committed_refunds
```

Los refunds `CANCELLED` no forman parte del monto comprometido.

Regla principal:

```text
total refunds comprometidos <= total approved payments
```

Los cálculos monetarios utilizan strings decimales y BCMath, no `float`.

## 6. Completar una devolución

Endpoint:

```text
PATCH /api/refunds/{id}/complete
route name: refund.complete
```

Payload:

```json
{
    "method": "TRANSFER",
    "notes": "Transferencia realizada al cliente"
}
```

Al completarse:

```text
status = COMPLETED
method = método informado
completed_by_user_id = usuario autenticado
completed_at = fecha/hora actual
notes = opcional
```

`CompleteRefundHandler` usa `findByIdForUpdate()` dentro de una transacción para proteger la transición concurrente.

El endpoint no ejecuta la devolución externa.

## 7. Consultas administrativas

Detalle:

```text
GET /api/refunds/{id}
route name: refund.view
```

Listado por sucursal:

```text
GET /api/branches/{branch_id}/refunds
route name: refund.collection
```

Filtro opcional:

```text
GET /api/branches/{branch_id}/refunds?status=PENDING
```

El listado se resuelve mediante `PaymentRefundRepository::findByBranch()`.

`refund.collection` sigue la regla general del proyecto para colecciones: **no es un permiso persistido en DB**. Se valida que la membership activa cubra la Branch solicitada.

## 8. Autorización

Permisos administrativos persistidos:

```text
refund.view
refund.complete
```

`CheckPermission` resuelve el scope de un refund recorriendo:

```text
PaymentRefund
↓
Reservation
↓
Court
↓
Branch
↓
Club / Membership scope
```

Una membership global del Club cubre todas sus Branches. Una membership específica solo cubre su Branch.

## 9. DTO

Las consultas y comandos HTTP no exponen directamente la entidad de dominio. Se utiliza `PaymentRefundDto` con datos como:

```text
id
reservation_id
payment_id
amount
status
reason
method
notes
created_by_user_id
completed_by_user_id
completed_at
```

## 10. Integración con el resumen financiero

El resumen financiero ya contempla devoluciones completadas.

Campos actuales:

```text
total_price
approved_amount
required_deposit
refunded_amount
net_paid_amount
remaining_amount
financial_status
```

Reglas:

```text
approved_amount = suma de Payments APPROVED
refunded_amount = suma de PaymentRefund COMPLETED
net_paid_amount = approved_amount - refunded_amount
remaining_amount = max(total_price - net_paid_amount, 0)
```

`net_paid_amount` se protege para nunca exponerse como negativo ante datos históricos inconsistentes.

El `financial_status` se calcula usando `net_paid_amount`, no el total histórico aprobado.

Estados financieros actuales:

```text
UNPAID
PARTIALLY_PAID
DEPOSIT_PAID
PAID
OVERPAID
```

Ejemplo:

```text
Reserva total          40000.00
Payments APPROVED      40000.00
Refund COMPLETED       20000.00
--------------------------------
approved_amount        40000.00
refunded_amount        20000.00
net_paid_amount        20000.00
remaining_amount       20000.00
financial_status       DEPOSIT_PAID
```

Un refund `PENDING` o `CANCELLED` **no modifica** el resumen financiero neto. Solo `COMPLETED` representa dinero efectivamente devuelto.

## 11. Payment original y PaymentStatus::REFUNDED

El flujo manual de refunds no modifica automáticamente el `Payment` original.

Ejemplo:

```text
Payment
  amount = 20000.00
  status = APPROVED

PaymentRefund
  amount = 20000.00
  status = COMPLETED
```

Interpretación:

```text
dinero recibido = 20000.00
dinero devuelto = 20000.00
dinero neto     =     0.00
```

Aunque `PaymentStatus` contiene `REFUNDED`, el flujo manual implementado usa `PaymentRefund` como fuente de verdad de las devoluciones.

## 12. Repository

Contrato actual relevante:

```php
interface PaymentRefundRepository
{
    public function findById(int $id): ?PaymentRefund;
    public function findByIdForUpdate(int $id): ?PaymentRefund;
    public function findByReservation(int $reservationId): array;
    public function findPending(): array;
    public function save(PaymentRefund $refund): PaymentRefund;
    public function update(PaymentRefund $refund): PaymentRefund;
    public function sumCommittedByReservation(int $reservationId): string;
    public function sumCompletedByReservation(int $reservationId): string;
    public function findByBranch(int $branchId, ?RefundStatus $status = null): array;
}
```

`sumCommittedByReservation()` sirve para impedir refunds duplicados/excesivos.

`sumCompletedByReservation()` alimenta el resumen financiero real.

## 13. Tests cubiertos

El módulo tiene cobertura sobre:

```text
PaymentRefund entity y transiciones
EloquentPaymentRefundRepository
cancelación + creación opcional de refund
prevención de refunds superiores al dinero aprobado
CompleteRefundHandler
PATCH /refunds/{id}/complete
GET /refunds/{id}
GET /branches/{branch_id}/refunds
filtro por status
permisos y scope entre Branches
refund PENDING no modifica net_paid
refund COMPLETED modifica net_paid
refund total vuelve UNPAID
solo COMPLETED participa del resumen
refund de otra Reservation no afecta
refund puede corregir un estado OVERPAID
```

La suite completa se encuentra verde al cierre de esta implementación.

## 14. Decisiones que no deben cambiarse accidentalmente

1. El sistema registra refunds; no mueve dinero.
2. `Payment` y `PaymentRefund` son conceptos separados.
3. La cancelación administrativa decide explícitamente `create_refund`.
4. Crear un refund `PENDING` no significa que el dinero haya sido devuelto.
5. Solo `COMPLETED` reduce el dinero neto cobrado.
6. `PENDING + COMPLETED` comprometen monto e impiden duplicar obligaciones.
7. `CANCELLED` no compromete monto.
8. El `Payment` original se conserva.
9. Cancelación + creación del refund son atómicas.
10. Completar un refund usa lock/transacción.
11. El estado financiero se calcula sobre `net_paid_amount`.
12. Los importes se calculan con decimal/BCMath, nunca con float.
