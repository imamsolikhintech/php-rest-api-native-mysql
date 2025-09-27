<?php
/**
 * API Router
 * Handles routing for API endpoints
 */

class Router {
    private $routes = [];
    private $middleware = [];
    
    /**
     * Add GET route
     */
    public function get($path, $handler, $middleware = []) {
        $this->addRoute('GET', $path, $handler, $middleware);
    }
    
    /**
     * Add POST route
     */
    public function post($path, $handler, $middleware = []) {
        $this->addRoute('POST', $path, $handler, $middleware);
    }
    
    /**
     * Add PUT route
     */
    public function put($path, $handler, $middleware = []) {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }
    
    /**
     * Add DELETE route
     */
    public function delete($path, $handler, $middleware = []) {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }
    
    /**
     * Add route with any method
     */
    public function addRoute($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => $method,
            'path' => $this->normalizePath($path),
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }
    
    /**
     * Add global middleware
     */
    public function addMiddleware($middleware) {
        $this->middleware[] = $middleware;
    }
    
    /**
     * Handle incoming request
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = $this->normalizePath($uri);
        
        // Remove API base path if present
        if (strpos($path, API_BASE_URL) === 0) {
            $path = substr($path, strlen(API_BASE_URL));
        }
        
        $path = $this->normalizePath($path);
        
        // Find matching route
        $matchedRoute = null;
        $params = [];
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method) {
                $routeParams = $this->matchRoute($route['path'], $path);
                if ($routeParams !== false) {
                    $matchedRoute = $route;
                    $params = $routeParams;
                    break;
                }
            }
        }
        
        if (!$matchedRoute) {
            $this->sendNotFound();
            return;
        }
        
        // Execute global middleware
        foreach ($this->middleware as $middleware) {
            $this->executeMiddleware($middleware);
        }
        
        // Execute route-specific middleware
        foreach ($matchedRoute['middleware'] as $middleware) {
            $this->executeMiddleware($middleware);
        }
        
        // Execute handler
        $this->executeHandler($matchedRoute['handler'], $params);
    }
    
    /**
     * Normalize path
     */
    private function normalizePath($path) {
        $path = trim($path, '/');
        return '/' . $path;
    }
    
    /**
     * Match route pattern with actual path
     */
    private function matchRoute($routePath, $actualPath) {
        // Convert route pattern to regex
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $actualPath, $matches)) {
            array_shift($matches); // Remove full match
            
            // Extract parameter names
            preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
            $paramNames = $paramNames[1];
            
            // Combine parameter names with values
            $params = [];
            for ($i = 0; $i < count($paramNames); $i++) {
                if (isset($matches[$i])) {
                    $params[$paramNames[$i]] = $matches[$i];
                }
            }
            
            return $params;
        }
        
        return false;
    }
    
    /**
     * Execute middleware
     */
    private function executeMiddleware($middleware) {
        if (is_string($middleware)) {
            $middlewareClass = new $middleware();
            $middlewareClass->handle();
        } elseif (is_callable($middleware)) {
            call_user_func($middleware);
        }
    }
    
    /**
     * Execute route handler
     */
    private function executeHandler($handler, $params = []) {
        if (is_string($handler)) {
            // Format: "ControllerClass@method"
            if (strpos($handler, '@') !== false) {
                list($class, $method) = explode('@', $handler);
                $controller = new $class();
                
                // Pass parameters to controller
                if (!empty($params)) {
                    $controller->setParams($params);
                }
                
                $controller->$method();
            } else {
                // Just controller class, call handleRequest
                $controller = new $handler();
                if (!empty($params)) {
                    $controller->setParams($params);
                }
                $controller->handleRequest();
            }
        } elseif (is_callable($handler)) {
            call_user_func($handler, $params);
        }
    }
    
    /**
     * Send 404 response
     */
    private function sendNotFound() {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found'
        ]);
    }
    
    /**
     * Group routes with common prefix and middleware
     */
    public function group($prefix, $middleware, $callback) {
        $originalRoutes = $this->routes;
        $this->routes = [];
        
        // Execute callback to define routes
        call_user_func($callback, $this);
        
        // Add prefix and middleware to new routes
        foreach ($this->routes as &$route) {
            $route['path'] = $this->normalizePath($prefix . $route['path']);
            $route['middleware'] = array_merge($middleware, $route['middleware']);
        }
        
        // Merge with original routes
        $this->routes = array_merge($originalRoutes, $this->routes);
    }
}