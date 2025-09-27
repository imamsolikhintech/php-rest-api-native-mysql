# Bootcamp API Documentation

A comprehensive RESTful API built with native PHP for managing users, products, categories, and orders.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [API Endpoints](#api-endpoints)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [Examples](#examples)

## Features

- **RESTful API Design**: Clean and intuitive endpoints
- **JWT Authentication**: Secure token-based authentication
- **Role-based Access Control**: Admin and user roles
- **CRUD Operations**: Complete Create, Read, Update, Delete functionality
- **Input Validation**: Comprehensive data validation
- **Rate Limiting**: API abuse prevention
- **CORS Support**: Cross-origin resource sharing
- **Pagination**: Efficient data pagination
- **Search & Filtering**: Advanced search capabilities
- **File Upload Support**: Image and file handling
- **Error Handling**: Standardized error responses

## Installation

1. **Clone or download the API files**
2. **Set up your web server** (Apache/Nginx) to point to the `api` directory
3. **Create the database** using the provided SQL file:
   ```sql
   -- Import the database.sql file
   mysql -u username -p database_name < config/database.sql
   ```
4. **Configure the database connection** in `config/config.php`
5. **Set proper permissions** for the storage directory (if using file-based rate limiting)

## Configuration

Edit `config/config.php` to configure your API:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'bootcamp_db');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// JWT Configuration
define('JWT_SECRET', 'your-secret-key');
define('JWT_EXPIRY', 86400); // 24 hours

// API Configuration
define('API_VERSION', 'v1');
define('API_BASE_URL', 'http://localhost/bootcamp/api');
```

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. Include the token in the Authorization header:

```
Authorization: Bearer your-jwt-token
```

### Getting a Token

**POST** `/auth/login`

```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "user@example.com",
            "role": "user"
        }
    },
    "message": "Login successful"
}
```

## API Endpoints

### Authentication Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/login` | User login | No |
| POST | `/auth/register` | User registration | No |
| POST | `/auth/logout` | User logout | Yes |
| GET | `/auth/user` | Get current user | Yes |
| GET | `/auth/status` | Check auth status | Yes |

### User Endpoints

| Method | Endpoint | Description | Auth Required | Role |
|--------|----------|-------------|---------------|------|
| GET | `/users` | Get all users | Yes | Admin |
| GET | `/users/{id}` | Get user by ID | Yes | Admin/Owner |
| POST | `/users` | Create user | Yes | Admin |
| PUT | `/users/{id}` | Update user | Yes | Admin/Owner |
| DELETE | `/users/{id}` | Delete user | Yes | Admin |

### Product Endpoints

| Method | Endpoint | Description | Auth Required | Role |
|--------|----------|-------------|---------------|------|
| GET | `/products` | Get all products | No | - |
| GET | `/products/{id}` | Get product by ID | No | - |
| POST | `/products` | Create product | Yes | Admin |
| PUT | `/products/{id}` | Update product | Yes | Admin |
| DELETE | `/products/{id}` | Delete product | Yes | Admin |

### Category Endpoints

| Method | Endpoint | Description | Auth Required | Role |
|--------|----------|-------------|---------------|------|
| GET | `/categories` | Get all categories | No | - |
| GET | `/categories/{id}` | Get category by ID | No | - |
| POST | `/categories` | Create category | Yes | Admin |
| PUT | `/categories/{id}` | Update category | Yes | Admin |
| DELETE | `/categories/{id}` | Delete category | Yes | Admin |

### Order Endpoints

| Method | Endpoint | Description | Auth Required | Role |
|--------|----------|-------------|---------------|------|
| GET | `/orders` | Get all orders | Yes | Admin |
| GET | `/orders/{id}` | Get order by ID | Yes | Admin/Owner |
| POST | `/orders` | Create order | Yes | User |
| PUT | `/orders/{id}` | Update order | Yes | Admin |
| DELETE | `/orders/{id}` | Cancel order | Yes | Admin/Owner |

### Utility Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/health` | API health check | No |
| POST | `/upload` | File upload | Yes |

## Error Handling

The API returns standardized error responses:

```json
{
    "success": false,
    "error": {
        "code": 400,
        "message": "Validation failed",
        "details": {
            "email": "Email is required",
            "password": "Password must be at least 6 characters"
        }
    }
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Rate Limit Exceeded
- `500` - Internal Server Error

## Rate Limiting

The API implements rate limiting to prevent abuse:

- **Default**: 100 requests per hour
- **Authentication**: 5 requests per 15 minutes
- **File Upload**: 10 requests per hour

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640995200
```

## Examples

### User Registration

```bash
curl -X POST http://localhost/bootcamp/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123"
  }'
```

### Get Products with Pagination

```bash
curl -X GET "http://localhost/bootcamp/api/products?page=1&limit=10&search=laptop" \
  -H "Content-Type: application/json"
```

### Create Product (Admin)

```bash
curl -X POST http://localhost/bootcamp/api/products \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-jwt-token" \
  -d '{
    "name": "Gaming Laptop",
    "description": "High-performance gaming laptop",
    "price": 1299.99,
    "stock": 50,
    "category_id": 1,
    "sku": "LAPTOP001"
  }'
```

### Update User Profile

```bash
curl -X PUT http://localhost/bootcamp/api/users/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-jwt-token" \
  -d '{
    "name": "John Smith",
    "email": "johnsmith@example.com"
  }'
```

### Create Order

```bash
curl -X POST http://localhost/bootcamp/api/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-jwt-token" \
  -d '{
    "items": [
      {
        "product_id": 1,
        "quantity": 2,
        "price": 1299.99
      }
    ],
    "shipping_address": "123 Main St, City, State 12345",
    "payment_method": "credit_card"
  }'
```

### Search Products

```bash
curl -X GET "http://localhost/bootcamp/api/products?search=gaming&category=1&min_price=500&max_price=2000" \
  -H "Content-Type: application/json"
```

### File Upload

```bash
curl -X POST http://localhost/bootcamp/api/upload \
  -H "Authorization: Bearer your-jwt-token" \
  -F "file=@image.jpg" \
  -F "type=product_image"
```

## Query Parameters

### Pagination
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 10, max: 100)

### Filtering
- `search` - Search term
- `status` - Filter by status
- `category` - Filter by category ID
- `min_price` - Minimum price
- `max_price` - Maximum price

### Sorting
- `sort` - Sort field (e.g., `name`, `price`, `created_at`)
- `order` - Sort order (`asc` or `desc`)

## Response Format

### Success Response
```json
{
    "success": true,
    "data": {
        // Response data
    },
    "message": "Operation successful"
}
```

### Paginated Response
```json
{
    "success": true,
    "data": [
        // Array of items
    ],
    "pagination": {
        "current_page": 1,
        "total_pages": 5,
        "total_items": 50,
        "items_per_page": 10
    },
    "message": "Data retrieved successfully"
}
```

### Error Response
```json
{
    "success": false,
    "error": {
        "code": 400,
        "message": "Error description",
        "details": {
            // Additional error details
        }
    }
}
```

## Security Features

- **JWT Authentication**: Secure token-based authentication
- **Password Hashing**: Bcrypt password hashing
- **Input Validation**: Comprehensive input validation and sanitization
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Output escaping
- **CORS Configuration**: Configurable cross-origin policies
- **Rate Limiting**: Request rate limiting
- **Role-based Access**: Admin and user role separation

## Development

### Adding New Endpoints

1. Create a new controller in `controllers/`
2. Add routes in `routes/api.php`
3. Create corresponding model in `models/`
4. Update documentation

### Custom Middleware

Create middleware in `middleware/` directory and register in the router:

```php
$router->addMiddleware('custom', function() {
    // Middleware logic
    return true;
});
```

## Support

For issues and questions, please refer to the code comments and this documentation. The API is designed to be self-documenting with clear error messages and standardized responses.