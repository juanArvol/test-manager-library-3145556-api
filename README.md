# Manager Library API

> **Prueba técnica · Ficha ADSO 3145556**  
> Backend en PHP (Laravel) siguiendo arquitectura hexagonal según spec-python/spec-.net traducido a PHP

## Arquitectura

Este proyecto sigue **arquitectura hexagonal** (puertos y adaptadores) según `constitution.md`:

```
src/Library/
├── Domain/              # Agregados y objetos de valor (no depende de nada - Artículo I)
├── Application/
│   ├── Ports/Outbound/  # Interfaces definidas por el núcleo
│   └── Services/        # Casos de uso
├── Adapters/
│   ├── Inbound/Http/    # Controladores y DTOs
│   └── Outbound/        # Persistencia, Storage, Security
└── Bootstrap/Composition/  # Único punto de composición (Artículo III)
```

## Stack Técnico

- **PHP** 8.2+
- **Laravel** 11
- **PostgreSQL** 16 (esquema `library`)
- **JWT** para autenticación (HS256, 8 horas)
- **Docker** para desarrollo y despliegue

## Instalación

### Requisitos previos
- Docker y Docker Compose
- Composer (si se desarrolla localmente)

### Inicio rápido con Docker

```bash
# 1. Clonar el repositorio
git clone <repo-url>
cd test-manager-library-3145556-api

# 2. Copiar variables de entorno
cp .env.example .env

# 3. Instalar dependencias
composer install

# 4. Generar key de aplicación
php artisan key:generate

# 5. Ejecutar migraciones (tras levantar infra)
php artisan migrate

# 6. Arrancar servidor de desarrollo
php artisan serve --host=0.0.0.0 --port=8000
```

## Endpoints

Base: `/api/v1`

### Públicos
- `GET /health` → `{"status":"ok"}` (T-01 ✓)

### Autenticación
- `POST /api/v1/auth/login` → devuelve JWT

### Protegidos (requieren Bearer token)
Ver `api-contract.md` del spec para el contrato completo.

## Variables de Entorno

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=library
DB_USERNAME=library
DB_PASSWORD=library

JWT_SECRET=<generar-secreto-largo>
ADMIN_USERNAME=admin
ADMIN_PASSWORD=<contraseña-segura>
COVERS_DIR=./storage/app/covers
CORS_ORIGIN=http://localhost:5173
```

## Progreso de Tareas

- [x] **T-01**: Esqueleto del proyecto y GET /health ✓
- [x] **T-02**: Migración inicial (6 tablas + semilla) ✓
- [x] **T-03**: Objetos de valor (Isbn, Quantity, DateRange) ✓
- [x] **T-04**: Dominio: Book (Withdraw, Restock, baja lógica) ✓
- [x] **T-05**: Dominio: Loan (AddItem, ReturnItem, estado calculado) ✓
- [x] **T-06**: Dominio: Member y User (normalización, rol válido) ✓
- [x] **T-07**: Puertos y casos de uso de autenticación (con fakes) ✓
- [x] **T-08**: Puertos y casos de uso de libros (con fakes) ✓
- [x] **T-09**: Caso de uso: prestar (LendBooksService) con fakes ✓
- [ ] **T-10**: Caso de uso: devolver y vencidos
- [ ] **T-03**: Objetos de valor (Isbn, Quantity, DateRange)
- [ ] **T-04**: Dominio: Book
- [ ] **T-05**: Dominio: Loan
- [ ] **T-06**: Dominio: Member y User
- [ ] **T-07**: Autenticación (LoginService, CreateLibrarianService)
- [ ] **T-08**: Casos de uso de libros
- [ ] **T-09**: Caso de uso: prestar
- [ ] **T-10**: Caso de uso: devolver y vencidos
- [ ] **T-11**: Caso de uso: reporte
- [ ] **T-12**: Repositorios (persistencia)
- [ ] **T-13**: Concurrencia optimista
- [ ] **T-14**: Security adapter (hash + JWT)
- [ ] **T-15**: Storage adapter (portadas)
- [ ] **T-16**: HTTP adapter completo
- [ ] **T-17**: Composición y arranque
- [ ] **T-18**: Restricciones en base de datos
- [ ] **T-19**: Pruebas de arquitectura

## Principios (constitution.md)

1. **El dominio no depende de nada** (solo stdlib de PHP)
2. **El núcleo declara sus puertos**
3. **Un único punto de composición**
4. **SOLID no es decorativo**
5. **El esquema es propiedad de las migraciones**
6. **Las invariantes que la base puede imponer, la base las impone**
7. **Lo derivado se calcula, no se almacena**
8. **Los valores congelados no siguen al catálogo**
9. **Ningún secreto entra al repositorio ni a un log**

## Verificación

```bash
# Ejecutar pruebas unitarias
php artisan test --testsuite=Unit

# Ejecutar pruebas de integración
php artisan test --testsuite=Feature

# Ejecutar pruebas de arquitectura
php artisan test --testsuite=Architecture

# Health check
curl http://localhost:8000/api/health
```

## Referencias

- Spec completo: [`test-manager-library-3145556-docs`](https://github.com/code-sena/test-manager-library-3145556-docs)
- Contrato API: `spec-python/api-contract.md`
- Modelo de datos: `spec-python/data-model.md`
- ADR: `spec-python/adr/`
