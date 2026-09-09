# FRONTEND ROADMAP GENERAL — SPORTDESK

Actualizado: **09/09/2026** contra esta entrega del backend.

## Cambio de modelo obligatorio

`User` ya no representa a un cliente del club. Los conceptos son:

- `User`: identidad autenticable; necesita email verificado para iniciar sesión.
- `Customer`: persona global, con `user_id` opcional.
- `ClubCustomer`: relación de un Customer con un Club. Un mismo Customer puede estar en varios clubes.
- `Membership`: relación laboral/administrativa del User con un club o sucursal.

El selector de clientes de reservas debe consumir `/api/clubs/{club_id}/customers`; `/users` queda para agregar personal a memberships.

## Autenticación y verificación

```http
POST /api/auth/register
POST /api/auth/login
POST /api/auth/forgot-password
POST /api/auth/reset-password
POST /api/auth/logout
POST /api/auth/email/verification-notification
GET  /api/auth/email/verify/{id}/{hash}
GET  /api/user
GET  /api/me/context
```

Después del registro mostrar “revisá tu email”. Un login sin verificar devuelve `403`. La URL de verificación es firmada y el reenvío requiere Bearer token. `UserSummary` incorpora:

```ts
email_verified: boolean
email_verified_at: string | null
```

### Recuperación de contraseña

Pantallas necesarias: “Olvidé mi contraseña” y “Nueva contraseña”. La primera envía `{ email }` a `POST /api/auth/forgot-password`; mostrar siempre la confirmación de envío, aunque el email no exista. La segunda toma `token` y `email` de la URL recibida y envía:

```ts
{ email, token, password, confirm_password }
```

a `POST /api/auth/reset-password`. Un `422` indica token inválido/vencido o validación. Al éxito, limpiar estado de sesión y redirigir a login: la operación revoca todos los tokens Bearer del usuario.

## Entrada al dashboard

Después de login o al restaurar un Bearer, el frontend debe consumir `GET /api/me/context` antes de navegar. Si `memberships.length` es mayor a cero, puede seleccionar un contexto y acceder al dashboard cuando la membership seleccionada tenga `dashboard.view`. Si es cero, el User quedó autenticado pero no tiene acceso operativo: bloquear dashboard y rutas administrativas, mostrar `sin-acceso-operativo` y permitir únicamente cerrar sesión. Esta condición se decide por `context.memberships`, no por rol, `/api/user` ni `effective_permissions` globales.

## Clientes por club

```http
GET   /api/clubs/{club_id}/customers?search=&active=&page=1&per_page=20
POST  /api/clubs/{club_id}/customers
GET   /api/clubs/{club_id}/customers/{club_customer_id}
PUT   /api/clubs/{club_id}/customers/{club_customer_id}
PATCH /api/clubs/{club_id}/customers/{club_customer_id}/status
```

Permisos: `customer.view`, `customer.create`, `customer.update`, `customer.change_status`.

DTO de lista/detalle:

```ts
interface ClubCustomer {
  id: number                 // club_customer_id
  club_id: number
  customer_id: number        // identidad global
  user_id: number | null
  has_account: boolean
  email_verified: boolean
  name: string
  email: string | null
  phone: string | null
  active: boolean
  notes: string | null
  first_reservation_at: string | null
  last_reservation_at: string | null
  created_at: string | null
  updated_at: string | null
}
```

Alta sin cuenta: `{ name, email?, phone?, notes? }`. Alta desde una cuenta verificada: `{ user_id, notes? }`. Asociación explícita de un Customer global: `{ customer_id, notes? }`. El email normalizado es único y, al verificar una cuenta con el mismo email, backend la vincula automáticamente al Customer sin cuenta. El teléfono no se usa para asociar ni fusionar.

## Reservas

En altas administrativas normales y fijas usar:

```ts
club_customer_id: number
```

No usar `customer_user_id` en código frontend nuevo. Se conserva temporalmente en backend para datos históricos. Un cliente sin cuenta es perfectamente válido. Las respuestas de Reservation y FixedReservation incluyen `club_customer_id`.

## Orden sugerido

1. Verificación de email y manejo de `403`.
2. Contexto real y selección de club/sucursal.
3. CRUD/selector de clientes por club.
4. Reemplazar selectores de User en reservas por ClubCustomer.
5. Integrar reservas fijas, conflictos, agenda y dashboard.

Antes de cerrar cada fase ejecutar `pnpm typecheck`, `pnpm lint`, `pnpm test:run` y `pnpm build`.
