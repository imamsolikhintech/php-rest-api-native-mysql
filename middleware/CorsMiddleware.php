<?php
/**
 * CORS Middleware
 * Handles Cross-Origin Resource Sharing (CORS) headers
 */

class CorsMiddleware {
    private $allowedOrigins;
    private $allowedMethods;
    private $allowedHeaders;
    private $exposedHeaders;
    private $maxAge;
    private $allowCredentials;
    private $response;
    
    public function __construct() {
        // Load CORS configuration from config
        global $config;
        $this->response = new ResponseHandler();
        
        $this->allowedOrigins = $config['cors']['allowed_origins'] ?? ['*'];
        $this->allowedMethods = $config['cors']['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $config['cors']['allowed_headers'] ?? [
            'Content-Type',
            'Authorization',
            'X-Requested-With',
            'Accept',
            'Origin',
            'Access-Control-Request-Method',
            'Access-Control-Request-Headers'
        ];
        $this->exposedHeaders = $config['cors']['exposed_headers'] ?? ['X-Total-Count', 'X-Page-Count'];
        $this->maxAge = $config['cors']['max_age'] ?? 86400; // 24 hours
        $this->allowCredentials = $config['cors']['allow_credentials'] ?? true;
    }
    
    /**
     * Handle CORS middleware
     */
    public function handle($request = null) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Set CORS headers
        $this->setCorsHeaders($origin);
        
        // Handle preflight requests
        if ($method === 'OPTIONS') {
            $this->handlePreflightRequest();
            return false; // Stop execution for preflight
        }
        
        return true; // Continue execution
    }
    
    /**
     * Set CORS headers
     */
    private function setCorsHeaders($origin) {
        // Access-Control-Allow-Origin
        if ($this->isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: {$origin}");
        } elseif (in_array('*', $this->allowedOrigins)) {
            header("Access-Control-Allow-Origin: *");
        }
        
        // Access-Control-Allow-Credentials
        if ($this->allowCredentials) {
            header("Access-Control-Allow-Credentials: true");
        }
        
        // Access-Control-Allow-Methods
        header("Access-Control-Allow-Methods: " . implode(', ', $this->allowedMethods));
        
        // Access-Control-Allow-Headers
        header("Access-Control-Allow-Headers: " . implode(', ', $this->allowedHeaders));
        
        // Access-Control-Expose-Headers
        if (!empty($this->exposedHeaders)) {
            header("Access-Control-Expose-Headers: " . implode(', ', $this->exposedHeaders));
        }
        
        // Access-Control-Max-Age
        header("Access-Control-Max-Age: {$this->maxAge}");
    }
    
    /**
     * Handle preflight requests
     */
    private function handlePreflightRequest() {
        $requestMethod = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '';
        $requestHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
        
        // Validate requested method
        if ($requestMethod && !in_array($requestMethod, $this->allowedMethods)) {
            http_response_code(405);
            $this->response->error('Method not allowed', 405);
            return;
        }
        
        // Validate requested headers
        if ($requestHeaders) {
            $requestedHeaders = array_map('trim', explode(',', $requestHeaders));
            foreach ($requestedHeaders as $header) {
                if (!in_array($header, $this->allowedHeaders)) {
                    http_response_code(400);
                    $this->response->error('Header not allowed: ' . $header, 400);
                    return;
                }
            }
        }
        
        // Send successful preflight response
        http_response_code(200);
        echo json_encode(['message' => 'Preflight request successful']);
        exit;
    }
    
    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed($origin) {
        if (empty($origin)) {
            return false;
        }
        
        // Check exact match
        if (in_array($origin, $this->allowedOrigins)) {
            return true;
        }
        
        // Check wildcard patterns
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if ($this->matchesWildcard($allowedOrigin, $origin)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Match wildcard patterns
     */
    private function matchesWildcard($pattern, $origin) {
        // Convert wildcard pattern to regex
        $regex = str_replace(['*', '.'], ['.*', '\.'], $pattern);
        $regex = '/^' . $regex . '$/i';
        
        return preg_match($regex, $origin);
    }
    
    /**
     * Add custom CORS header
     */
    public function addHeader($name, $value) {
        header("{$name}: {$value}");
    }
    
    /**
     * Set custom exposed header
     */
    public function exposeHeader($headerName) {
        if (!in_array($headerName, $this->exposedHeaders)) {
            $this->exposedHeaders[] = $headerName;
            header("Access-Control-Expose-Headers: " . implode(', ', $this->exposedHeaders));
        }
    }
    
    /**
     * Handle CORS for file uploads
     */
    public function handleFileUploadCors() {
        // Additional headers for file uploads
        header("Access-Control-Allow-Headers: " . implode(', ', array_merge($this->allowedHeaders, [
            'Content-Disposition',
            'Content-Length',
            'Content-Range'
        ])));
        
        // Expose file-related headers
        $this->exposeHeader('Content-Disposition');
        $this->exposeHeader('Content-Length');
        $this->exposeHeader('Content-Range');
    }
    
    /**
     * Static method to quickly set basic CORS headers
     */
    public static function setBasicHeaders() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Max-Age: 86400");
    }
    
    /**
     * Static method to handle simple preflight
     */
    public static function handleSimplePreflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::setBasicHeaders();
            http_response_code(200);
            echo json_encode(['message' => 'Preflight OK']);
            exit;
        }
    }
    
    /**
     * Validate CORS configuration
     */
    public function validateConfig() {
        $errors = [];
        
        // Check allowed origins
        if (empty($this->allowedOrigins)) {
            $errors[] = 'At least one allowed origin must be specified';
        }
        
        // Check allowed methods
        if (empty($this->allowedMethods)) {
            $errors[] = 'At least one allowed method must be specified';
        }
        
        // Validate methods
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        foreach ($this->allowedMethods as $method) {
            if (!in_array($method, $validMethods)) {
                $errors[] = "Invalid HTTP method: {$method}";
            }
        }
        
        // Check max age
        if ($this->maxAge < 0) {
            $errors[] = 'Max age must be a positive number';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Get CORS configuration
     */
    public function getConfig() {
        return [
            'allowed_origins' => $this->allowedOrigins,
            'allowed_methods' => $this->allowedMethods,
            'allowed_headers' => $this->allowedHeaders,
            'exposed_headers' => $this->exposedHeaders,
            'max_age' => $this->maxAge,
            'allow_credentials' => $this->allowCredentials
        ];
    }
    
    /**
     * Update CORS configuration
     */
    public function updateConfig($config) {
        if (isset($config['allowed_origins'])) {
            $this->allowedOrigins = $config['allowed_origins'];
        }
        
        if (isset($config['allowed_methods'])) {
            $this->allowedMethods = $config['allowed_methods'];
        }
        
        if (isset($config['allowed_headers'])) {
            $this->allowedHeaders = $config['allowed_headers'];
        }
        
        if (isset($config['exposed_headers'])) {
            $this->exposedHeaders = $config['exposed_headers'];
        }
        
        if (isset($config['max_age'])) {
            $this->maxAge = $config['max_age'];
        }
        
        if (isset($config['allow_credentials'])) {
            $this->allowCredentials = $config['allow_credentials'];
        }
    }
}