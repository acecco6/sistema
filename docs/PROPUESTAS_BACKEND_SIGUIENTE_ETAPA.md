# Propuestas para la siguiente etapa del backend

Actualizado: **09/09/2026**. Ninguno de estos puntos es necesario para usar la implementación actual.

## Prioridad alta

1. Flujo de “reclamar perfil”: vincular a una cuenta verificada un Customer creado previamente sin cuenta, con confirmación del club y auditoría.
2. Merge controlado de Customers duplicados: vista previa, traslado de ClubCustomers/reservas y registro del merge. Nunca fusionar automáticamente por email/teléfono.
3. Recuperación de contraseña (`forgot/reset password`) y revocación de tokens tras cambios sensibles.
4. Edición/reprogramación de Reservation con recalculo/snapshot de precio y control transaccional de solapamientos.

## Prioridad media

1. Contactos múltiples y preferencias de comunicación por Customer.
2. Etiquetas por ClubCustomer (`frecuente`, `moroso`, `academia`) y filtros.
3. Historial/auditoría de cambios de cliente y status.
4. Endpoint de estadísticas del cliente dentro del club: reservas, cancelaciones, facturación y última actividad.
5. Importación CSV con previsualización y reporte de conflictos.
6. Notificaciones in-app con leído/no leído.

## Prioridad posterior

1. Consentimientos y preferencias de privacidad/marketing.
2. Bloqueos temporales con motivo y vencimiento en lugar de solo `active`.
3. Crédito/saldo a favor por club.
4. Dependientes o grupos familiares asociados a una cuenta.
5. Webhooks/eventos de integración para CRM.

## Decisiones que requieren definición funcional

- quién puede buscar un Customer global para asociarlo a otro club;
- quién aprueba el claim de un perfil sin cuenta;
- política de retención/anonimización de datos personales;
- si el estado inactivo impide solo nuevas reservas o también pagos/series existentes;
- estrategia al modificar una reserva fija con occurrences ya generadas.
