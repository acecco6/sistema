# Devoluciones de pagos

## 1. Objetivo

El módulo de devoluciones permite registrar y administrar dinero que debe ser
devuelto a un cliente cuando una reserva es cancelada.

El sistema NO realiza automáticamente transferencias, devoluciones de Mercado
Pago ni movimientos de dinero.

Su responsabilidad es:

- determinar cuándo existe una devolución pendiente;
- registrar el monto que debe devolverse;
- mostrar las devoluciones pendientes al personal autorizado;
- permitir que un administrador confirme que la devolución fue realizada;
- mantener trazabilidad de quién generó y quién completó la devolución.

---

# 2. Conceptos principales

Los pagos y las devoluciones son conceptos separados.

Un `Payment` representa dinero recibido.

Un `PaymentRefund` representa dinero que debe ser o fue devuelto.

Ejemplo:

Reserva:

    total_price = 40000.00

Pagos:

    Mercado Pago = 20000.00 APPROVED

Si la reserva se cancela y corresponde devolver el dinero:

    PaymentRefund
        amount = 20000.00
        status = PENDING

El Payment original NO se elimina ni se modifica.

Esto permite conservar el historial financiero completo.

---

# 3. El sistema no ejecuta devoluciones

Las devoluciones son realizadas manualmente por el personal del club.

Por ejemplo:

- transferencia bancaria;
- efectivo;
- devolución manual mediante Mercado Pago;
- tarjeta;
- otro medio.

El backend solamente registra el estado de esa devolución.

Por lo tanto:

    Refund PENDING

significa:

    "Hay dinero que debe ser devuelto."

Mientras que:

    Refund COMPLETED

significa:

    "El personal confirmó que ese dinero ya fue devuelto."

---

# 4. Estados de una devolución

Inicialmente existirán los siguientes estados:

## PENDING

La devolución debe realizarse.

El dinero todavía no fue marcado como devuelto.

## COMPLETED

El personal confirmó que la devolución fue realizada.

Debe registrarse:

- quién la completó;
- cuándo fue completada;
- método utilizado;
- opcionalmente notas.

## CANCELLED

La obligación de devolución fue anulada.

Este estado permite conservar el registro histórico sin eliminarlo.

---

# 5. Cancelación de reservas

Las reglas actuales de cancelación de reservas continúan existiendo.

Una reserva no puede ser cancelada normalmente dentro de las 24 horas previas
a su comienzo.

La cancelación administrativa puede tener reglas especiales.

Cuando un administrador cancela manualmente una reserva que tiene dinero
cobrado, debe indicar explícitamente si corresponde generar una devolución.

Ejemplo:

    create_refund = true

o:

    create_refund = false

La existencia de pagos NO implica automáticamente que deban devolverse.

La decisión corresponde al administrador que realiza la cancelación.

---

# 6. Cancelación sin dinero pagado

Ejemplo:

Reserva:

    total_price = 40000.00

Pagos aprobados:

    0.00

El administrador cancela:

    create_refund = true

Como no existe dinero recibido, no debe generarse ningún PaymentRefund.

La reserva simplemente queda cancelada.

---

# 7. Cancelación con dinero pagado y sin devolución

Ejemplo:

Reserva:

    total_price = 40000.00

Pagos aprobados:

    20000.00

Cancelación:

    create_refund = false

Resultado:

    Reservation = CANCELLED

No se crea ningún PaymentRefund.

El Payment original continúa:

    Payment = APPROVED
    amount = 20000.00

Esto representa que el club recibió ese dinero y decidió no devolverlo.

---

# 8. Cancelación con devolución

Ejemplo:

Reserva:

    total_price = 40000.00

Pagos aprobados:

    20000.00

Cancelación:

    create_refund = true

Resultado:

    Reservation = CANCELLED

    PaymentRefund
        amount = 20000.00
        status = PENDING

La creación del refund NO significa que el dinero haya sido devuelto.

Solamente registra la obligación.

---

# 9. Múltiples pagos

Una reserva puede tener múltiples Payments.

Ejemplo:

    Mercado Pago    10000.00 APPROVED
    CASH              5000.00 APPROVED
    TRANSFER           5000.00 APPROVED

Total aprobado:

    20000.00

Si el administrador cancela la reserva indicando:

    create_refund = true

el monto a devolver será calculado a partir del dinero efectivamente aprobado.

No deben contabilizarse pagos:

- PENDING
- REJECTED
- CANCELLED

---

# 10. Cálculo del monto a devolver

No se debe crear una devolución simplemente usando el total de la reserva.

Debe utilizarse el dinero realmente recibido.

Conceptualmente:

    refundable_amount =
        approved_payments
        - existing_refunds

Para evitar duplicar obligaciones, deben considerarse tanto devoluciones
pendientes como completadas.

Ejemplo:

Pagos aprobados:

    25000.00

Refund COMPLETED:

    5000.00

Refund PENDING:

    10000.00

Monto todavía disponible para generar como nueva devolución:

    25000 - 5000 - 10000
    =
    10000.00

---

# 11. Prevención de devoluciones duplicadas

El sistema nunca debe permitir generar obligaciones de devolución superiores
al dinero efectivamente recibido.

Por lo tanto:

    total refunds <= total approved payments

Los refunds existentes en estado PENDING y COMPLETED deben participar del
cálculo.

Esto también protege contra solicitudes repetidas o concurrencia.

---

# 12. Transacción al cancelar una reserva

Cuando una cancelación administrativa solicita crear una devolución:

    cancelar Reservation
            +
    crear PaymentRefund PENDING

debe ejecutarse dentro de la misma transacción de base de datos.

Esto evita estados inconsistentes como:

    Reservation = CANCELLED

pero:

    no existe Refund

aunque correspondía devolver dinero.

La operación debe ser atómica.

---

# 13. Registro de la devolución

Cuando el personal realmente devuelve el dinero, debe confirmar la operación.

Ejemplo:

    PATCH /api/refunds/{id}/complete

Payload aproximado:

    {
        "method": "TRANSFER",
        "notes": "Transferencia realizada al alias informado por el cliente"
    }

Entonces:

    status = COMPLETED
    completed_by_user_id = usuario autenticado
    completed_at = now()

El sistema NO realiza la transferencia.

Solamente registra que el administrador declaró que fue realizada.

---

# 14. Trazabilidad

Cada devolución debe permitir conocer como mínimo:

- reserva;
- monto;
- estado;
- motivo;
- quién generó la devolución;
- quién confirmó la devolución;
- cuándo fue completada;
- método utilizado para devolver el dinero;
- notas opcionales.

Esto permite reconstruir posteriormente el historial financiero de una reserva.

---

# 15. Modelo PaymentRefund

La entidad prevista será:

    PaymentRefund

Campos principales:

    id
    reservation_id
    payment_id nullable
    amount
    status
    reason nullable
    method nullable
    notes nullable
    created_by_user_id
    completed_by_user_id nullable
    completed_at nullable
    created_at
    updated_at

`reservation_id` es obligatorio.

`payment_id` puede ser nullable porque una devolución puede representar una
obligación general sobre el dinero cobrado de una reserva y no necesariamente
una devolución individual de un Payment.

---

# 16. Payment original

Crear o completar una devolución NO debe eliminar el Payment original.

Ejemplo:

    Payment
        amount = 20000
        status = APPROVED

    PaymentRefund
        amount = 20000
        status = COMPLETED

Ambos registros deben permanecer.

Esto permite interpretar correctamente:

    dinero recibido = 20000
    dinero devuelto = 20000
    dinero neto retenido = 0

---

# 17. Estado REFUNDED de Payment

Aunque actualmente `PaymentStatus` contiene `REFUNDED`, el flujo de
devoluciones manuales no dependerá de cambiar automáticamente el Payment a
REFUNDED.

La fuente de verdad sobre las devoluciones será `PaymentRefund`.

Esto es necesario porque una reserva puede:

- tener múltiples pagos;
- tener devoluciones separadas;
- tener devoluciones pendientes;
- conservar el historial original de los cobros.

El uso futuro de `PaymentStatus::REFUNDED` deberá revisarse antes de utilizarlo
en este flujo.

---

# 18. Resumen financiero

Actualmente el resumen financiero utiliza pagos APPROVED para calcular:

    total_price
    approved_amount
    required_deposit
    remaining_amount
    financial_status

Al incorporar devoluciones será necesario distinguir entre:

    dinero cobrado
    dinero devuelto
    dinero neto

Conceptualmente:

    approved_amount = suma de Payments APPROVED

    refunded_amount = suma de PaymentRefund COMPLETED

    net_paid_amount =
        approved_amount - refunded_amount

Esta integración se realizará después de implementar el módulo de refunds.

No debe modificarse el cálculo actual hasta que el módulo de devoluciones esté
implementado y cubierto por tests.

---

# 19. APIs previstas

## Devoluciones pendientes

    GET /api/refunds?status=PENDING

Permitirá al personal autorizado consultar dinero pendiente de devolución.

La respuesta debería incluir información suficiente para identificar:

- refund;
- reserva;
- cliente;
- monto;
- fecha de la reserva;
- motivo.

---

## Detalle

    GET /api/refunds/{id}

Permitirá consultar el detalle de una devolución.

---

## Completar devolución

    PATCH /api/refunds/{id}/complete

Marca una devolución como realizada.

No ejecuta ninguna operación financiera externa.

---

# 20. Permisos previstos

Se crearán permisos independientes para administrar devoluciones.

Inicialmente:

    refund.view
    refund.complete

La creación de un Refund no necesariamente tendrá un endpoint público o
administrativo independiente.

En el flujo principal será generado por el caso de uso de cancelación de una
reserva cuando:

    create_refund = true

---

# 21. Flujo completo esperado

Ejemplo:

    Reserva #150
    total = 40000

            ↓

    Cliente paga seña
    Payment = 20000 APPROVED

            ↓

    Reservation = CONFIRMED

            ↓

    Administrador cancela manualmente

            ↓

    create_refund = true

            ↓

    Transaction:

        Reservation → CANCELLED

        PaymentRefund
            amount = 20000
            status = PENDING

            ↓

    Refund aparece en bandeja:

        GET /api/refunds?status=PENDING

            ↓

    Administrador realiza transferencia manualmente

            ↓

        PATCH /api/refunds/{id}/complete

            ↓

    PaymentRefund:

        status = COMPLETED
        method = TRANSFER
        completed_by_user_id = admin
        completed_at = fecha/hora

            ↓

    Historial financiero:

        cobrado:  20000
        devuelto: 20000
        neto:         0

---

# 22. Principios del módulo

1. El sistema registra devoluciones, no mueve dinero.

2. Payment y PaymentRefund representan movimientos conceptualmente diferentes.

3. Una cancelación administrativa debe indicar explícitamente si corresponde
   devolución.

4. Tener dinero pagado no significa automáticamente que deba devolverse.

5. Crear un Refund no significa que el dinero ya fue devuelto.

6. Solamente un Refund COMPLETED representa una devolución confirmada.

7. Nunca se debe permitir devolver más dinero del efectivamente cobrado.

8. Las operaciones financieras deben usar decimal/BCMath y nunca float.

9. La cancelación y creación de la obligación de devolución deben ser
   transaccionales.

10. Todo el flujo debe conservar trazabilidad del usuario que ejecutó cada
    acción.
