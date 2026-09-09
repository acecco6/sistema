# RESERVAS FIJAS — IMPLEMENTACIÓN BACKEND

> Estado real del módulo en `sistema-master(9).zip`.
> Actualizado: **08/09/2026**.
> Estado: **implementado, Job funcionando y tests pasando**.

---

# 1. Objetivo

Las reservas fijas representan recurrencias semanales administradas por el club.

Una serie puede tener uno o varios turnos semanales. Ejemplo:

```text
Cliente: Juan
Vigencia: desde 08/09/2026, sin fecha final

Slot 1
- lunes
- cancha 1
- 18:00
- 60 minutos

Slot 2
- martes
- cancha 1
- 18:00
- 120 minutos
```

La serie NO reemplaza a `reservations`.

Cada ocurrencia real se materializa como una `Reservation` normal para reutilizar:

- agenda;
- validación de disponibilidad;
- pricing;
- price segments;
- pagos;
- cancelaciones;
- estados;
- finalización automática;
- reportes futuros.

---

# 2. Modelo

## fixed_reservations

Representa la serie.

Campos principales:

```text
id
club_id
customer_user_id nullable
guest_name nullable
guest_email nullable
guest_phone nullable
created_by_user_id nullable
starts_on
ends_on nullable
active
notes nullable
timestamps
```

`ends_on = NULL` significa serie sin fecha final.

La identidad del cliente puede ser:

```text
customer_user_id
```

o snapshot guest:

```text
guest_name
guest_email
guest_phone
```

No deben coexistir ambas modalidades.

## fixed_reservation_slots

Representa cada turno semanal de la serie.

```text
id
fixed_reservation_id
court_id
day_of_week
start_time
duration_minutes
active
timestamps
```

`day_of_week` usa ISO-8601:

```text
1 lunes
2 martes
3 miércoles
4 jueves
5 viernes
6 sábado
7 domingo
```

Una serie puede tener múltiples slots, incluso en distintas canchas, siempre que las canchas pertenezcan al mismo Club.

Existe unique:

```text
fixed_reservation_id
court_id
day_of_week
start_time
```

## reservations

Las ocurrencias materializadas agregan:

```text
fixed_reservation_slot_id nullable
recurrence_date nullable
```

Una reserva común mantiene ambos campos en `NULL`.

La combinación:

```text
fixed_reservation_slot_id + recurrence_date
```

identifica una ocurrencia de forma idempotente.

Una ocurrencia cancelada permanece en `reservations`; por eso el generador no la recrea.

---

# 3. Creación

Endpoint:

```http
POST /api/clubs/{club_id}/fixed-reservations
```

Permission:

```text
fixed_reservation.create
```

Body:

```json
{
  "guest_name": "Cliente fijo",
  "guest_email": "cliente@example.com",
  "guest_phone": "111111111",
  "starts_on": "2026-09-14",
  "ends_on": null,
  "notes": "Reserva fija",
  "slots": [
    {
      "court_id": 1,
      "day_of_week": 1,
      "start_time": "18:00",
      "duration_minutes": 60
    },
    {
      "court_id": 1,
      "day_of_week": 2,
      "start_time": "18:00",
      "duration_minutes": 120
    }
  ]
}
```

También puede enviarse:

```text
customer_user_id
```

en lugar del snapshot guest.

Validaciones HTTP actuales:

- `starts_on` obligatorio y no anterior a hoy;
- `ends_on` opcional y >= `starts_on`;
- al menos un slot;
- court existente;
- `day_of_week` entre 1 y 7;
- `start_time` `H:i`;
- duración mínima 60 minutos.

Application además valida:

- serie con al menos un slot;
- court activa;
- branch activa;
- todas las courts pertenecen al Club de la serie;
- disponibilidad del horizonte inicial.

---

# 4. Alta estricta

`CreateFixedReservationWithOccurrencesHandler` realiza:

```text
Command
↓
FixedReservationScheduleValidator
↓
valida horizonte futuro
↓
CreateFixedReservationHandler
↓
guarda serie + slots
↓
GenerateFixedReservationOccurrences
↓
materializa Reservations
```

En el alta inicial:

```text
skipConflicts = false
```

Por lo tanto un conflicto de agenda hace fallar la generación en vez de ser ignorado.

Las ocurrencias fijas se crean como:

```text
status = confirmed
expires_at = NULL
```

No son `pending`, porque no deben expirar a los 15 minutos.

---

# 5. Generación rolling

Clase:

```text
GenerateFixedReservationOccurrences
```

El generador:

1. comprueba que la serie esté activa;
2. recorta el rango por `starts_on` / `ends_on`;
3. obtiene slots activos;
4. recorre las fechas;
5. compara `date->format('N')` con `day_of_week`;
6. consulta `existsFixedOccurrence(slot, recurrence_date)`;
7. construye `starts_at` y `ends_at`;
8. omite fechas pasadas;
9. usa `CreateReservationHandler`;
10. persiste la occurrence como Reservation normal.

El uso de `CreateReservationHandler` reutiliza las reglas reales de reserva, incluido overlap, intervalos, horario de Branch, pricing y persistencia de segmentos.

---

# 6. Job

Job:

```text
App\Jobs\GenerateFixedReservationOccurrencesJob
```

Implementa `ShouldQueue`.

Configuración:

```text
tries = 3
```

El Job toma una ventana de exactamente 8 semanas:

```text
from = today
to   = today + 8 semanas - 1 día
```

Busca series activas mediante:

```text
FixedReservationRepository::findActiveBetween()
```

y ejecuta:

```text
skipConflicts = true
```

El Scheduler actual lo registra en:

```text
routes/console.php
```

con ejecución diaria.

El Job se puede probar manualmente:

```php
\App\Jobs\GenerateFixedReservationOccurrencesJob::dispatchSync();
```

Si se modificaron clases mientras Tinker estaba abierto, cerrar y volver a abrir Tinker para no ejecutar clases ya cargadas.

---

# 7. Conflictos

Un conflicto futuro del Job NO debe detener toda la serie.

Ejemplo:

```text
14/09 libre     → occurrence
21/09 ocupado   → conflicto
28/09 libre     → occurrence
05/10 libre     → occurrence
```

El generador captura específicamente:

```text
CourtNotAvailableException
```

cuando está en modo Job.

No captura genéricamente todos los errores como si fueran conflictos de agenda.

## fixed_reservation_conflicts

Campos:

```text
id
fixed_reservation_id
fixed_reservation_slot_id
court_id
recurrence_date
starts_at
ends_at
reason
message nullable
resolved
resolved_by_user_id nullable
resolved_at nullable
timestamps
```

Unique:

```text
fixed_reservation_slot_id + recurrence_date
```

Esto evita duplicar alertas aunque el Job se ejecute muchas veces.

Reason actual:

```text
court_not_available
```

---

# 8. Ciclo de vida de un conflicto

Cuando el Job no puede crear una occurrence:

```text
CourtNotAvailableException
↓
FixedReservationConflictService::register()
↓
crea conflicto o reabre el existente
↓
Job continúa con la siguiente occurrence
```

Si el horario posteriormente queda libre:

```text
Job vuelve a intentar
↓
crea Reservation
↓
resolveOccurrence()
↓
resolved = true
resolved_by_user_id = NULL
resolved_at = timestamp
```

`resolved_by_user_id = NULL` significa resolución automática del sistema.

Un conflicto también puede marcarse resuelto manualmente por un usuario administrativo.

---

# 9. API

## Listar series

```http
GET /api/clubs/{club_id}/fixed-reservations
```

Route name:

```text
fixed_reservation.collection
```

## Crear

```http
POST /api/clubs/{club_id}/fixed-reservations
```

Route name:

```text
fixed_reservation.create
```

## Ver serie

```http
GET /api/fixed-reservations/{id}
```

Route name:

```text
fixed_reservation.view
```

## Desactivar

La ruta real actual usa:

```http
PATCH /api/fixed-reservations/{id}/desactivate
```

Route name:

```text
fixed_reservation.deactivate
```

Al desactivar:

- la serie pasa a inactive;
- busca Reservations futuras de esa serie;
- no toca CANCELLED / COMPLETED / EXPIRED;
- cancela las restantes.

## Listar conflictos

```http
GET /api/clubs/{club_id}/fixed-reservation-conflicts
```

Filtros:

```http
?resolved=0
?resolved=1
```

Sin filtro devuelve todos.

Route name:

```text
fixed_reservation_conflict.collection
```

## Resolver conflicto

```http
PATCH /api/fixed-reservation-conflicts/{id}/resolve
```

Route name / permission:

```text
fixed_reservation_conflict.resolve
```

---

# 10. DTO de serie

Respuesta `FixedReservationDto`:

```json
{
  "id": 1,
  "club_id": 1,
  "customer_user_id": null,
  "guest": {
    "name": "Cliente fijo",
    "email": "cliente@example.com",
    "phone": "111111111"
  },
  "created_by_user_id": 10,
  "starts_on": "2026-09-14",
  "ends_on": null,
  "active": true,
  "notes": "Reserva fija",
  "slots": [
    {
      "id": 1,
      "court_id": 1,
      "day_of_week": 1,
      "start_time": "18:00:00",
      "duration_minutes": 60,
      "active": true
    }
  ]
}
```

Si existe `customer_user_id`, `guest` es `null`.

---

# 11. DTO de conflicto

```json
{
  "id": 1,
  "fixed_reservation_id": 1,
  "fixed_reservation_slot_id": 1,
  "court_id": 1,
  "recurrence_date": "2026-09-21",
  "starts_at": "2026-09-21 18:00:00",
  "ends_at": "2026-09-21 19:00:00",
  "reason": "court_not_available",
  "message": "La cancha está ocupada en el horario de la reserva fija.",
  "resolved": false,
  "resolved_by_user_id": null,
  "resolved_at": null
}
```

---

# 12. Autorización

Permissions persistidos:

```text
fixed_reservation.create
fixed_reservation.view
fixed_reservation.deactivate
fixed_reservation_conflict.resolve
```

Las rutas `.collection` siguen el patrón especial del proyecto y se autorizan por membership/scope, no agregando un permiso collection a la base.

`CheckPermission` conoce:

```text
fixed_reservation
fixed_reservation_conflict
```

Las series están scopeadas a Club.

---

# 13. Tests

Suite:

```text
tests/Feature/Reservations/FixedReservations/
```

Incluye:

```text
FixedReservationTestCase
GenerateFixedReservationOccurrencesTest
FixedReservationConflictServiceTest
GenerateFixedReservationOccurrencesJobTest
```

Casos cubiertos:

- dos slots semanales;
- occurrences CONFIRMED;
- idempotencia;
- occurrence cancelada no se regenera;
- conflicto del Job se salta y el resto continúa;
- alta estricta falla ante conflicto;
- resolución automática cuando el horario queda libre;
- registro repetido no duplica conflicto;
- conflicto resuelto puede reabrirse;
- Job genera el resto de la ventana;
- ejecutar Job dos veces no duplica Reservations ni conflictos.

Estado informado al cierre del módulo:

```text
tests pasando
```

---

# 14. Reglas para cambios futuros

No convertir la serie en una única Reservation infinita.

No borrar una occurrence cancelada para “liberar” idempotencia.

No recrear una occurrence si ya existe `(slot, recurrence_date)`.

No hacer que un conflicto puntual del Job detenga toda la serie.

No convertir excepciones técnicas inesperadas en `court_not_available`.

No notificar automáticamente al cliente apenas aparece un conflicto sin definir antes el flujo operativo del club.

Para editar horarios de una serie, preservar historia: preferir desactivar slot anterior y crear un slot nuevo antes que mutar semánticamente el histórico.

---

# 15. Estado

```text
Reservas fijas             IMPLEMENTADO
Múltiples slots            IMPLEMENTADO
Materialización            IMPLEMENTADO
Horizonte rolling 8 semanas IMPLEMENTADO
Job                        IMPLEMENTADO
Idempotencia               IMPLEMENTADO
Conflictos persistentes    IMPLEMENTADO
Resolución automática      IMPLEMENTADO
Resolución manual          IMPLEMENTADO
Tests                      PASANDO
Frontend                   PENDIENTE
```

---

# 16. Bloqueo de superposiciones entre series

Al crear una reserva fija, el backend valida el calendario de series activas antes de persistirla. Rechaza con `409` cuando coinciden simultáneamente:

- misma cancha;
- mismo día de la semana;
- vigencias con al menos una ocurrencia común;
- horarios que se superponen.

Ejemplo bloqueado: Juan tiene cancha 1 martes 18:00–19:00; una nueva serie para Lucas incluye cancha 1 martes 18:00–19:00. Aunque la misma solicitud también tenga un slot válido en otra cancha, se rechaza toda la serie.

El mismo horario en canchas distintas sí es válido. La validación se realiza contra las series completas, no solo contra las occurrences ya materializadas en el horizonte de ocho semanas.
