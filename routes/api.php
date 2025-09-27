<?php
/**
 * API Routes Configuration
 */

// Start session for authentication
session_start();

// Authentication routes (no auth required)
$router->post('/auth/login', 'AuthController@login');
$router->post('/auth/register', 'AuthController@register');
$router->post('/auth/logout', 'AuthController@logout');
$router->get('/auth/status', 'AuthController@status');

// User routes
$router->group('/users', ['AuthMiddleware'], function($router) {
    $router->get('/', 'UserController@index');           // GET /users
    $router->get('/{id}', 'UserController@show');        // GET /users/1
    $router->post('/', 'UserController@store');          // POST /users
    $router->put('/{id}', 'UserController@update');      // PUT /users/1
    $router->delete('/{id}', 'UserController@destroy');  // DELETE /users/1
});

// Product routes
$router->get('/products', 'ProductController@index');           // GET /products (public)
$router->get('/products/{id}', 'ProductController@show');       // GET /products/1 (public)

$router->group('/products', ['AuthMiddleware'], function($router) {
    $router->post('/', 'ProductController@store');              // POST /products
    $router->put('/{id}', 'ProductController@update');          // PUT /products/1
    $router->delete('/{id}', 'ProductController@destroy');      // DELETE /products/1
});

// Category routes
$router->get('/categories', 'CategoryController@index');        // GET /categories (public)
$router->get('/categories/{id}', 'CategoryController@show');    // GET /categories/1 (public)

$router->group('/categories', ['AuthMiddleware'], function($router) {
    $router->post('/', 'CategoryController@store');             // POST /categories
    $router->put('/{id}', 'CategoryController@update');         // PUT /categories/1
    $router->delete('/{id}', 'CategoryController@destroy');     // DELETE /categories/1
});

// Order routes (all require authentication)
$router->group('/orders', ['AuthMiddleware'], function($router) {
    $router->get('/', 'OrderController@index');                 // GET /orders
    $router->get('/{id}', 'OrderController@show');              // GET /orders/1
    $router->post('/', 'OrderController@store');                // POST /orders
    $router->put('/{id}', 'OrderController@update');            // PUT /orders/1
    $router->delete('/{id}', 'OrderController@destroy');        // DELETE /orders/1
});

// Admin routes (require admin role)
$router->group('/admin', ['AuthMiddleware', 'AdminMiddleware'], function($router) {
    $router->get('/dashboard', 'AdminController@dashboard');
    $router->get('/users', 'AdminController@users');
    $router->get('/reports', 'AdminController@reports');
});

// File upload routes
$router->group('/upload', ['AuthMiddleware'], function($router) {
    $router->post('/image', 'UploadController@image');
    $router->post('/file', 'UploadController@file');
});

// Health check route (public)
$router->get('/health', function() {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'status' => 'OK',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => API_VERSION
        ],
        'message' => 'API is running'
    ]);
});