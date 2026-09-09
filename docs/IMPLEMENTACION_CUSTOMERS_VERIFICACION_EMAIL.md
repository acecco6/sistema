# Implementación: Customers multi-club y verificación de email

Fecha: **09/09/2026**.

## Modelo

- `customers.user_id` es nullable y unique: una persona puede existir sin cuenta; una cuenta solo representa un Customer global.
- `club_customers` tiene unique `(club_id, customer_id)` y guarda estado, notas y primera/última reserva dentro del club.
- `reservations.club_customer_id` y `fixed_reservations.club_customer_id` son nullable para conservar filas históricas y reservas públicas guest.
- La migración convierte las reservas existentes con `customer_user_id` en Customer + ClubCustomer. Los guests históricos no se deduplican automáticamente.

## Reglas

1. El email normalizado de Customer es único cuando existe. Al verificar una cuenta, se vincula automáticamente al Customer sin cuenta que tenga ese email; el teléfono no se usa para asociar ni fusionar.
2. Una cuenta solo puede asociarse si verificó su email.
3. Al reservar autenticado se crea/reutiliza el Customer y su relación con el club.
4. Un ClubCustomer inactivo no puede recibir reservas nuevas.
5. Nombre/email de Customers vinculados a cuenta se toman del User; la edición del club queda limitada a notas.
6. `customer_user_id` se mantiene durante la transición para ownership y compatibilidad del frontend anterior.

## Despliegue

```bash
composer install
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RolePermissionSeeder
php artisan config:clear
php artisan route:clear
php artisan test
```

Configurar `APP_URL` con la URL pública correcta y un mailer real (`MAIL_MAILER`, `MAIL_HOST`, credenciales y `MAIL_FROM_*`) para que el link firmado enviado por email apunte al backend accesible.

## Compatibilidad

Las APIs previas y payloads `customer_user_id`/`guest_*` siguen disponibles. El frontend nuevo debe migrar a `club_customer_id`. No eliminar las columnas legacy hasta completar el despliegue del frontend y migrar los datos.
