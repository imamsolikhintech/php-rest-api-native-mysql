<?php
/**
 * API Configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'bootcamp_db');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_CHARSET', 'utf8mb4');

// API Configuration
define('API_VERSION', 'v1');
define('API_BASE_URL', '/api');

// Security Configuration
define('JWT_SECRET', 'your-secret-key-here');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 3600); // 1 hour

// Pagination Configuration
define('DEFAULT_PAGE_SIZE', 10);
define('PAGINATION_LIMIT', 10); // For backward compatibility
define('MAX_PAGE_SIZE', 100);

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Environment Configuration
define('ENVIRONMENT', 'development'); // development, production

// Timezone
date_default_timezone_set('Asia/Jakarta');

// CORS Configuration
define('CORS_ALLOWED_ORIGINS', ['*']); // In production, specify exact origins
define('CORS_ALLOWED_METHODS', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
define('CORS_ALLOWED_HEADERS', [
    'Content-Type',
    'Authorization',
    'X-Requested-With',
    'Accept',
    'Origin',
    'Access-Control-Request-Method',
    'Access-Control-Request-Headers'
]);
define('CORS_EXPOSED_HEADERS', ['X-Total-Count', 'X-Page-Count']);
define('CORS_MAX_AGE', 86400); // 24 hours
define('CORS_ALLOW_CREDENTIALS', true);

// Rate Limiting Configuration
define('RATE_LIMIT_DEFAULT_MAX', 100);
define('RATE_LIMIT_DEFAULT_WINDOW', 3600); // 1 hour
define('RATE_LIMIT_AUTH_MAX', 5);
define('RATE_LIMIT_AUTH_WINDOW', 900); // 15 minutes
define('RATE_LIMIT_UPLOAD_MAX', 10);
define('RATE_LIMIT_UPLOAD_WINDOW', 3600); // 1 hour