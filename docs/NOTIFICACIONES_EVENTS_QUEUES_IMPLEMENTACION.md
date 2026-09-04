# Implementación de Events, Notifications, Queues y Email Logs

## 1. Objetivo

Incorporar notificaciones al sistema sin acoplar el envío de emails a los handlers principales de negocio.

La idea es que las operaciones importantes continúen siendo síncronas y responsables únicamente de mantener la consistencia del dominio y la base de datos, mientras que los efectos secundarios —por ejemplo enviar un email— se ejecuten mediante eventos, listeners y colas.

El flujo objetivo será:

```text
Acción de negocio
    ↓
Handler
    ↓
Persistencia / COMMIT
    ↓
Event
    ↓
Listener ShouldQueue
    ↓
EmailLog PENDING
    ↓
Notification / Mail
    ↓
EmailLog SENT o FAILED
```

La configuración del mailer seguirá dependiendo exclusivamente del `.env`:

```text
MAIL_MAILER=log
```

para desarrollo rápido, o:

```text
MAIL_MAILER=smtp
```

para probar los emails visualmente mediante Mailtrap Sandbox.

---

## 2. Principios de implementación

### 2.1 El negocio no depende del email

Crear, confirmar o cancelar una reserva, aprobar un pago o completar una devolución debe seguir funcionando aunque el proveedor SMTP esté caído.

Por eso no se utilizará `Mail::send()` ni `Notification::send()` directamente dentro de los handlers principales.

### 2.2 Los eventos representan hechos que ya ocurrieron

Los eventos se nombrarán en pasado o como hechos consumados:

```text
ReservationConfirmed
ReservationCancelled
ReservationExpired
PaymentApproved
PaymentRejected
RefundCreated
RefundCompleted
```

El evento no decide qué hacer después. Solamente comunica que algo ya sucedió.

### 2.3 Los efectos secundarios irán a Queue

Los listeners encargados de email implementarán `ShouldQueue`.

De esta forma una petición HTTP no queda esperando al servidor SMTP.

### 2.4 Los eventos con efectos externos deben ejecutarse después del COMMIT

Esto es especialmente importante porque Payments, Refunds y Cancellation utilizan transacciones.

Nunca debemos enviar un email si después la operación termina en rollback.

Ejemplo incorrecto:

```text
BEGIN
    Payment APPROVED
    enviar email
    ERROR
ROLLBACK
```

El usuario habría recibido una confirmación de algo que nunca quedó persistido.

### 2.5 EmailLog es auditoría, no reemplaza el mailer

La tabla `email_logs` registrará los intentos de envío independientemente de si usamos:

```text
MAIL_MAILER=log
```

o:

```text
MAIL_MAILER=smtp
```

Esto nos permitirá saber qué correo intentó enviar el sistema, a quién, cuándo y si falló.

---

## 3. Estructura de carpetas propuesta

Se mantendrá la arquitectura actual del proyecto.

### Events

```text
app/Application/Reservations/Events/
    ReservationConfirmed.php
    ReservationCancelled.php
    ReservationExpired.php

app/Application/Payments/Events/
    PaymentApproved.php
    PaymentRejected.php

app/Application/Payments/Refunds/Events/
    RefundCreated.php
    RefundCompleted.php
```

Por el momento los eventos vivirán en Application porque nacen como resultado de casos de uso y coordinan efectos secundarios de aplicación.

### Listeners

```text
app/Application/Notifications/Listeners/
    SendReservationConfirmedNotification.php
    SendReservationCancelledNotification.php
    SendReservationExpiredNotification.php
    SendPaymentApprovedNotification.php
    SendPaymentRejectedNotification.php
    SendRefundCreatedNotification.php
    SendRefundCompletedNotification.php
```

Los listeners de email implementarán `ShouldQueue`.

### Notifications

```text
app/Application/Notifications/Mail/
    ReservationConfirmedNotification.php
    ReservationCancelledNotification.php
    ReservationExpiredNotification.php
    PaymentApprovedNotification.php
    PaymentRejectedNotification.php
    RefundCreatedNotification.php
    RefundCompletedNotification.php
```

Estas clases serán responsables de construir asunto, contenido y datos del correo.

### Email logging

```text
app/Domain/Notifications/
    Entities/EmailLog.php
    Enums/EmailLogStatus.php
    Repositories/EmailLogRepository.php

app/Infrastructure/Persistence/Eloquent/Notifications/
    EloquentEmailLogRepository.php

app/Models/
    EmailLog.php
```

### Migración

```text
database/migrations/
    xxxx_xx_xx_xxxxxx_create_email_logs_table.php
```

### Tests

```text
tests/Feature/Notifications/
    ReservationConfirmedNotificationTest.php
    ReservationCancelledNotificationTest.php
    ReservationExpiredNotificationTest.php
    RefundNotificationTest.php

tests/Unit/Notifications/
    EmailLogTest.php
```

---

## 4. Tabla `email_logs`

La tabla propuesta será:

```php
Schema::create('email_logs', function (Blueprint $table) {
    $table->id();

    $table->string('to_email');
    $table->string('subject');

    $table->string('notification_type')->nullable();
    $table->string('template')->nullable();

    $table->json('payload')->nullable();

    $table->string('status');

    $table->text('error_message')->nullable();

    $table->timestamp('sent_at')->nullable();

    $table->timestamps();

    $table->index('status');
    $table->index('to_email');
});
```

### Estados

```php
enum EmailLogStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
}
```

### Qué se guarda en `payload`

No se guardará inicialmente el HTML completo del email.

Se guardarán solamente identificadores y datos relevantes para auditoría, por ejemplo:

```json
{
    "reservation_id": 150,
    "club_id": 3,
    "branch_id": 5,
    "starts_at": "2026-09-10 20:00:00"
}
```

Así evitamos duplicar contenido pesado en base de datos.

---

## 5. Primer flujo a implementar: ReservationConfirmed

Este será el primer flujo vertical completo y servirá como patrón para los demás.

```text
Reserva confirmada
        ↓
ReservationConfirmed
        ↓
SendReservationConfirmedNotification
        ↓
Queue
        ↓
crear EmailLog PENDING
        ↓
ReservationConfirmedNotification
        ↓
MAIL_MAILER
        ↓
EmailLog SENT / FAILED
```

### Por qué empezar por ReservationConfirmed

Actualmente una reserva puede llegar a `CONFIRMED` por más de un camino:

```text
Admin confirma manualmente
    ↓
ConfirmReservationHandler
```

O:

```text
Mercado Pago aprueba la seña
    ↓
ProcessMercadoPagoWebhookHandler
    ↓
tryConfirmReservation()
```

El evento debe emitirse únicamente cuando la reserva realmente cambió a `CONFIRMED`.

Esto evita duplicar lógica de notificación entre ambos caminos.

---

## 6. Lugares actuales que vamos a modificar

### 6.1 Confirmación manual

Archivo actual:

```text
app/Application/Reservations/Confirm/ConfirmReservationHandler.php
```

Hoy hace:

```text
buscar reserva
    ↓
reservation->confirm()
    ↓
repository->update()
```

Después de persistir correctamente se incorporará el evento `ReservationConfirmed`.

### 6.2 Confirmación por Mercado Pago

Archivo actual:

```text
app/Application/Payments/Webhooks/ProcessMercadoPagoWebhookHandler.php
```

Actualmente:

```text
webhook aprobado
    ↓
Payment APPROVED
    ↓
tryConfirmReservation()
    ↓
Reservation CONFIRMED
```

El evento `ReservationConfirmed` solamente deberá dispararse si `confirmFromPayment()` efectivamente confirmó la reserva.

También podremos emitir `PaymentApproved` en este flujo, pero no será parte de la primera implementación para evitar meter varias notificaciones a la vez.

### 6.3 Cancelación administrativa

Archivo actual:

```text
app/Application/Reservations/Cancel/CancelReservationHandler.php
```

Actualmente utiliza una transacción y puede crear un `PaymentRefund PENDING`.

Más adelante este handler podrá producir:

```text
ReservationCancelled
```

Y, cuando realmente se cree una devolución:

```text
RefundCreated
```

Ambos deberán respetar el COMMIT de la transacción.

### 6.4 Refund completado

Archivo actual:

```text
app/Application/Payments/Refunds/CompleteRefund/CompleteRefundHandler.php
```

Después de completar y persistir el refund podrá producir:

```text
RefundCompleted
```

La notificación resultante podrá informar al cliente que la devolución fue registrada como completada.

### 6.5 Expiración de reservas

Ya existe:

```text
app/Jobs/ExpirePendingReservationsJob.php
```

Y ya se ejecuta desde:

```text
routes/console.php
```

con:

```text
Schedule::job(new ExpirePendingReservationsJob())->everyMinute();
```

Después de que una reserva cambie realmente a `EXPIRED`, el job podrá emitir:

```text
ReservationExpired
```

Este es un buen ejemplo de que `Job` y `Event` cumplen responsabilidades distintas:

```text
Scheduler
    ↓
Job que busca y expira reservas
    ↓
Event ReservationExpired
    ↓
Listener
    ↓
Notification
```

---

## 7. Destinatarios de las notificaciones

### Usuario registrado

Si la reserva posee:

```text
customer_user_id
```

se utilizará el usuario registrado como destinatario.

### Guest

Si la reserva corresponde a un invitado, se utilizará:

```text
guest_email
```

mediante una notificación enrutable por email.

No se creará un `User` artificial para invitados.

### Notificaciones internas

Algunos eventos serán más útiles para empleados del club que para clientes.

Ejemplo principal:

```text
RefundCreated
```

Un refund `PENDING` representa dinero que todavía debe ser devuelto manualmente, por lo que posteriormente podremos notificar a usuarios con scope de Admin/Manager de la sucursal.

Esto se implementará después de cerrar el primer flujo de email al cliente.

---

## 8. Contenido inicial de los emails

### ReservationConfirmed

Debe incluir como mínimo:

```text
Club
Sucursal
Cancha
Fecha
Hora inicio
Hora fin
Precio total
Monto pagado
Saldo pendiente
```

Los valores financieros deberían salir del servicio existente:

```text
app/Application/Payments/Services/ReservationPaymentSummaryService.php
```

Así evitamos volver a implementar reglas financieras dentro de la Notification.

### ReservationCancelled

Datos previstos:

```text
Reserva
Fecha / horario
Cancha
Monto pagado
Estado de devolución si corresponde
```

### ReservationExpired

Debe indicar que la reserva temporal expiró porque no fue confirmada dentro del período permitido.

### RefundCreated

Inicialmente orientada a operación interna:

```text
refund_id
reservation_id
amount
reason
branch
```

### RefundCompleted

Para el cliente:

```text
Monto devuelto
Método registrado
Fecha de devolución
Reserva relacionada
```

---

## 9. Configuración de Queue

La primera implementación utilizará queue de base de datos.

`.env`:

```env
QUEUE_CONNECTION=database
```

Será necesario verificar/crear las tablas estándar de Laravel para jobs fallidos y pendientes según la instalación actual.

Worker local:

```bash
php artisan queue:work
```

Para desarrollo pueden mantenerse dos terminales:

```text
Terminal 1
php artisan serve

Terminal 2
php artisan queue:work
```

Si el listener falla, el request que confirmó la reserva no deberá revertirse. El trabajo quedará registrado como fallido/reintentable según la configuración de Queue.

---

## 10. Configuración de Mail

No se acoplará código a Mailtrap.

El código utilizará el mailer configurado en Laravel.

### Desarrollo mediante log

```env
MAIL_MAILER=log
```

Los emails se verán en:

```text
storage/logs/laravel.log
```

### Desarrollo visual mediante Mailtrap Sandbox

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=no-reply@sistema.test
MAIL_FROM_NAME="${APP_NAME}"
```

Cambiar de una opción a otra no debe requerir modificaciones en Events, Listeners o Notifications.

---

## 11. Manejo de `EmailLog`

El listener realizará conceptualmente:

```text
1. Resolver destinatario
2. Crear EmailLog PENDING
3. Intentar enviar Notification
4. Si funciona:
       EmailLog -> SENT
       sent_at = now
5. Si falla:
       EmailLog -> FAILED
       error_message = excepción
       relanzar excepción para que Queue pueda reintentar
```

Es importante relanzar la excepción luego de registrar el fallo para no romper el comportamiento normal de retry de Laravel Queue.

### Consideración sobre reintentos

Un mismo job puede ejecutarse más de una vez.

Antes de cerrar la implementación debemos definir cómo evitaremos crear múltiples filas engañosas por un mismo intento lógico.

La opción prevista es agregar al `EmailLog` una referencia estable al evento/job, por ejemplo:

```text
event_id / message_key
```

si durante la implementación vemos que los retries necesitan idempotencia explícita.

No se agregará hasta comprobar que realmente lo necesitamos.

---

## 12. Registro de Events y Listeners

El proyecto utiliza Laravel 13.

Antes de crear un `EventServiceProvider` manual se verificará el mecanismo actual de event discovery del framework y del proyecto.

La implementación debe evitar crear configuración duplicada si Laravel ya descubre automáticamente los listeners ubicados dentro de `app`.

El objetivo es conservar la configuración mínima necesaria.

---

## 13. Tests obligatorios del primer flujo

### Event

Debemos comprobar que una confirmación real dispara:

```text
ReservationConfirmed
```

Casos:

```text
confirmación manual
confirmación mediante pago aprobado
```

Y que no se dispara cuando la reserva no cambia de estado.

### Listener / Notification

Con:

```php
Notification::fake();
```

comprobar:

```text
usuario registrado recibe notification
Guest recibe notification por guest_email
```

### Queue

Comprobar que el listener implementa `ShouldQueue` y que el envío se procesa como trabajo en cola.

### EmailLog

Casos mínimos:

```text
envío exitoso -> SENT
sent_at informado
recipient correcto
subject correcto
payload correcto

fallo del envío -> FAILED
error_message informado
```

### Transacciones

Agregar test que garantice que no se produzca una notificación si la operación que debía generar el evento termina en rollback.

---

## 14. Orden de implementación

### Etapa 1 — Infraestructura base

```text
EmailLogStatus
EmailLog entity
EmailLogRepository
migration email_logs
Eloquent model
Eloquent repository
binding de repository
factory/tests básicos
```

### Etapa 2 — Queue

```text
QUEUE_CONNECTION=database
migraciones de queue si faltan
queue worker
prueba simple de job
```

### Etapa 3 — Primer Event

```text
ReservationConfirmed
```

Integrarlo en:

```text
ConfirmReservationHandler
ProcessMercadoPagoWebhookHandler
```

sin duplicar notificaciones.

### Etapa 4 — Primera Notification

```text
ReservationConfirmedNotification
SendReservationConfirmedNotification
```

Debe soportar:

```text
usuario registrado
guest
```

### Etapa 5 — EmailLog

Integrar el registro `PENDING -> SENT / FAILED` alrededor del envío.

### Etapa 6 — Tests completos

Cerrar el primer flujo hasta dejar toda la suite verde.

### Etapa 7 — Replicar patrón

Una vez validado ReservationConfirmed:

```text
ReservationCancelled
ReservationExpired
PaymentApproved
PaymentRejected
RefundCreated
RefundCompleted
```

---

## 15. Eventos previstos y prioridad

| Evento | Destinatario inicial | Canal | Queue | Prioridad |
|---|---|---|---|---|
| ReservationConfirmed | Cliente/guest | Mail | Sí | Alta |
| ReservationCancelled | Cliente/guest | Mail | Sí | Alta |
| ReservationExpired | Cliente/guest | Mail | Sí | Media |
| PaymentApproved | Cliente/guest | Mail | Sí | Media |
| PaymentRejected | Cliente/guest | Mail | Sí | Media |
| RefundCreated | Admin/Manager | Mail / futura database | Sí | Alta |
| RefundCompleted | Cliente/guest | Mail | Sí | Alta |

No implementaremos todos simultáneamente. Esta tabla define el alcance futuro.

---

## 16. Posible segunda etapa: notificaciones internas

Después de cerrar emails podremos aprovechar Laravel Database Notifications para la campana del frontend.

Endpoints futuros posibles:

```http
GET /api/notifications
PATCH /api/notifications/{id}/read
PATCH /api/notifications/read-all
```

Casos útiles:

```text
Nueva reserva
Pago aprobado
Reserva cancelada
Refund pendiente
```

Esta funcionalidad queda explícitamente fuera de la primera implementación.

---

## 17. Resultado esperado

Al terminar esta implementación el sistema debería poder hacer lo siguiente:

```text
Admin confirma reserva
        ↓
Reserva CONFIRMED persistida
        ↓
COMMIT exitoso
        ↓
ReservationConfirmed
        ↓
Listener agregado a Queue
        ↓
EmailLog PENDING
        ↓
Email enviado usando MAIL_MAILER configurado
        ↓
EmailLog SENT
```

Y ante una caída de SMTP:

```text
Reserva sigue CONFIRMED
EmailLog FAILED
Queue puede reintentar
error_message queda registrado
```

Con esta estructura las reglas del negocio quedan desacopladas de servicios externos y podremos agregar canales futuros —database notifications, WhatsApp u otros— sin modificar los handlers principales.
