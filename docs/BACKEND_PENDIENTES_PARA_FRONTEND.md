# Estado de pendientes para el frontend

Actualizado: **09/09/2026**.

## Resuelto

- bootstrap `GET /api/me/context`;
- collection y detalle de memberships, con filtros y paginación;
- catálogo de roles y permissions;
- búsqueda paginada de usuarios;
- catálogo de tipos de cancha;
- lectura y modificación de intervalos;
- estado seguro de Mercado Pago por club;
- Reservation DTO enriquecido y cliente/guest normalizado;
- dashboard operativo por branch;
- eliminación de `dd()` y normalización del 401;
- horario del Job configurable por env;
- URLs canónicas con compatibilidad para las heredadas.
- modelo `Customer` global + `ClubCustomer` por club, compatible con clientes sin cuenta y multi-club;
- CRUD/búsqueda paginada de clientes por club;
- integración de `club_customer_id` en reservas normales, autenticadas y fijas;
- verificación y reenvío de email, con bloqueo de login no verificado.

## Postergado conscientemente

- inbox HTTP: cuando el panel incorpore campana y estados leído/no leído;
- edición de reservas fijas: falta definir el impacto sobre occurrences creadas, versionado de slots e histórico;
- paginación de todos los históricos financieros: memberships y usuarios ya están paginados; payments/refunds/fixed reservations pueden migrarse cuando el frontend consuma tablas grandes.

Contratos completos: `docs/BACKEND_APIS_FRONTEND.md`.

Ideas de siguiente etapa: `docs/PROPUESTAS_BACKEND_SIGUIENTE_ETAPA.md`.
