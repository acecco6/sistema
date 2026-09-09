# APIs de soporte para el frontend

Actualizado: **09/09/2026**.

Todos los endpoints requieren `Authorization: Bearer <token>` y utilizan el envelope estándar `status`, `message`, `data` y `code`.

## Contexto de sesión

```http
GET /api/me/context
```

Devuelve `user`, memberships activas enriquecidas con club, branch, role y permissions, más `effective_permissions` sin duplicados. Para decisiones dependientes del scope usar los permisos de cada membership; `effective_permissions` sirve para navegación general.

## Memberships

```http
GET /api/clubs/{club_id}/memberships
GET /api/memberships/{id}
```

Filtros: `search`, `branch_id`, `role_id`, `active`, `page`, `per_page` (máximo 100). La collection devuelve `items` y `pagination`.

Permiso: `membership.view`. Una membership global puede ver todo el club; una membership de branch solo ve memberships de sus branches autorizadas.

## Roles y permisos

```http
GET /api/roles
GET /api/roles/{id}/permissions
```

Son catálogos autenticados. El segundo endpoint devuelve el role y su lista de permisos.

## Búsqueda de usuarios

Ruta recomendada:

```http
GET /api/clubs/{club_id}/users?search=alejo&branch_id=1&page=1&per_page=20
```

Compatibilidad: `GET /api/users?club_id=1&branch_id=1&search=alejo`.

Permiso: `user.view`. `branch_id` es necesario para operadores con scope de sucursal; un administrador global puede omitirlo. Por defecto se devuelven usuarios activos. Nunca se incluyen passwords ni tokens.

## Tipos e intervalos de cancha

```http
GET /api/court-types
GET   /api/branches/{branch_id}/court-types/{court_type_id}/interval
PATCH /api/branches/{branch_id}/court-types/{court_type_id}/interval
```

Body: `{ "interval_minutes": 20 }`. Debe estar entre 5 y 240 y ser múltiplo de 5. Si no existe configuración, GET devuelve el default `30` sin crear una fila.

Permisos: `court_interval.view`, `court_interval.update`.

## Mercado Pago

```http
GET /api/clubs/{club_id}/mercado-pago
```

Devuelve estado de conexión, vigencia, seller ID y public key. Nunca devuelve `access_token` ni `refresh_token`.

Permiso: `club.mercado_pago.view`.

## Reservation DTO enriquecido

Las respuestas administrativas agregan `customer`, `guest`, `fixed_reservation_slot_id`, `recurrence_date` y `source`. `source` vale `manual` o `fixed_reservation`. Se mantienen temporalmente `customer_user_id` y `guest_*` por compatibilidad.

## Dashboard operativo

```http
GET /api/branches/{branch_id}/dashboard?date=2026-09-09
```

Si se omite `date`, usa la fecha actual. Devuelve reservas agrupadas por estado, ocupación, próximos 10 turnos, pagos aprobados, refunds pendientes y conflictos de reservas fijas sin resolver.

Permiso: `dashboard.view`.

## Compatibilidad de URLs

Rutas recomendadas:

```http
PATCH /api/memberships/{id}/branch
PATCH /api/fixed-reservations/{id}/deactivate
```

Las heredadas `/branche` y `/desactivate` continúan funcionando con el mismo permiso.

## Scheduler

La generación diaria de reservas fijas se configura con `FIXED_RESERVATIONS_JOB_TIME=00:10`. Después de cambiarla en producción, limpiar la caché de configuración.

## Clientes y cuentas

`User` es la identidad de autenticación; `Customer` es la persona global; `ClubCustomer` es su relación con cada club. Un cliente puede no tener User y un Customer puede estar asociado a varios clubes.

```http
GET   /api/clubs/{club_id}/customers
POST  /api/clubs/{club_id}/customers
GET   /api/clubs/{club_id}/customers/{id}
PUT   /api/clubs/{club_id}/customers/{id}
PATCH /api/clubs/{club_id}/customers/{id}/status
```

La collection admite `search`, `active`, `page` y `per_page`. Las reservas nuevas deben enviar `club_customer_id`; `customer_user_id` queda como compatibilidad.

## Verificación de email

```http
GET  /api/auth/email/verify/{id}/{hash}           # URL firmada
POST /api/auth/email/verification-notification    # autenticado
```

El registro envía la notificación. Un email no verificado recibe `403` al intentar login. `/api/user` y `/api/me/context` incluyen `email_verified` y `email_verified_at`.
