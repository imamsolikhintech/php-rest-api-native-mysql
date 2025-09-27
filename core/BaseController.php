<?php
/**
 * Base Controller
 * Contains common functionality for all API controllers
 */

abstract class BaseController {
    protected $db;
    protected $request;
    protected $response;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->request = $this->getRequestData();
        $this->response = new ResponseHandler();
    }
    /**
     * Get parameter from request data (JSON, POST, GET) with optional default value
     */
    protected function getParam($key, $default = null) {
        return isset($this->request[$key]) ? $this->sanitize($this->request[$key]) : $default;
    }
    /**
     * Get a specific URI segment by index (0-based)
     */
    protected function getUriSegment($index) {
        $segments = $this->getUriSegments();
        return isset($segments[$index]) ? $segments[$index] : null;
    }
    /**
     * Get request data (JSON, POST, GET)
     */
    protected function getRequestData() {
        $method = $_SERVER['REQUEST_METHOD'];
        $data = [];
        
        switch ($method) {
            case 'GET':
                $data = $_GET;
                break;
            case 'POST':
            case 'PUT':
            case 'DELETE':
                $input = file_get_contents('php://input');
                $data = json_decode($input, true) ?? [];
                
                // Merge with POST data if available
                if (!empty($_POST)) {
                    $data = array_merge($data, $_POST);
                }
                break;
        }
        
        return $data;
    }
    
    /**
     * Get request method
     */
    protected function getMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }
    
    /**
     * Get request URI segments
     */
    protected function getUriSegments() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = trim($uri, '/');
        return explode('/', $uri);
    }
    /**
     * Get query parameter with optional default value
     */
    protected function getQueryParam($key, $default = null) {
        return isset($_GET[$key]) ? $this->sanitize($_GET[$key]) : $default;
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired($data, $required) {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->response->error('Missing required fields: ' . implode(', ', $missing), 400);
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize input data
     */
    protected function sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get pagination parameters
     */
    protected function getPagination() {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(MAX_PAGE_SIZE, max(1, intval($_GET['limit']))) : DEFAULT_PAGE_SIZE;
        $offset = ($page - 1) * $limit;
        
        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Get search and filter parameters
     */
    protected function getFilters() {
        $filters = [];
        
        // Search parameter
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = $this->sanitize($_GET['search']);
        }
        
        // Sort parameters
        if (isset($_GET['sort_by'])) {
            $filters['sort_by'] = $this->sanitize($_GET['sort_by']);
            $filters['sort_order'] = isset($_GET['sort_order']) && 
                                   strtolower($_GET['sort_order']) === 'desc' ? 'DESC' : 'ASC';
        }
        
        // Status filter
        if (isset($_GET['status'])) {
            $filters['status'] = $this->sanitize($_GET['status']);
        }
        
        return $filters;
    }
    
    /**
     * Check if user is authenticated
     */
    protected function requireAuth() {
        if (!$this->isAuthenticated()) {
            $this->response->error('Authentication required', 401);
            return false;
        }
        return true;
    }
    
    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated() {
        return isset($GLOBALS['current_user_id']) && !empty($GLOBALS['current_user_id']);
    }
    
    /**
     * Get current user ID
     */
    protected function getCurrentUserId() {
        return $GLOBALS['current_user_id'] ?? null;
    }
    
    /**
     * Get current user data
     */
    protected function getCurrentUser() {
        return $GLOBALS['current_user'] ?? null;
    }
    
    /**
     * Check if user has specific role
     */
    protected function hasRole($role) {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === $role;
    }
    
    /**
     * Require specific role
     */
    protected function requireRole($role) {
        if (!$this->requireAuth()) {
            return false;
        }
        
        if (!$this->hasRole($role)) {
            $this->response->error('Insufficient permissions', 403);
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle file upload
     */
    protected function handleFileUpload($fileKey, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        $file = $_FILES[$fileKey];
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File size exceeds maximum allowed size');
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception('File type not allowed');
        }
        
        // Generate unique filename
        $filename = uniqid() . '.' . $extension;
        $destination = UPLOAD_DIR . $filename;
        
        // Create upload directory if it doesn't exist
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $filename;
        }
        
        throw new Exception('Failed to upload file');
    }
    
    /**
     * Abstract method that must be implemented by child controllers
     */
    abstract public function handleRequest();
}