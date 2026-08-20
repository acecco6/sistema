# Proyecto de Estudio — Plataforma SaaS de Gestión y Reservas para Clubes

## 1. Objetivo

Construir una plataforma backend profesional para la gestión de clubes deportivos y reservas de canchas.

El proyecto tiene dos objetivos:

1. Construir un producto funcional y razonablemente realista.
2. Utilizarlo como proyecto de estudio para profundizar en PHP, MySQL, Laravel, arquitectura backend, concurrencia, testing, Redis, mensajería, Docker, CI/CD, cloud, observabilidad y system design.

La prioridad no es terminar rápido. La prioridad es **entender profundamente los problemas que aparecen durante el desarrollo y las decisiones técnicas que los resuelven**.

---

# 2. Objetivo profesional

La meta es evolucionar desde:

> "Desarrollo aplicaciones con PHP/Laravel"

hacia:

> "Soy un Backend Engineer capaz de diseñar, desarrollar, optimizar y escalar sistemas backend."

Por eso, antes de implementar una solución con Laravel, se debe intentar comprender el problema general que existe debajo de la herramienta.

Ejemplo:

No utilizar directamente `Cache::lock()` sin entender primero:

- qué es un distributed lock;
- qué problema resuelve;
- qué condiciones de carrera existen;
- qué garantías ofrece;
- qué ocurre si el proceso muere;
- qué alternativas existen.

---

# 3. Stack tecnológico

## Backend

- PHP 8.x
- Laravel
- API REST
- Sanctum
- MySQL

## Infraestructura

- Docker
- Docker Compose
- Nginx
- Redis
- RabbitMQ

## Testing

- PHPUnit / Pest
- Feature Tests
- Unit Tests
- Integration Tests

## DevOps

- Git
- GitHub Actions
- CI/CD
- Linux
- AWS

## Observabilidad

- Logs estructurados
- Metrics
- Tracing
- OpenTelemetry

## Frontend

Opcional inicialmente.

Puede utilizarse:

- Vue
- Quasar

El foco principal del proyecto será el backend.

---

# 4. Descripción del producto

La plataforma permitirá que distintos clubes administren sus operaciones.

Cada club puede tener:

- múltiples sucursales;
- múltiples canchas por sucursal;
- empleados;
- clientes;
- reservas;
- productos;
- precios;
- pagos;
- configuraciones.

Los clientes podrán consultar disponibilidad y realizar reservas.

Los administradores podrán gestionar la operación completa del club.

---

# 5. Modelo conceptual inicial

```text
Club
 ├── Branches
 │    ├── Courts
 │    ├── Products
 │    └── Employees
 │
 ├── Customers
 ├── Reservations
 ├── Payments
 └── Settings
```

Entidades iniciales:

- User
- Role
- Permission
- Club
- Branch
- Court
- CourtType
- Customer
- Reservation
- Payment
- Product
- PriceRule

El modelo podrá evolucionar a medida que aparezcan nuevos requerimientos.

---

# 6. Multi-tenancy

La plataforma debe soportar múltiples clubes.

Ejemplo:

```text
Platform
│
├── Club A
│   ├── Branch 1
│   ├── Branch 2
│   └── Users
│
└── Club B
    ├── Branch 1
    └── Users
```

Una regla fundamental:

> Un usuario perteneciente al Club A nunca debe poder acceder a información del Club B.

Esto debe cumplirse incluso si intenta modificar manualmente IDs en las requests.

Se estudiarán:

- tenant isolation;
- authorization;
- policies;
- middleware;
- scopes;
- seguridad a nivel de aplicación;
- seguridad a nivel de base de datos cuando corresponda.

---

# 7. Sistema de reservas

Las reservas serán el núcleo del sistema.

Una reserva tendrá, como mínimo:

- club;
- sucursal;
- cancha;
- cliente;
- fecha;
- hora de inicio;
- hora de finalización;
- precio;
- estado;
- información de pago.

Estados posibles:

```text
PENDING
CONFIRMED
CANCELLED
EXPIRED
COMPLETED
```

La máquina de estados podrá evolucionar.

---

# 8. Problema principal: concurrencia

El sistema debe impedir que dos usuarios puedan confirmar la misma cancha para el mismo horario.

Ejemplo:

```text
Cancha 3
18:00 - 19:00
```

Dos usuarios realizan simultáneamente:

```text
POST /api/reservations
```

Resultado esperado:

```text
Usuario A → CONFIRMED
Usuario B → REJECTED
```

Nunca:

```text
Usuario A → CONFIRMED
Usuario B → CONFIRMED
```

Este problema será utilizado para estudiar:

- race conditions;
- transactions;
- row locks;
- pessimistic locking;
- optimistic locking;
- isolation levels;
- MVCC;
- unique constraints;
- deadlocks;
- lock waits;
- retries;
- idempotency.

---

# 9. MySQL avanzado

MySQL será una parte fundamental del proyecto.

No se utilizará únicamente como almacenamiento.

Se estudiarán:

## Índices

- B-Tree
- índices simples;
- índices compuestos;
- cardinalidad;
- selectividad;
- covering indexes;
- índices redundantes.

## Queries

- JOINs;
- subqueries;
- CTEs;
- agregaciones;
- window functions;
- paginación;
- problemas N+1.

## Optimización

Utilizar:

```sql
EXPLAIN
EXPLAIN ANALYZE
```

para investigar consultas reales.

## Concurrencia

Experimentar con:

```text
READ UNCOMMITTED
READ COMMITTED
REPEATABLE READ
SERIALIZABLE
```

Estudiar:

- dirty reads;
- non-repeatable reads;
- phantom reads;
- deadlocks;
- locks;
- MVCC.

## Escalabilidad

Investigar:

- replicación;
- read replicas;
- particionamiento;
- archivado;
- grandes volúmenes de datos.

---

# 10. Arquitectura

El proyecto comenzará como un **Modular Monolith**.

No se utilizarán microservicios simplemente porque "parecen más profesionales".

Primero se debe conseguir una arquitectura modular y mantenible.

Estructura conceptual:

```text
app/
├── Domain/
│   ├── Clubs/
│   ├── Reservations/
│   ├── Payments/
│   ├── Customers/
│   └── Products/
│
├── Application/
│   ├── Clubs/
│   ├── Reservations/
│   ├── Payments/
│   └── ...
│
├── Infrastructure/
│   ├── Persistence/
│   ├── Payments/
│   ├── Notifications/
│   └── ...
│
└── Shared/
```

Conceptos a estudiar:

- SOLID;
- Dependency Injection;
- Dependency Inversion;
- Clean Architecture;
- Hexagonal Architecture;
- DDD;
- Use Cases;
- DTOs;
- Value Objects;
- Repositories;
- Domain Services;
- Domain Events.

No se debe aplicar una abstracción sin una razón concreta.

---

# 11. PHP avanzado

Durante el proyecto se estudiarán y aplicarán:

- OOP;
- encapsulamiento;
- herencia;
- composición;
- interfaces;
- clases abstractas;
- traits;
- polimorfismo;
- SOLID;
- dependency injection;
- exceptions;
- closures;
- generators;
- iterators;
- enums;
- attributes;
- readonly;
- tipos;
- variance;
- namespaces;
- autoloading;
- memory management a nivel conceptual;
- performance.

Objetivo:

Entender PHP como lenguaje y no únicamente como herramienta para Laravel.

---

# 12. Laravel profundo

Se estudiará Laravel internamente.

Temas:

- Service Container;
- Service Providers;
- Middleware;
- Pipeline;
- Routing;
- Request lifecycle;
- Events;
- Listeners;
- Jobs;
- Queues;
- Batches;
- Scheduling;
- Cache;
- Eloquent;
- Query Builder;
- Transactions;
- Policies;
- Gates;
- Authentication;
- Authorization;
- Validation;
- Notifications;
- Testing;
- Performance.

Siempre que sea posible:

> Entender primero el concepto y luego estudiar cómo Laravel lo implementa.

---

# 13. Pagos

Se agregará un sistema de pagos simulado inicialmente.

Flujo:

```text
Reservation
     ↓
Payment
     ↓
Payment Provider
     ↓
Webhook
     ↓
Update Payment
     ↓
Confirm Reservation
```

El webhook puede llegar:

- una vez;
- dos veces;
- varias veces;
- fuera de orden;
- con retraso.

El sistema debe ser idempotente.

Se estudiarán:

- idempotency keys;
- webhooks;
- state machines;
- retries;
- transactions;
- consistency;
- event processing.

---

# 14. Queues y procesamiento asíncrono

Después de confirmar una reserva:

```text
ReservationCreated
        │
        ├── SendConfirmationEmail
        ├── SendNotification
        ├── GenerateInvoice
        ├── UpdateStatistics
        └── NotifyClub
```

Estas operaciones no deberían bloquear innecesariamente la respuesta HTTP.

Flujo:

```text
HTTP Request
     ↓
Create Reservation
     ↓
Commit Transaction
     ↓
Dispatch Jobs
     ↓
HTTP 201
```

Estudiar:

- queues;
- workers;
- retries;
- backoff;
- failed jobs;
- dead letter queues;
- batches;
- job idempotency;
- jobs duplicados;
- partial failures.

---

# 15. RabbitMQ

RabbitMQ se incorporará cuando el sistema tenga una necesidad real de mensajería.

Objetivos:

- comprender exchanges;
- queues;
- routing keys;
- acknowledgements;
- retries;
- dead letter exchanges;
- message durability;
- consumer failures;
- competing consumers.

No se utilizará RabbitMQ únicamente por utilizar otra tecnología.

---

# 16. Redis

Redis se utilizará para:

- caching;
- locks;
- rate limiting;
- datos temporales;
- coordinación entre procesos.

Ejemplo:

```text
GET /api/branches/1/availability?date=2026-09-01
```

Primera consulta:

```text
API
 ↓
MySQL
 ↓
Redis
```

Siguientes consultas:

```text
API
 ↓
Redis
```

Al crear o cancelar una reserva debe analizarse cómo invalidar correctamente la información cacheada.

Estudiar:

- cache-aside;
- TTL;
- cache keys;
- invalidation;
- distributed locks;
- atomic operations;
- race conditions.

---

# 17. Testing

Todo comportamiento importante debe tener tests.

Tipos:

```text
Unit Tests
Feature Tests
Integration Tests
```

Ejemplo:

```text
Given:
    cancha disponible

When:
    dos usuarios intentan reservar simultáneamente

Then:
    solamente una reserva debe confirmarse
```

También deberán existir tests para:

- autorización;
- multi-tenancy;
- reservas;
- cancelaciones;
- pagos;
- webhooks;
- idempotencia;
- jobs;
- errores;
- permisos;
- concurrencia cuando sea posible.

---

# 18. Docker

El proyecto debe poder iniciarse mediante:

```bash
docker compose up
```

Servicios iniciales:

```text
Laravel
MySQL
Redis
RabbitMQ
Nginx
```

Posteriormente:

```text
Worker
Scheduler
```

Estudiar:

- images;
- containers;
- networks;
- volumes;
- environment variables;
- health checks;
- container dependencies;
- resource limits.

---

# 19. CI/CD

Agregar pipeline mediante GitHub Actions.

Pipeline inicial:

```text
Push
 ↓
Install dependencies
 ↓
Static analysis
 ↓
Code style
 ↓
Unit tests
 ↓
Feature tests
 ↓
Build
```

Posteriormente:

```text
Merge
 ↓
Build Docker Image
 ↓
Deploy
 ↓
Run migrations
 ↓
Health check
```

---

# 20. Seguridad

El sistema debe contemplar:

- authentication;
- authorization;
- RBAC;
- tenant isolation;
- validation;
- mass assignment;
- SQL injection;
- XSS;
- CSRF cuando corresponda;
- rate limiting;
- secrets management;
- secure headers;
- password hashing;
- API security;
- webhook verification;
- audit logs.

También se investigarán vulnerabilidades deliberadamente durante el desarrollo para entender cómo prevenirlas.

---

# 21. Observabilidad

El sistema debe permitir responder preguntas como:

> ¿Por qué esta request tardó 3 segundos?

> ¿Por qué falló este job?

> ¿Cuántas reservas están fallando?

> ¿Cuál endpoint es el más lento?

> ¿Cuánto tarda una reserva desde HTTP hasta confirmación?

Se estudiarán:

- structured logging;
- metrics;
- tracing;
- correlation IDs;
- OpenTelemetry;
- error tracking.

---

# 22. Performance

Cuando el sistema sea funcional se crearán problemas artificiales de rendimiento.

Ejemplos:

```text
100 usuarios concurrentes
1.000 requests/minuto
10.000 reservas
1.000.000 reservas
10.000.000 reservas
```

Se medirán:

- response time;
- throughput;
- database load;
- CPU;
- memory;
- cache hit ratio;
- queue latency.

La optimización deberá basarse en mediciones y no en suposiciones.

---

# 23. System Design

Una vez que el sistema funcione, se analizará cómo escalarlo.

Escenario:

```text
                    Load Balancer
                         │
              ┌──────────┼──────────┐
              ↓          ↓          ↓
             API        API        API
              │          │          │
              └──────────┼──────────┘
                         ↓
                       Redis
                         ↓
                       MySQL
                         │
                       Queue
                         ↓
                      Workers
```

Preguntas a resolver:

- ¿Qué componente escala primero?
- ¿Dónde aparece el cuello de botella?
- ¿Cómo escalar horizontalmente?
- ¿Qué información se puede cachear?
- ¿Cuándo necesitamos read replicas?
- ¿Cómo manejar fallos?
- ¿Cómo evitar doble procesamiento?
- ¿Cómo manejar picos de tráfico?
- ¿Qué pasa si Redis deja de funcionar?
- ¿Qué pasa si RabbitMQ deja de funcionar?
- ¿Qué pasa si MySQL no responde?

---

# 24. AWS

Cuando el sistema esté suficientemente maduro, llevarlo a AWS.

Posibles servicios:

- EC2 / ECS;
- RDS;
- ElastiCache;
- S3;
- CloudWatch;
- Load Balancer;
- IAM.

No es necesario utilizar todos.

Cada servicio debe introducirse cuando exista una necesidad concreta.

---

# 25. Roadmap

## Fase 0 — Diseño

- [ ] Definir requerimientos
- [ ] Definir dominio
- [ ] Identificar entidades
- [ ] Identificar reglas de negocio
- [ ] Diseñar arquitectura inicial
- [ ] Diseñar modelo de datos
- [ ] Definir API inicial

## Fase 1 — PHP

- [ ] OOP
- [ ] SOLID
- [ ] Interfaces
- [ ] Abstract classes
- [ ] Composition
- [ ] Dependency Injection
- [ ] Exceptions
- [ ] Enums
- [ ] DTOs
- [ ] Value Objects

## Fase 2 — MySQL

- [ ] Diseño relacional
- [ ] Normalización
- [ ] Índices
- [ ] Índices compuestos
- [ ] EXPLAIN
- [ ] EXPLAIN ANALYZE
- [ ] Transactions
- [ ] Isolation levels
- [ ] Locks
- [ ] Deadlocks
- [ ] MVCC
- [ ] Query optimization

## Fase 3 — Laravel

- [ ] API
- [ ] Authentication
- [ ] Authorization
- [ ] Policies
- [ ] Service Container
- [ ] Service Providers
- [ ] Middleware
- [ ] Eloquent
- [ ] Transactions
- [ ] Events
- [ ] Jobs
- [ ] Queues

## Fase 4 — Arquitectura

- [ ] Modular Monolith
- [ ] Clean Architecture
- [ ] Hexagonal Architecture
- [ ] DDD
- [ ] Use Cases
- [ ] Domain Events
- [ ] Repository Pattern
- [ ] Dependency Inversion

## Fase 5 — Concurrencia

- [ ] Race conditions
- [ ] Pessimistic locking
- [ ] Optimistic locking
- [ ] Idempotency
- [ ] Deadlocks
- [ ] Retry strategies

## Fase 6 — Redis

- [ ] Cache
- [ ] TTL
- [ ] Cache invalidation
- [ ] Locks
- [ ] Rate limiting

## Fase 7 — Mensajería

- [ ] RabbitMQ
- [ ] Exchanges
- [ ] Queues
- [ ] Routing
- [ ] Acknowledgements
- [ ] Retry
- [ ] Dead letter queues

## Fase 8 — Testing

- [ ] Unit tests
- [ ] Feature tests
- [ ] Integration tests
- [ ] Testing jobs
- [ ] Testing events
- [ ] Testing authorization
- [ ] Testing concurrency

## Fase 9 — Docker

- [ ] Dockerfile
- [ ] Docker Compose
- [ ] Networks
- [ ] Volumes
- [ ] Health checks
- [ ] Workers

## Fase 10 — CI/CD

- [ ] GitHub Actions
- [ ] Static analysis
- [ ] Tests
- [ ] Code style
- [ ] Docker build
- [ ] Deployment

## Fase 11 — Observabilidad

- [ ] Structured logs
- [ ] Correlation IDs
- [ ] Metrics
- [ ] Tracing
- [ ] OpenTelemetry

## Fase 12 — Escalabilidad

- [ ] Load testing
- [ ] Horizontal scaling
- [ ] Load balancing
- [ ] Read replicas
- [ ] Caching strategy
- [ ] Queue scaling

## Fase 13 — AWS

- [ ] Deploy
- [ ] RDS
- [ ] Redis
- [ ] Load Balancer
- [ ] Monitoring
- [ ] IAM

---

# 26. Reglas de estudio

Estas reglas son importantes.

### Regla 1

No copiar soluciones sin entenderlas.

### Regla 2

Antes de utilizar una herramienta, entender el problema que resuelve.

### Regla 3

No introducir tecnologías porque sí.

Cada tecnología debe responder a una necesidad.

### Regla 4

Preferir soluciones simples cuando resuelvan correctamente el problema.

### Regla 5

Medir antes de optimizar.

### Regla 6

Documentar decisiones importantes.

### Regla 7

Cuando aparezca un problema interesante, investigar primero el concepto y después implementarlo.

### Regla 8

Intentar romper el sistema deliberadamente para entender sus límites.

---

# 27. Architecture Decision Records

Las decisiones arquitectónicas importantes deberán documentarse.

Ejemplo:

```text
ADR-001: Utilizar Modular Monolith

Contexto:
Necesitamos separar dominios sin introducir la complejidad de microservicios.

Decisión:
Utilizar Modular Monolith.

Consecuencias:
Los módulos permanecen en el mismo proceso y despliegue,
pero mantienen límites claros.
```

Se crearán ADRs para decisiones importantes.

---

# 28. Resultado esperado

Al finalizar el proyecto debería existir:

- una API backend funcional;
- arquitectura modular;
- base de datos optimizada;
- sistema de reservas concurrente;
- autenticación y autorización;
- sistema de pagos;
- procesamiento asíncrono;
- Redis;
- RabbitMQ;
- tests;
- Docker;
- CI/CD;
- observabilidad;
- documentación;
- deployment en cloud;
- documentación de decisiones arquitectónicas.

Pero el resultado más importante será otro:

> **Entender por qué el sistema está diseñado de esa manera y poder defender técnicamente cada decisión.**

Ese es el objetivo principal del proyecto.
