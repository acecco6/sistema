# Módulo Courts + TipoCourt

Completar el módulo **Courts** siguiendo exactamente la arquitectura del proyecto (Domain → Application → Infrastructure → Http), incluyendo la asociación con `TipoCourt` y la extensión de `CheckPermission`.

La base ya tiene: migration, entidad Court, CourtRepository (interfaz), EloquentCourtRepository, Eloquent models (Court + TipoCourt) y las excepciones.

Lo que falta es: use cases (Application), controllers (Http), rutas, seeder de permisos, binding en AppServiceProvider y extensión de CheckPermission.

---

## Estado actual del código

| Archivo | Estado |
|---|---|
| migration `tipos_court` | ✅ existe |
| migration `courts` | ✅ existe |
| migration `interval_time_tipo_court` | ✅ existe |
| `Domain/Courts/Entities/Court.php` | ✅ existe (con `tipoCourtId`) |
| `Domain/Courts/Repositories/CourtRepository.php` | ✅ existe |
| `Domain/Courts/Exceptions/*` | ✅ 3 excepciones existen |
| `Infrastructure/Persistence/EloquentCourtRepository.php` | ✅ existe |
| `Models/Court.php` | ✅ existe |
| `Models/TipoCourt.php` | ✅ existe |
| `Application/Courts/*` | ❌ falta todo |
| `Http/Controllers/Courts/*` | ❌ falta todo |
| Rutas court.* en api.php | ❌ falta |
| Binding CourtRepository en AppServiceProvider | ❌ falta |
| CheckPermission: case 'court' | ❌ falta |
| Seeder permisos court.* | ❌ falta |
| CourtFactory + TipoCourtFactory | ❌ falta |

---

## Proposed Changes

### Application Layer

#### [NEW] `Application/Courts/DTOs/CourtDto.php`
DTO con: `id`, `branchId`, `tipoCourtId`, `name`, `active`. Método `fromDomain(Court $court)` y `toArray()`.

#### [NEW] `Application/Courts/Store/StoreCommand.php`
Campos: `branchId`, `tipoCourtId`, `name`.

#### [NEW] `Application/Courts/Store/StoreHandler.php`
Valida que exista la Branch. Valida que exista el TipoCourt. Crea y persiste la entidad Court.

#### [NEW] `Application/Courts/Show/ShowCommand.php`
Campo: `id`.

#### [NEW] `Application/Courts/Show/ShowHandler.php`
Busca por ID, lanza `CourtNotFoundException` si no existe.

#### [NEW] `Application/Courts/Get/GetCommand.php`
Campo: `branchId`.

#### [NEW] `Application/Courts/Get/GetHandler.php`
Devuelve array de Courts filtrados por branch.

#### [NEW] `Application/Courts/Update/UpdateCommand.php`
Campos: `id`, `name`, `tipoCourtId`.

#### [NEW] `Application/Courts/Update/UpdateHandler.php`
Busca Court, actualiza name y tipoCourtId.

#### [NEW] `Application/Courts/Deactivate/DeactivateCommand.php`
Campo: `id`.

#### [NEW] `Application/Courts/Deactivate/DeactivateHandler.php`
Busca Court, llama `deactivate()`, persiste.

---

### Domain Layer — completar CourtRepository

#### [MODIFY] [`CourtRepository.php`](file:///c:/Users/acecco/Desktop/Estudio/Sistema/app/Domain/Courts/Repositories/CourtRepository.php)
Agregar `findByBranchId` ya existe. No hay cambios necesarios por ahora.

---

### Infrastructure — binding

#### [MODIFY] [`AppServiceProvider.php`](file:///c:/Users/acecco/Desktop/Estudio/Sistema/app/Providers/AppServiceProvider.php)
Agregar: `$this->app->bind(CourtRepository::class, EloquentCourtRepository::class);`

---

### Http Layer

#### [NEW] `Http/Controllers/Courts/CreateCourtController.php`
Valida `branch_id` (se toma de la ruta), `tipo_court_id`, `name`. Llama `StoreHandler`.

#### [NEW] `Http/Controllers/Courts/ShowCourtController.php`
Llama `ShowHandler` con `id` de la ruta.

#### [NEW] `Http/Controllers/Courts/GetCourtController.php`
Llama `GetHandler` con `branch_id` de la ruta.

#### [NEW] `Http/Controllers/Courts/UpdateCourtController.php`
Valida `name` y `tipo_court_id`. Llama `UpdateHandler`.

#### [NEW] `Http/Controllers/Courts/DeactivateCourtController.php`
Llama `DeactivateHandler` con `id` de la ruta.

---

### Rutas

#### [MODIFY] [`api.php`](file:///c:/Users/acecco/Desktop/Estudio/Sistema/routes/api.php)

```
// Rutas de Courts (anidadas bajo branches)
Route::prefix('branches/{branch_id}/courts')
    ->middleware('permission')
    ->group(function () {
        Route::get('',  GetCourtController::class)->name('court.collection');
        Route::post('', CreateCourtController::class)->name('court.create');
    });

// Rutas de Courts (individuales)
Route::prefix('courts')
    ->middleware('permission')
    ->group(function () {
        Route::get('/{id}',    ShowCourtController::class)->name('court.view');
        Route::put('/{id}',    UpdateCourtController::class)->name('court.update');
        Route::delete('/{id}', DeactivateCourtController::class)->name('court.deactivate');
    });
```

La estructura de rutas replica el patrón de branches: colección y create anidadas bajo el padre, operaciones individuales con prefijo propio.

---

### CheckPermission

#### [MODIFY] [`CheckPermission.php`](file:///c:/Users/acecco/Desktop/Estudio/Sistema/app/Http/Middleware/CheckPermission.php)

**En el `match` de scope:**
```php
'court' => $this->resolveCourtScope($request),
```

**Nuevo método `resolveCourtScope()`:**
- Si hay `branch_id` en la ruta (POST /branches/{branch_id}/courts): autoriza a nivel Branch.
- Si hay `id` en la ruta (GET/PUT/DELETE /courts/{id}): busca el Court, obtiene su `branch_id` → busca la Branch → devuelve clubId + branchId.

**En el `match` de `authorizeCollection()`:**
```php
'court' => $this->authorizeCourtCollection($request, $userId),
```

**Nuevo método `authorizeCourtCollection()`:** verifica que el usuario tenga membership activa en la branch del parámetro de ruta `branch_id`.

> [!IMPORTANT]
> El resolver de court necesita inyectar `CourtRepository` en `CheckPermission`.

---

### Factories

#### [NEW] `database/factories/TipoCourtFactory.php`
Genera `name` y `description` únicos.

#### [NEW] `database/factories/CourtFactory.php`
Genera `branch_id`, `tipo_court_id`, `name`, `active: true`. Con estado `inactive()`.

---

### Seeder de permisos

#### [MODIFY] `database/seeders/DatabaseSeeder.php` (o seeder existente)
Agregar permisos:
```
court.view
court.create
court.update
court.deactivate
court.collection
```

---

## Verification Plan

### Automated Tests
```bash
php artisan test
```
Se escribirán Feature Tests en `tests/Feature/Courts/CourtTest.php` cubriendo:
- court.create con scope correcto → 201
- court.create sin membresía → 403
- court.view de court de otra branch → 403
- court.update con permiso → 200
- court.deactivate → desactiva en DB
- membership global puede operar sobre courts de cualquier branch del club
- court.collection filtra por branch

### Manual
- `php artisan migrate:fresh` sin errores
- Ejecutar llamadas a la API con token válido
