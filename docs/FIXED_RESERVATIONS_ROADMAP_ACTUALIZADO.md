# ROADMAP FRONTEND — RESERVAS FIJAS Y CONFLICTOS

Actualizado: **09/09/2026**.

## Cambio requerido en RF-4

La selección de cliente ya no debe consultar `/users`. Debe listar `/api/clubs/{club_id}/customers` y enviar `club_customer_id`.

```ts
interface CreateFixedReservationPayload {
  club_customer_id: number
  starts_on: string
  ends_on?: string | null
  notes?: string | null
  slots: Array<{
    court_id: number
    day_of_week: 1 | 2 | 3 | 4 | 5 | 6 | 7
    start_time: string
    duration_minutes: number
  }>
}
```

`customer_user_id` y los campos `guest_*` continúan aceptados únicamente como compatibilidad. Para una nueva serie creada desde el panel, primero crear/seleccionar el ClubCustomer y enviar su `id`.

El backend rechaza toda la creación con `409` si alguno de los slots se superpone con una serie fija activa de la misma cancha, día de la semana y vigencia. El mismo horario en otra cancha es válido.

## Contrato de respuesta actualizado

`FixedReservation` agrega `club_customer_id: number | null`. Las occurrences generadas copian ese vínculo a cada Reservation. Esto permite mantener la identidad aunque el cliente no tenga cuenta y conservar la relación correcta si pertenece a varios clubes.

## Flujo UI

1. Restaurar sesión y club seleccionado.
2. Cargar permisos `fixed_reservation.*` y `customer.view`.
3. Buscar ClubCustomer activo.
4. Si no existe, abrir alta rápida sin cuenta.
5. Enviar `club_customer_id` con slots y vigencia.
6. Mostrar conflictos; el frontend no materializa occurrences.

Las rutas de series/conflictos y la semántica de generación permanecen iguales a la versión anterior del roadmap.
