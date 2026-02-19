# Product API

A RESTful API for managing products built with Symfony 7 and PHP 8.2. Features complete CRUD operations, Swagger documentation, and comprehensive test coverage.

## Features

- **RESTful API** - Full CRUD operations for products
- **Swagger Documentation** - Interactive API documentation with OpenAPI 3.0
- **Service Layer Architecture** - Clean separation of concerns
- **Validation** - Input validation with detailed error messages
- **Unit Tests** - Comprehensive test coverage with PHPUnit 11
- **Code Coverage Reports** - HTML coverage reports with Xdebug

## Tech Stack

| Technology | Version |
|------------|---------|
| PHP | 8.2+ |
| Symfony | 7.x |
| Doctrine ORM | 3.x |
| PHPUnit | 11.x |
| NelmioApiDocBundle | 4.x |

## Requirements

- PHP 8.2 or higher
- Composer
- MySQL 8.0+ or PostgreSQL 13+
- Xdebug (optional, for code coverage)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/mahirsust/product-api.git
cd product-api
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

Copy the environment file and configure your database:

```bash
cp .env .env.local
```

Generate APP_SECRET:

```bash
php -r "echo bin2hex(random_bytes(16));"
```

Edit `.env.local`:

```env
APP_SECRET=paste_generated_secret_here
DATABASE_URL="mysql://username:password@127.0.0.1:3306/product_api?serverVersion=8.0"
```

### 4. Create Database and Run Migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 5. Start the Server

```bash
php -S localhost:8000 -t public
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/products` | List all products (paginated) |
| GET | `/api/products/{id}` | Get a single product |
| POST | `/api/products` | Create a new product |
| PUT | `/api/products/{id}` | Update a product (full) |
| PATCH | `/api/products/{id}` | Update a product (partial) |
| DELETE | `/api/products/{id}` | Delete a product |
| GET | `/api/products/search` | Search products with filters |

## API Documentation

Interactive Swagger documentation is available at:

```
http://localhost:8000/api/doc
```

## Usage Examples

### Create a Product

```bash
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Wireless Keyboard",
    "description": "High-quality wireless keyboard with RGB lighting",
    "price": 49.99,
    "quantity": 100
  }'
```

**Response:**

```json
{
  "id": 1,
  "name": "Wireless Keyboard",
  "description": "High-quality wireless keyboard with RGB lighting",
  "price": "49.99",
  "quantity": 100,
  "createdAt": "2026-02-09T21:30:00+00:00",
  "updatedAt": null
}
```

### Get All Products

```bash
curl http://localhost:8000/api/products?page=1&limit=10
```

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Wireless Keyboard",
      "price": "49.99",
      "quantity": 100
    }
  ],
  "meta": {
    "total": 1,
    "page": 1,
    "limit": 10,
    "pages": 1
  }
}
```

### Search Products

```bash
curl "http://localhost:8000/api/products/search?name=keyboard&minPrice=20&maxPrice=100&inStock=true"
```

### Update a Product (Partial)

```bash
curl -X PATCH http://localhost:8000/api/products/1 \
  -H "Content-Type: application/json" \
  -d '{
    "price": 39.99
  }'
```

### Delete a Product

```bash
curl -X DELETE http://localhost:8000/api/products/1
```

## Project Structure

```
product-api/
├── src/
│   ├── Controller/
│   │   └── Api/
│   │       └── ProductController.php
│   ├── Dto/
│   │   └── ProductDto.php
│   ├── Entity/
│   │   └── Product.php
│   ├── Exception/
│   │   ├── ProductNotFoundException.php
│   │   └── ValidationException.php
│   ├── Repository/
│   │   └── ProductRepository.php
│   └── Service/
│       ├── ProductService.php
│       └── ProductServiceInterface.php
├── tests/
│   └── Unit/
│       └── Service/
│           └── ProductServiceTest.php
├── config/
├── migrations/
├── public/
├── .env
├── composer.json
├── phpunit.xml.dist
└── README.md
```

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         HTTP Request                             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      ProductController                           │
│  • Handles HTTP request/response                                 │
│  • Deserializes JSON to DTO                                      │
│  • Returns JSON responses                                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       ProductService                             │
│  • Business logic                                                │
│  • Validation                                                    │
│  • Data transformation                                           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   ProductRepository                              │
│  • Database operations                                           │
│  • Query building                                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                         Database                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Testing

### Run All Tests

```bash
composer test
```

### Run Tests with Verbose Output

```bash
vendor/bin/phpunit --testdox
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/Service/ProductServiceTest.php
```

### Run Specific Test Method

```bash
vendor/bin/phpunit --filter testCreateProductSuccessfully
```

### Generate Code Coverage Report

```bash
composer test:coverage
```

Then open the report:

```bash
# Windows
start coverage/index.html

# macOS
open coverage/index.html

# Linux
xdg-open coverage/index.html
```

## Available Scripts

| Command | Description |
|---------|-------------|
| `composer test` | Run all tests |
| `composer test:unit` | Run unit tests only |
| `composer test:coverage` | Generate HTML coverage report |

## Error Responses

### Validation Error (400)

```json
{
  "errors": {
    "name": "Product name is required",
    "price": "Price must be a positive number"
  }
}
```

### Not Found Error (404)

```json
{
  "error": "Product with ID 123 was not found."
}
```

## Configuration

### Database

Configure in `.env`:

```env
# MySQL
DATABASE_URL="mysql://user:pass@127.0.0.1:3306/product_api?serverVersion=8.0"

# PostgreSQL
DATABASE_URL="postgresql://user:pass@127.0.0.1:5432/product_api?serverVersion=13"
```

## Useful Commands

```bash
# Clear cache
php bin/console cache:clear

# Create migration
php bin/console make:migration

# Run migrations
php bin/console doctrine:migrations:migrate

# Validate database schema
php bin/console doctrine:schema:validate

# View routes
php bin/console debug:router
```

## Acknowledgments

- [Symfony](https://symfony.com/)
- [Doctrine](https://www.doctrine-project.org/)
- [NelmioApiDocBundle](https://github.com/nelmio/NelmioApiDocBundle)
- [PHPUnit](https://phpunit.de/)
