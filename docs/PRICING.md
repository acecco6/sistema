# Pricing — Precios, promociones y cálculo por horario

## Objetivo

El módulo `Pricing` es responsable de determinar cuánto cuesta reservar una cancha durante un período determinado.

El sistema soporta:

- Precio base por sucursal y tipo de cancha.
- Precios definidos por 60 minutos.
- Promociones por día de la semana.
- Promociones por fecha específica.
- Promociones por franja horaria.
- Períodos de vigencia de promociones.
- Prioridades entre promociones.
- Reservas que atraviesan parcialmente una promoción.
- Reservas que atraviesan múltiples tarifas.
- Cálculo proporcional por minutos.

La responsabilidad principal del cálculo pertenece a:

```text
PriceResolver
```

---

# 1. Precio base

El precio base se configura mediante:

```text
Branch + TipoCourt
```

Ejemplo:

```text
Sucursal Palermo
Tipo de cancha: Pádel
Precio: $25.000
```

Esto significa:

```text
$25.000 por 60 minutos
```

No significa que cualquier reserva cueste siempre $25.000.

El precio representa una tarifa de referencia de **60 minutos**.

Por ejemplo:

```text
Precio base: $25.000 / 60 minutos

Reserva 60 minutos
→ $25.000

Reserva 30 minutos
→ $12.500

Reserva 90 minutos
→ $37.500
```

El cálculo es proporcional:

```text
subtotal = precioHora × minutos / 60
```

---

# 2. Promociones

Las promociones también representan un precio por **60 minutos**.

Ejemplo:

```text
Precio base:
$25.000 / 60 min

Promoción Happy Hour:
$18.000 / 60 min
```

La promoción puede tener restricciones como:

```text
day_of_week
specific_date
start_time
end_time
starts_at
ends_at
priority
active
```

Una regla puede utilizar una o varias restricciones simultáneamente.

---

# 3. Horarios de una promoción

Las franjas horarias utilizan el siguiente criterio:

```text
[start_time, end_time)
```

Esto significa:

```text
start_time → incluido
end_time   → excluido
```

Ejemplo:

```text
Promoción:
14:00 → 18:00
```

La promoción está vigente durante el tiempo transcurrido desde las `14:00` hasta llegar a las `18:00`.

Por lo tanto:

```text
13:59 → NO aplica
14:00 → aplica
15:00 → aplica
17:30 → aplica
17:59 → aplica
18:00 → NO aplica
```

Esto es importante para evitar superposiciones entre períodos.

Por ejemplo:

```text
PROMO
14:00 ─────────────────── 18:00

NORMAL
                       18:00 ──────────>
```

Una reserva que empieza exactamente a las `18:00` ya utiliza el precio correspondiente al siguiente período.

---

# 4. Una reserva puede tener múltiples precios

El precio de una reserva NO se determina solamente mirando su hora de inicio.

Se analiza todo el período:

```text
startsAt → endsAt
```

Por ejemplo:

```text
Precio base:
$25.000 / hora

Promoción:
14:00 → 18:00
$18.000 / hora

Reserva:
17:00 → 19:00
```

La reserva atraviesa dos períodos:

```text
                 PROMO
14:00 ───────────────────── 18:00

                       RESERVA
                 17:00 ───────────── 19:00
```

El cálculo es:

```text
17:00 → 18:00
60 minutos promoción
= $18.000

18:00 → 19:00
60 minutos precio base
= $25.000
```

Resultado:

```text
TOTAL = $43.000
```

---

# 5. Entrada parcial a una promoción

Una reserva puede comenzar antes de una promoción y entrar en ella posteriormente.

Ejemplo:

```text
Precio base:
$25.000 / hora

Promoción:
14:00 → 18:00
$18.000 / hora

Reserva:
13:30 → 14:30
```

Se divide:

```text
13:30 ─────── 14:00 ─────── 14:30
     NORMAL          PROMO
      30m             30m
```

Cálculo normal:

```text
$25.000 × 30 / 60
= $12.500
```

Cálculo promocional:

```text
$18.000 × 30 / 60
= $9.000
```

Resultado:

```text
$12.500 + $9.000

TOTAL = $21.500
```

---

# 6. Salida parcial de una promoción

También puede ocurrir lo contrario.

Ejemplo:

```text
Promoción:
14:00 → 18:00

Reserva:
17:30 → 19:00
```

Se divide:

```text
17:30 ─────── 18:00 ───────────── 19:00
      PROMO              NORMAL
       30m                60m
```

Con:

```text
Precio promo: $18.000 / hora
Precio base:  $25.000 / hora
```

tenemos:

```text
17:30 → 18:00
$18.000 × 30 / 60
= $9.000

18:00 → 19:00
$25.000 × 60 / 60
= $25.000
```

Resultado:

```text
TOTAL = $34.000
```

---

# 7. Reserva exactamente al final de una promoción

Esta es una regla de negocio importante.

Si tenemos:

```text
Promoción:
14:00 → 18:00
```

una reserva:

```text
18:00 → 19:00
```

NO utiliza la promoción.

Resultado:

```text
18:00 → 19:00
precio base
```

Esto ocurre porque `end_time` es exclusivo.

Conceptualmente:

```text
14:00 <= momento < 18:00
```

---

# 8. Reserva que termina exactamente cuando termina la promoción

Una reserva:

```text
17:00 → 18:00
```

sí utiliza completamente la promoción.

Aunque `18:00` no forme parte del período promocional, la reserva contiene los 60 minutos:

```text
17:00 → 18:00
```

dentro del período.

Por lo tanto:

```text
Precio promo: $18.000 / hora

Reserva:
17:00 → 18:00

TOTAL = $18.000
```

---

# 9. CourtPriceRule::appliesTo()

`CourtPriceRule::appliesTo()` NO recibe una reserva completa.

Recibe solamente un momento:

```php
public function appliesTo(
    DateTimeImmutable $date
): bool
```

Su responsabilidad es responder:

> ¿Esta regla de precio aplica exactamente en este momento?

Ejemplo:

```php
$rule->appliesTo(
    new DateTimeImmutable(
        '2026-09-10 15:30:00'
    )
);
```

Si la regla tiene:

```text
start_time = 14:00
end_time   = 18:00
```

devuelve:

```text
true
```

Pero:

```php
$rule->appliesTo(
    new DateTimeImmutable(
        '2026-09-10 18:00:00'
    )
);
```

devuelve:

```text
false
```

---

# 10. Responsabilidad de PriceResolver

`PriceResolver` sí recibe la reserva completa:

```php
resolve(
    int $branchId,
    int $tipoCourtId,
    DateTimeImmutable $startsAt,
    DateTimeImmutable $endsAt,
): ReservationPrice
```

Por ejemplo:

```php
$result = $priceResolver->resolve(
    branchId: 1,
    tipoCourtId: 1,
    startsAt: new DateTimeImmutable(
        '2026-09-10 13:30:00'
    ),
    endsAt: new DateTimeImmutable(
        '2026-09-10 14:30:00'
    ),
);
```

La responsabilidad de `PriceResolver` es:

```text
Reserva completa
       ↓
buscar precio base
       ↓
buscar promociones
       ↓
analizar el período completo
       ↓
determinar qué tarifa aplica
en cada parte de la reserva
       ↓
agrupar períodos consecutivos
con la misma tarifa
       ↓
calcular subtotales
       ↓
calcular total
```

---

# 11. Segmentos de precio

Una reserva puede generar uno o varios `PriceSegment`.

Ejemplo:

```text
Reserva:
17:00 → 19:00

Promo:
14:00 → 18:00
```

Genera:

```text
PriceSegment #1

startsAt:    17:00
endsAt:      18:00
hourlyPrice: 18000.00
subtotal:    18000.00
rule:        Happy Hour
```

y:

```text
PriceSegment #2

startsAt:    18:00
endsAt:      19:00
hourlyPrice: 25000.00
subtotal:    25000.00
rule:        null
```

El resultado final (`ReservationPrice`) contiene:

```text
total: 43000.00

segments:
    - promo  17:00 → 18:00
    - normal 18:00 → 19:00
```

---

# 12. Varias promociones

Una reserva puede atravesar más de una promoción.

Ejemplo:

```text
Precio base:
$25.000

Promo A:
14:00 → 16:00
$18.000

Promo B:
16:00 → 18:00
$20.000

Reserva:
15:00 → 19:00
```

Resultado:

```text
15:00 → 16:00
Promo A
1 hora × $18.000
= $18.000

16:00 → 18:00
Promo B
2 horas × $20.000
= $40.000

18:00 → 19:00
Precio base
1 hora × $25.000
= $25.000
```

Total:

```text
$18.000
+ $40.000
+ $25.000
─────────
$83.000
```

---

# 13. Promociones superpuestas

Puede ocurrir que dos promociones sean válidas durante el mismo período.

Ejemplo:

```text
Promo A
14:00 → 18:00
$20.000
priority = 10

Promo B
15:00 → 17:00
$15.000
priority = 50
```

Entre:

```text
15:00 → 17:00
```

ambas promociones aplican.

La regla es:

> Gana la promoción con mayor `priority`.

Por lo tanto:

```text
14:00 → 15:00
Promo A

15:00 → 17:00
Promo B

17:00 → 18:00
Promo A
```

No se suman descuentos ni promociones.

Siempre existe una única tarifa ganadora para cada segmento.

---

# 14. Día de la semana

`day_of_week` utiliza ISO-8601:

```text
1 = lunes
2 = martes
3 = miércoles
4 = jueves
5 = viernes
6 = sábado
7 = domingo
```

Ejemplo:

```text
day_of_week = 2
start_time  = 14:00
end_time    = 18:00
```

significa:

```text
Todos los martes
desde las 14:00
hasta las 18:00
```

---

# 15. Fecha específica

Una promoción puede estar limitada a una fecha:

```text
specific_date = 2026-09-10
```

En ese caso solamente aplica durante ese día.

Puede combinarse con horario:

```text
specific_date = 2026-09-10
start_time    = 14:00
end_time      = 18:00
```

Significa:

```text
10/09/2026
14:00 → 18:00
```

---

# 16. Vigencia de promociones

`starts_at` y `ends_at` representan el período durante el cual una regla existe comercialmente.

Ejemplo:

```text
starts_at = 2026-09-01 00:00:00
ends_at   = 2026-09-30 23:59:59

day_of_week = 2
start_time  = 14:00
end_time    = 18:00
```

Significa:

> Durante septiembre de 2026, todos los martes de 14:00 a 18:00.

Todas las restricciones configuradas deben cumplirse.

---

# 17. Valores null

Una condición `null` significa que esa restricción no existe.

Ejemplo:

```text
day_of_week = null
```

significa:

```text
cualquier día de la semana
```

Si tenemos:

```text
specific_date = null
day_of_week   = null
start_time    = 14:00
end_time      = 18:00
```

la regla puede aplicar todos los días entre las 14:00 y las 18:00, siempre que también cumpla cualquier otra condición configurada.

---

# 18. Manejo del dinero

No se deben utilizar `float` para cálculos monetarios.

Los precios provenientes de `DECIMAL` se manejan como:

```php
string
```

Ejemplo:

```text
"25000.00"
```

Para realizar operaciones se convierten internamente a centavos:

```text
"25000.00"
      ↓
2500000 centavos
```

Después del cálculo:

```text
2150000 centavos
      ↓
"21500.00"
```

Esto evita errores de precisión propios de números flotantes.

---

# 19. Precio histórico de una reserva

`PriceResolver` calcula el precio en el momento de crear una reserva.

El resultado debe almacenarse en la reserva.

Ejemplo:

```text
Hoy:

Precio base = $25.000
Promo       = $18.000

Reserva creada:
price = $18.000
```

Si mañana la promoción cambia:

```text
Promo = $20.000
```

la reserva anterior debe seguir teniendo:

```text
price = $18.000
```

Nunca se debe recalcular el precio histórico de una reserva utilizando la configuración actual.

La configuración de Pricing determina el precio **al momento de reservar**.

---

# 20. Resumen de responsabilidades

```text
CourtPrice
│
├── Define precio base por 60 minutos
│
└── Pertenece a Branch + TipoCourt


CourtPriceRule
│
├── Define precio promocional por 60 minutos
├── Conoce sus restricciones
└── appliesTo(moment)
       ↓
   determina si aplica
   en un instante concreto


PriceResolver
│
├── Recibe inicio y fin de reserva
├── Obtiene precio base
├── Obtiene promociones
├── Determina tarifa por período
├── Resuelve prioridades
├── Divide en segmentos
├── Calcula proporcionalmente
└── Devuelve ReservationPrice


ReservationPrice
│
├── total
└── PriceSegment[]


PriceSegment
│
├── startsAt
├── endsAt
├── hourlyPrice
├── subtotal
├── ruleId
└── ruleName
```

---

# Regla principal del sistema

El concepto más importante de Pricing es:

> **Una reserva no tiene necesariamente un único precio por hora.**

El precio se calcula sobre todo el intervalo reservado.

```text
Reserva
startsAt ───────────────────────── endsAt
                 ↓
          PriceResolver
                 ↓
     ┌───────────┼───────────┐
     ↓           ↓           ↓
 Precio base   Promo A     Promo B
     ↓           ↓           ↓
 subtotal     subtotal     subtotal
     └───────────┼───────────┘
                 ↓
                TOTAL
```

De esta manera una promoción se aplica únicamente sobre la parte de la reserva que realmente coincide con su período de validez.
