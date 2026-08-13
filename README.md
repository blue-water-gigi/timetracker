# Time Tracker

Time Tracker is a web application for organizing teamwork and tracking time across projects. Employees record hours in weekly timesheets, while managers review the submitted work and monitor team activity through statistics.

## What it does

- Manages organizations, workspaces, projects, and project members.
- Supports administrator and employee accounts.
- Lets employees create timesheets and add regular or overtime entries.
- Provides a submit, approve, and reject workflow with project-based roles.
- Shows personal, project, team, and workspace statistics.
- Notifies users when a timesheet is submitted or reviewed.

## Technology

- **Backend:** Laravel 12, PHP 8.4, PostgreSQL, Redis, Laravel Sanctum
- **Frontend:** Vue 3, TypeScript, Pinia, Vue Router, Vite
- **Development environment:** Docker Compose and Nginx

## Run locally

### Requirements

- Docker Desktop with Docker Compose
- Git
- WSL is recommended on Windows

### 1. Prepare the environment

From the repository root:

```bash
cp backend_app/.env.example backend_app/.env
docker compose build
docker compose run --rm php composer install
docker compose run --rm php php artisan key:generate
```

### 2. Start the application

```bash
docker compose up -d
docker compose exec php php artisan migrate
```

Open [http://localhost:5173](http://localhost:5173) and create an administrator account. The backend API is available at [http://localhost:8000/api/v1](http://localhost:8000/api/v1).

The queue worker starts automatically and processes background notifications.

### Optional demo data

On a fresh, empty database you can add sample organizations, projects, users, and timesheets:

```bash
docker compose exec php php artisan db:seed
```

Demo administrator credentials:

```text
Email: admin@mail.com
Password: admin123
```

## Useful commands

```bash
# Show running services
docker compose ps

# Backend tests
docker compose exec php php artisan test

# Frontend tests
docker compose exec frontend npm run test

# Frontend lint and production build
docker compose exec frontend npm run lint
docker compose exec frontend npm run build

# Stop the application (saved data is kept)
docker compose down
```

## Project structure

```text
backend_app/    Laravel API, database migrations, queues, and tests
frontend_app/   Vue application, UI components, stores, and tests
docker/         Docker images and Nginx/PostgreSQL configuration
```

