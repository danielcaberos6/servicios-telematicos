# Proyecto Subastas

Aplicación web desarrollada con **Laravel 13** y **PostgreSQL 16**, contenerizada con Docker.

## Requisitos

- [Docker](https://docs.docker.com/get-docker/) y Docker Compose
- Git

## Instalación y ejecución

### 1. Clonar el repositorio

```bash
git clone https://github.com/danielcaberos6/servicios-telematicos.git
cd servicios-telematicos
```

### 2. Configurar variables de entorno

```bash
cp .env.example .env
```

Editá el archivo `.env` con los valores correspondientes a la base de datos:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ServiciosTelematicos
DB_USERNAME=ServiciosTelematicos
DB_PASSWORD=ServiciosTelematicos
```

### 3. Levantar los contenedores

```bash
docker compose up -d --build
```

Esto construye la imagen de Laravel, levanta el contenedor de PostgreSQL y deja la aplicación corriendo en:

```
http://localhost:8000
```

### 4. Correr las migraciones

```bash
docker exec ServiciosTelematicos php artisan migrate
```

## Detener los contenedores

```bash
docker compose down
```

Para eliminar también los volúmenes (borra los datos de la base de datos):

```bash
docker compose down -v
```

## Estructura del proyecto

```
proyecto-subastas/
├── app/              # Lógica de la aplicación (modelos, controladores)
├── database/         # Migraciones y seeders
├── routes/           # Definición de rutas
├── resources/        # Vistas y assets
├── docker-compose.yml
├── dockerfile
└── .env.example
```

## Stack tecnológico

| Tecnología   | Versión |
|--------------|---------|
| PHP          | 8.4     |
| Laravel      | 13      |
| PostgreSQL   | 16      |
| Docker       | -       |
