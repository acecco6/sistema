# BACKEND — PENDIENTES PARA COMPLEMENTAR EL FRONTEND

Fuente revisada: `sistema-master(9).zip` — 08/09/2026.

## P0 — prioridad alta

1. **Bootstrap de sesión/autorización**
   - `GET /api/me/context`
   - usuario + memberships + role + permissions efectivos.

2. **Collection/detalle de memberships**
   - `GET /api/clubs/{club_id}/memberships`
   - `GET /api/memberships/{id}`
   - incluir usuario, rol y branch.

3. **Roles y permisos**
   - `GET /api/roles`
   - `GET /api/roles/{id}/permissions`.

4. **Búsqueda de usuarios/clientes**
   - `GET /api/users?search=...` o recurso Customer equivalente.
   - imprescindible para reservas, reservas fijas y memberships.

5. **Tipos de cancha**
   - `GET /api/court-types`.
   - devolver `id`, `name`, `description`.

## P1 — muy recomendado

6. **Intervalos de cancha**
   - GET/PATCH de `interval_time_tipo_court` por branch + tipo.

7. **Estado de Mercado Pago por Club**
   - `GET /api/clubs/{club_id}/mercado-pago`.
   - nunca exponer tokens.

8. **Reservation DTO enriquecido**
   - `fixed_reservation_slot_id`
   - `recurrence_date`
   - ideal: `source`
   - customer/guest normalizado para mostrar nombres sin requests extra.

9. **Dashboard agregado**
   - reservas del día
   - ocupación
   - próximos turnos
   - pagos/saldos
   - refunds pendientes
   - conflictos de reservas fijas.

10. **Normalizar auth/errors**
    - eliminar `dd()` de RegisterController;
    - respetar 401/403;
    - usar envelope consistente.

11. **Configurar horario del Job**
    - evitar hora hardcodeada de desarrollo;
    - mover a config/env o definir horario de producción.

## P2 — crecimiento

12. **Paginación/filtros** para memberships, customers, históricos, payments/refunds y fixed reservations.

13. **Feed HTTP de notificaciones** solo si el panel tendrá campana/inbox.

14. **Edición de reservas fijas** con contrato explícito y preservación de histórico.

15. **Normalizar URLs heredadas**
    - `/memberships/{id}/branche`
    - `/fixed-reservations/{id}/desactivate`
    - mantener compatibilidad si se renombran.

## Orden sugerido

```text
/me/context
↓
memberships + roles/permisos
↓
users/customers search
↓
court-types
↓
interval config
↓
auth/error cleanup
↓
Reservation DTO
↓
Mercado Pago status
↓
dashboard
↓
paginación/notificaciones/edición
```
