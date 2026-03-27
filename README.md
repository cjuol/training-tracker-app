# Training Tracker

Aplicacion web para gestionar entrenamiento entre entrenadores y atletas. Permite crear mesociclos, asignarlos a atletas, ejecutar sesiones con registro de series y consultar progreso historico, mediciones corporales y pasos diarios.

## Stack

- Symfony 7.4
- PHP 8.2+
- PostgreSQL 16
- Twig
- Tailwind CSS con Symfony UX y AssetMapper
- Stimulus para interacciones en cliente
- PHPUnit, PHPStan y PHP CS Fixer
- Docker Compose para entorno local

## Puesta En Marcha Rapida Con Docker

### Requisitos

- Docker y Docker Compose

### Arranque

```bash
docker compose up --build -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

La aplicacion queda disponible en http://localhost.

Si necesitas datos iniciales para desarrollo o pruebas:

```bash
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

## Desarrollo Sin Docker

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
symfony server:start
```

Configura `DATABASE_URL` segun tu entorno PostgreSQL local.

## Tests Y Calidad

Ejecutar tests:

```bash
vendor/bin/phpunit
```

Analisis estatico:

```bash
vendor/bin/phpstan analyse --memory-limit=256M
```

Revisar formato:

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=no
```

Aplicar formato:

```bash
vendor/bin/php-cs-fixer fix --allow-risky=no
```

## Roles

- `ROLE_ATLETA`: ejecuta entrenamientos asignados, registra series, consulta historial, mediciones y pasos.
- `ROLE_ENTRENADOR`: crea ejercicios y mesociclos, organiza sesiones, asigna planes y consulta progreso de atletas vinculados.

## Modulos Principales

- Autenticacion y seguridad: login, control de acceso por rol y voters para recursos sensibles.
- Catalogo de ejercicios: alta, edicion y reutilizacion de ejercicios con distintos tipos de medicion.
- Constructor de mesociclos: creacion de bloques de entrenamiento, sesiones y ejercicios ordenables.
- Flujo de asignacion: asignacion directa a atletas o union mediante codigo de invitacion.
- Ejecucion de entrenamientos: registro de series, control de descanso y soporte para superseries.
- Historial y dashboard: vista unificada para atleta y entrenador con seguimiento de actividad.
- Perfil y seguimiento fisico: mediciones corporales, foto de perfil y objetivo diario de pasos.

## Estructura General

- `src/Controller`: controladores HTTP.
- `src/Entity`: modelo de dominio con Doctrine.
- `src/Service`: logica de negocio principal.
- `templates/`: vistas Twig.
- `assets/`: controladores Stimulus y estilos.
- `migrations/`: migraciones de base de datos.
- `tests/`: tests funcionales, de servicio y de humo.

## Docker Services

- `nginx`: servidor web publico.
- `php`: runtime de Symfony.
- `db`: PostgreSQL 16.

## Estado Del Proyecto

La base del producto ya cubre autenticacion, planificacion de entrenamientos, asignaciones, ejecucion de sesiones y seguimiento del atleta. El repositorio mantiene documentacion publica minima en este archivo; la documentacion interna y notas de trabajo no forman parte del control de versiones.