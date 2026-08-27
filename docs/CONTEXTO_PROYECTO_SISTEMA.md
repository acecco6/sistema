# Estado del proyecto --- Sistema de gestión de clubes

> Documento de continuidad para retomar el proyecto en futuras
> conversaciones. Actualizado: 25/08/2026.

## 1. Objetivo del proyecto

Proyecto Laravel usado como práctica de backend avanzado, intentando
separar responsabilidades con una arquitectura inspirada en DDD / Clean
Architecture.

La intención no es solamente hacer funcionar un CRUD, sino practicar:

- PHP y Laravel a mayor profundidad.
- Diseño de dominio.
- Capas `Domain`, `Application`, `Infrastructure` y `Http`.
- Repositories.
- Commands / Queries / Handlers.
- DTOs.
- Excepciones de dominio.
- Autenticación con Laravel Sanctum.
- Roles y permisos.
- Autorización basada en memberships y scope.
- Feature tests y factories.
- Más adelante: reservas, transacciones, concurrencia, locks, índices
  y race conditions.

---

## 2. Arquitectura que estamos usando

La estructura general sigue este criterio:

```text
app/
├── Domain/
│   ├── Users/
│   ├── Clubs/
│   ├── Branches/
│   ├── Memberships/
│   ├── Roles/
│   └── Permissions/
│
├── Application/
│   ├── Auth/
│   ├── Authorization/
│   ├── Clubs/
│   ├── Branches/
│   ├── Memberships/
│   └── Roles/
│
├── Infrastructure/
│   └── Persistence/
│
└── Http/
    ├── Controllers/
    └── Middleware/
```

### Domain

Contiene las reglas y conceptos del negocio:

- Entities.
- Repository interfaces.
- Domain Exceptions.
- Value Objects cuando corresponde.

El Domain no debería depender de Eloquent ni de HTTP.

### Application

Contiene los casos de uso.

Patrón utilizado:

```text
Command / Query
        ↓
Handler
        ↓
Repository interfaces / Domain
```

Ejemplos existentes:

```text
Application/Clubs/Store
Application/Clubs/Update
Application/Branches/Get
Application/Branches/Store
Application/Memberships/Create
Application/Memberships/ChangeRole
Application/Memberships/ChangeBranche
Application/Memberships/ChangeStatus
Application/Roles/AssignPermission
```

### Infrastructure

Implementa los contratos definidos en Domain.

Ejemplo:

```text
Domain
MembershipRepository
        ↑
Infrastructure
EloquentMembershipRepository
```

Eloquent se mantiene principalmente en esta capa y en los Models de
Laravel.

### HTTP

Los Controllers reciben la request, validan los datos, crean
Commands/Queries y ejecutan Handlers.

La lógica de negocio no debería terminar concentrada en los Controllers.

---

# 3. Autenticación

La autenticación está implementada con Laravel Sanctum.

Rutas públicas:

```text
POST /api/auth/login
POST /api/auth/register
```

Ruta protegida:

```text
POST /api/auth/logout
```

Las demás rutas principales están dentro de:

```php
Route::middleware('auth:sanctum')
```

Se implementaron:

- Register.
- Login.
- Logout.
- Generación de tokens.
- Validación de tokens.
- Manejo de usuario inactivo.
- Respuestas JSON para errores de autenticación/validación.

También se crearon Feature Tests para:

```text
tests/Feature/Auth/RegisterTest.php
tests/Feature/Auth/LoginTest.php
tests/Feature/Auth/LogoutTest.php
```

Los tests de logout contemplan token válido, ausencia de autenticación,
token inválido y token expirado.

Para probar expiración de Sanctum, el test debe definir explícitamente:

```php
config([
    'sanctum.expiration' => 60,
]);
```

y modificar `created_at` del token para no depender de la configuración
local.

---

# 4. Clubs y Branches

Relación principal:

```text
Club
 └── Branch
```

Un Club puede tener varias Branches.

Actualmente existen casos de uso para:

## Clubs

- Collection.
- Show.
- Create.
- Update.
- Deactivate.

## Branches

- Collection por Club.
- Show.
- Create.
- Update.
- Deactivate.

Las operaciones de desactivación son lógicas mediante el campo:

```text
active
```

en lugar de eliminar necesariamente el registro físicamente.

---

# 5. Memberships

`Membership` es una pieza central de la autorización.

Una membership relaciona:

```text
User
Club
Role
Branch opcional
Active
```

Esquema conceptual:

```text
Membership
├── user_id
├── club_id
├── rol_id
├── branch_id nullable
└── active
```

IMPORTANTE: el proyecto decidió usar:

```text
rol_id
```

y no:

```text
role_id
```

Por eso las relaciones y foreign keys que no puedan ser inferidas
automáticamente por Laravel deben indicar explícitamente la
tabla/columna.

Ejemplo en `permission_role`:

```php
$table->foreignId('rol_id')
    ->constrained('roles')
    ->cascadeOnDelete();
```

---

# 6. Scope de una Membership

Hay dos tipos conceptuales de membership.

## Membership global

```text
branch_id = NULL
```

Significa que el usuario tiene ese rol para todo el Club.

Ejemplo:

```text
User
└── Club A
    └── Manager (GLOBAL)
```

Puede operar sobre todas las branches del Club, siempre sujeto a los
permisos de su Role.

## Membership por Branch

```text
branch_id = 3
```

Ejemplo:

```text
User
└── Club A
    ├── Branch 3 → Manager
    └── Branch 5 → Employee
```

El mismo usuario puede tener múltiples memberships dentro del mismo Club
y roles distintos según la Branch.

---

# 7. Reglas de conflicto de Memberships

Se implementó `hasConflictingMembership()`.

Reglas actuales:

### Crear/cambiar a membership global

Si `branch_id === null`, no puede coexistir otra membership del mismo
usuario en ese Club.

```text
GLOBAL + Branch específica = conflicto
```

### Crear/cambiar a membership de Branch

No puede existir:

- una membership global para ese User + Club;
- otra membership para la misma Branch.

Sí se permiten memberships para diferentes branches:

```text
Branch 1 → permitido
Branch 2 → permitido
```

Esto permite que un usuario tenga roles diferentes por sucursal.

---

# 8. Repository de Memberships

Contrato actual relevante:

```php
public function findById(int $id): ?Membership;

public function findForUserAndClub(
    int $userId,
    int $clubId,
    ?int $branchId = null
): ?Membership;

public function hasConflictingMembership(
    int $userId,
    int $clubId,
    ?int $branchId,
    ?int $excludeMembershipId = null
): bool;

public function findActiveForScope(
    int $userId,
    int $clubId,
    ?int $branchId = null
): ?Membership;

public function findActiveForClub(
    int $userId,
    int $clubId
): array;

public function hasActiveMemberships(int $userId): bool;
```

### Diferencia importante

`findActiveForScope()` devuelve una sola membership compatible con el
scope solicitado.

Para una Branch:

```text
busca membership global
OR
membership específica de esa Branch
```

`findActiveForClub()` devuelve un `array` porque el usuario puede tener
varias memberships activas dentro del mismo Club.

NO volver a tratar `findActiveForClub()` como si devolviera
`?Membership`.

---

# 9. Roles y Permissions

Los Roles son globales.

Roles iniciales:

```text
1 SuperAdmin
2 Admin
3 Manager
4 Employee
```

Los permisos se relacionan con roles mediante:

```text
permission_role
```

La FK del rol en este proyecto se llama:

```text
rol_id
```

La idea central es que el nombre de los permisos coincida con los
nombres de las rutas.

Ejemplos:

```text
club.view
club.create
club.update
club.deactivate

branch.view
branch.create
branch.update
branch.deactivate

membership.create
membership.change_status
membership.change_role
membership.change_branch
```

Esto permite que `CheckPermission` utilice:

```php
$request->route()?->getName()
```

como nombre del permiso requerido.

---

# 10. Convención especial `.collection`

Las rutas que devuelven colecciones utilizan:

```text
*.collection
```

Ejemplos actuales:

```php
->name('club.collection')
->name('branch.collection')
```

Estas rutas NO se autorizan exactamente igual que una operación
individual.

Razón:

```text
GET /clubs
```

no tiene un `club_id` concreto.

Y:

```text
GET /clubs/{club_id}/branches
```

debe devolver solamente las Branches que están dentro del scope del
usuario.

Por eso `CheckPermission` detecta:

```php
str_ends_with($routeName, '.collection')
```

y deriva hacia autorización específica de colecciones.

---

# 11. CheckPermission

Middleware central:

```text
App\Http\Middleware\CheckPermission
```

Responsabilidades actuales:

1.  Obtener usuario autenticado.
2.  Obtener nombre de ruta.
3.  Detectar rutas `.collection`.
4.  Resolver el scope de Club / Branch / Membership.
5.  Delegar la autorización a `AuthorizationService`.

Resolvers actuales:

```text
resolveClubScope()
resolveBranchScope()
resolveMembershipScope()
```

Recursos soportados actualmente:

```php
match ($resource) {
    'club' => ...
    'branch' => ...
    'membership' => ...
}
```

Cuando agreguemos nuevos recursos, por ejemplo Courts, habrá que evaluar
si necesitan un nuevo resolver.

---

# 12. AuthorizationService

Servicio:

```text
App\Application\Authorization\AuthorizationService
```

Tiene dos conceptos principales.

## Autorización por scope

```php
can(
    int $userId,
    int $clubId,
    ?int $branchId,
    string $permission
): bool
```

Utiliza:

```text
findActiveForScope()
```

Sirve para operaciones concretas sobre recursos que pertenecen a un
Club/Branch.

## Autorización dentro de un Club

```php
canInClub(
    int $userId,
    int $clubId,
    string $permission
): bool
```

Utiliza:

```text
findActiveForClub()
```

Como `findActiveForClub()` devuelve varias memberships, se recorren
todas y alcanza con que alguna membership activa tenga un Role con el
permiso solicitado.

Conceptualmente:

```text
Membership 1 → Role Manager  → permiso X ✅
Membership 2 → Role Employee → permiso X ❌

Resultado → permitido
```

También existe:

```php
authorizeInClub(...)
```

que lanza `AuthorizationDeniedException` si `canInClub()` devuelve
`false`.

---

# 13. Caso especial `club.view`

Se decidió que una persona con membership limitada a una Branch sí pueda
consultar información general del Club al que pertenece.

Por eso:

```text
club.view
```

usa autorización a nivel Club mediante `authorizeInClub()` y no exige
necesariamente una membership global.

En cambio, acciones que afectan a TODO el Club, como:

```text
club.update
club.deactivate
```

deben respetar el scope correspondiente y no deberían ser concedidas
simplemente por pertenecer a una Branch.

---

# 14. Collections y filtrado

La autorización de una colección no significa devolver todos los
registros.

Ejemplo:

```text
GET /clubs/{club_id}/branches
```

Si el usuario tiene:

```text
Branch 1
Branch 3
```

debe obtener:

```text
Branch 1
Branch 3
```

y no todas las Branches del Club.

Si tiene membership global:

```text
branch_id = NULL
```

puede obtener todas las Branches del Club.

Esta lógica se aplica en el caso de uso/repository, además de la
autorización del middleware.

---

# 15. Atención: detalle pendiente detectado en CheckPermission (YA CORREGIDO)

Actualmente `MembershipRepository::findActiveForClub()` devuelve:

```php
array
```

pero en `CheckPermission::authorizeBranchCollection()` todavía aparece
una comprobación del estilo:

```php
$membership = $this->memberships->findActiveForClub(...);

if ($membership === null) {
    throw new AuthorizationDeniedException();
}
```

Esto quedó desactualizado después de cambiar el repository para soportar
múltiples memberships.

Debe revisarse y utilizar una comprobación compatible con array, por
ejemplo conceptualmente:

```php
if ($memberships === []) {
    throw new AuthorizationDeniedException();
}
```

Además conviene renombrar la variable a `$memberships`.

Este punto queda marcado como pendiente/revisión.

---

# 16. Create Club

La ruta de creación de Club quedó como caso especial:

```php
Route::post('', CreateClubController::class)
    ->withoutMiddleware('permission')
    ->name('club.create');
```

La razón es que para crear el primer Club todavía no existe una
Membership dentro de ese Club contra la cual autorizar.

Este caso debe mantenerse presente cuando se revise el modelo de
permisos globales/SuperAdmin.

---

# 17. Excepciones

Existe una excepción base:

```php
App\Shared\Exceptions\DomainException
```

Las excepciones específicas del dominio extienden de ella.

Se decidió que las excepciones de dominio puedan tener definido su
código HTTP para traducirse a respuestas API.

También existe:

```text
AuthorizationDeniedException
```

para representar acceso denegado.

La configuración global de excepciones maneja respuestas JSON para:

- AuthenticationException.
- ValidationException.
- Domain/Authorization exceptions según configuración.

Objetivo: evitar repetir `try/catch` innecesarios en todos los
Controllers cuando el error puede resolverse globalmente.

---

# 18. Factories

Ya existen factories para:

```text
User
Club
Branch
Membership
Role
Permission
PermissionRole
```

Estados/helpers importantes:

## ClubFactory

```php
->inactive()
```

## BranchFactory

```php
->inactive()
```

## MembershipFactory

```php
->global()
->inactive()
->forBranch($branch)
```

Ejemplo recomendado:

```php
Membership::factory()
    ->for($user)
    ->for($club)
    ->for($role)
    ->forBranch($branch)
    ->createOne();
```

Membership global:

```php
Membership::factory()
    ->for($user)
    ->for($club)
    ->for($role)
    ->global()
    ->createOne();
```

Role con permiso:

```php
Role::factory()
    ->withPermission('branch.update')
    ->createOne();
```

---

# 19. Testing

Se empezó a incorporar testing desde cero usando Feature Tests.

Se usa:

```php
use RefreshDatabase;
```

Los tests corren con SQLite en memoria.

Configuración esperada:

```text
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Fue necesario habilitar en PHP CLI:

```text
pdo_sqlite
sqlite3
```

## Tests existentes

```text
tests/Feature/Auth/RegisterTest.php
tests/Feature/Auth/LoginTest.php
tests/Feature/Auth/LogoutTest.php

tests/Feature/Authorization/BranchAuthorizationTest.php

tests/Feature/Clubs/ClubTest.php
tests/Feature/Branches/BranchTest.php
```

Los nombres de las funciones de tests se escriben en español:

```php
test_usuario_puede_...
test_usuario_no_puede_...
test_membresia_global_...
```

El código, clases y conceptos técnicos siguen mayormente en inglés.

---

# 20. Reglas de autorización ya testeadas

Entre los escenarios trabajados están:

```text
Membership global
→ puede ver todas las Branches del Club.

Membership de Branch
→ solamente ve las Branches asignadas.

Dos memberships de Branch
→ ve ambas Branches.

Scope correcto + permiso
→ operación permitida.

Scope incorrecto + permiso
→ 403.

Scope correcto + sin permiso
→ 403.

Membership inactiva
→ no concede acceso.

Membership limitada a Branch
→ no permite actualizar todo el Club.

Membership global + club.update
→ permite actualizar el Club.
```

También se agregaron tests funcionales para Clubs y Branches, además de
Auth.

Siempre ejecutar al terminar cambios relevantes:

```bash
php artisan test
```

La regla es mantener toda la suite en verde antes de avanzar.

---

# 21. Convenciones para escribir tests

Preferir:

```php
/** @var User $user */
$user = User::factory()->createOne();
```

cuando el IDE no infiere correctamente el tipo concreto.

Los tests deberían seguir mentalmente:

```text
Arrange
↓
Act
↓
Assert
```

Las factories preparan el escenario; los assertions verifican
comportamiento.

No probar solamente status HTTP. Cuando corresponda, comprobar también
estado persistido:

```php
$this->assertDatabaseHas(...)
$this->assertDatabaseMissing(...)
$this->assertDatabaseCount(...)
```

Ejemplo: un login fallido no solamente debe responder 401; tampoco debe
crear un token.

---

# 22. Próximo módulo: Courts

El siguiente módulo acordado es:

```text
Courts / Canchas
```

Jerarquía:

```text
Club
└── Branch
    ├── Court
    │   └── TipoCourt
    │
    └── IntervalTimeTipoCourt
        └── TipoCourt
```

Estructura prevista:

```text
Domain/Courts/
├── Entities/
│   └── Court.php
├── Repositories/
│   └── CourtRepository.php
└── Exceptions/

Application/Courts/
├── Create/
├── Show/
├── Collection/
├── Update/
└── Deactivate/
```

Campos iniciales propuestos:

```text
id
branch_id
name
active
```

Permisos propuestos:

```text
court.view
court.create
court.update
court.deactivate
```

La autorización deberá reutilizar el modelo existente:

```text
User
→ Membership
→ Club/Branch scope
→ Role
→ Permission
```

Ejemplo:

```text
POST /branches/{branch_id}/courts

requiere:
membership con scope sobre esa Branch
+
court.create
```

También habrá que extender `CheckPermission` para resolver el scope de
un `court`.

---

# 23. Después de Courts: Reservations

Una vez cerrado Courts y sus tests, el siguiente módulo importante será
Reservations.

Orden previsto:

```text
Courts
↓
Tests de Courts
↓
Reservations básico
↓
Disponibilidad
↓
Evitar doble reserva
↓
Transactions
↓
Concurrencia
↓
SELECT ... FOR UPDATE / locking
↓
Race conditions
↓
Deadlocks y retries
↓
Índices
↓
EXPLAIN y optimización SQL
```

Este módulo será utilizado para profundizar especialmente en backend y
MySQL.

---

# 24. Temas pendientes

Prioridad aproximada:

1.  Crear módulo Courts. (terminado)
2.  Crear migration/model/entity/repository de Court. (terminado)
3.  Crear Application use cases de Court. (terminado)
4.  Agregar permisos `court.*`. (terminado)
5.  Extender autorización para resolver scope de Court. (terminado)
6.  Crear `CourtFactory`. (terminado)
7.  Crear Feature Tests de Courts. (terminado)
8.  Diseño de como implementar Precios y promociones por dia y hora.
9.  Comenzar Reservations.
10. Diseñar reglas para evitar reservas superpuestas.
11. Introducir transacciones y concurrencia.
12. Analizar índices MySQL y consultas con `EXPLAIN`.
13. Más adelante evaluar Unit Tests puros del Domain además de Feature
    Tests.

---

# 25. Criterio que venimos usando al desarrollar

No agregar abstracciones solamente por agregarlas.

Antes de implementar una solución:

1.  Identificar la regla de negocio.
2.  Determinar a qué capa pertenece.
3.  Evitar meter reglas del dominio en Controllers.
4.  Mantener Eloquent fuera del Domain.
5.  Reutilizar AuthorizationService en lugar de duplicar autorización.
6.  Diferenciar autorización de una acción y filtrado de una colección.
7.  Escribir tests para reglas importantes.
8.  Ejecutar la suite completa antes de seguir.

Cuando aparezca un problema nuevo de autorización, primero pensar:

```text
¿Qué recurso estoy intentando usar?
↓
¿A qué Club pertenece?
↓
¿A qué Branch pertenece, si corresponde?
↓
¿Qué memberships activas tiene el usuario?
↓
¿Alguna membership cubre ese scope?
↓
¿Qué Role tiene en esa membership?
↓
¿Ese Role posee el permiso de la ruta?
```

Ese es el modelo mental principal del sistema.

---

# 26. Contexto para una próxima conversación

Al retomar este proyecto, NO empezar de cero.

La base ya implementada incluye:

```text
Auth con Sanctum
Clubs
Branches
Memberships
Roles
Permissions
AuthorizationService
CheckPermission
Factories
Feature Tests
```

El sistema soporta memberships globales y memberships por Branch,
incluso múltiples memberships por Club.

El próximo objetivo principal es **Courts**, y luego **Reservations**
con foco en conceptos avanzados de backend, base de datos y
concurrencia.

Antes de continuar, revisar este documento junto con el estado actual
del repositorio porque el código puede haber avanzado desde la última
actualización.
