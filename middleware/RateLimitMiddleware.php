<?php
/**
 * Rate Limit Middleware
 * Handles API rate limiting to prevent abuse
 */

class RateLimitMiddleware {
    private $maxRequests;
    private $timeWindow;
    private $storage;
    private $keyPrefix;
    private $response;
    
    public function __construct($maxRequests = 100, $timeWindow = 3600) {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow; // in seconds
        $this->keyPrefix = 'rate_limit:';
        $this->response = new ResponseHandler();
        
        // Initialize storage (using file-based storage for simplicity)
        // In production, consider using Redis or Memcached
        $this->initializeStorage();
    }
    
    /**
     * Initialize storage system
     */
    private function initializeStorage() {
        $storageDir = dirname(__DIR__) . '/storage/rate_limits';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $this->storage = $storageDir;
    }
    
    /**
     * Handle rate limiting middleware
     */
    public function handle($request = null) {
        $clientKey = $this->getClientKey();
        $currentTime = time();
        
        // Get current request count
        $requestData = $this->getRequestData($clientKey);
        
        // Clean old requests
        $requestData = $this->cleanOldRequests($requestData, $currentTime);
        
        // Check if limit exceeded
        if (count($requestData) >= $this->maxRequests) {
            $this->sendRateLimitResponse($requestData);
            return false;
        }
        
        // Add current request
        $requestData[] = $currentTime;
        $this->saveRequestData($clientKey, $requestData);
        
        // Set rate limit headers
        $this->setRateLimitHeaders($requestData);
        
        return true;
    }
    
    /**
     * Get client identification key
     */
    private function getClientKey() {
        // Try to get user ID if authenticated
        if (isset($GLOBALS['current_user_id'])) {
            return 'user:' . $GLOBALS['current_user_id'];
        }
        
        // Fall back to IP address
        $ip = $this->getClientIP();
        return 'ip:' . $ip;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get request data from storage
     */
    private function getRequestData($clientKey) {
        $filename = $this->storage . '/' . md5($this->keyPrefix . $clientKey) . '.json';
        
        if (!file_exists($filename)) {
            return [];
        }
        
        $data = file_get_contents($filename);
        $decoded = json_decode($data, true);
        
        return $decoded['requests'] ?? [];
    }
    
    /**
     * Save request data to storage
     */
    private function saveRequestData($clientKey, $requestData) {
        $filename = $this->storage . '/' . md5($this->keyPrefix . $clientKey) . '.json';
        
        $data = [
            'client_key' => $clientKey,
            'requests' => $requestData,
            'updated_at' => time()
        ];
        
        file_put_contents($filename, json_encode($data));
    }
    
    /**
     * Clean old requests outside time window
     */
    private function cleanOldRequests($requestData, $currentTime) {
        $cutoffTime = $currentTime - $this->timeWindow;
        
        return array_filter($requestData, function($timestamp) use ($cutoffTime) {
            return $timestamp > $cutoffTime;
        });
    }
    
    /**
     * Send rate limit exceeded response
     */
    private function sendRateLimitResponse($requestData) {
        $oldestRequest = min($requestData);
        $resetTime = $oldestRequest + $this->timeWindow;
        $retryAfter = $resetTime - time();
        
        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . $resetTime);
        header('Retry-After: ' . $retryAfter);
        
        $this->response->error('Rate limit exceeded. Try again in ' . $retryAfter . ' seconds.', 429);
    }
    
    /**
     * Set rate limit headers
     */
    private function setRateLimitHeaders($requestData) {
        $remaining = max(0, $this->maxRequests - count($requestData));
        $oldestRequest = !empty($requestData) ? min($requestData) : time();
        $resetTime = $oldestRequest + $this->timeWindow;
        
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . $resetTime);
    }
    
    /**
     * Create rate limiter with custom limits
     */
    public static function create($maxRequests, $timeWindow) {
        return new self($maxRequests, $timeWindow);
    }
    
    /**
     * Create strict rate limiter (lower limits)
     */
    public static function strict() {
        return new self(30, 3600); // 30 requests per hour
    }
    
    /**
     * Create lenient rate limiter (higher limits)
     */
    public static function lenient() {
        return new self(1000, 3600); // 1000 requests per hour
    }
    
    /**
     * Create rate limiter for authentication endpoints
     */
    public static function forAuth() {
        return new self(5, 900); // 5 requests per 15 minutes
    }
    
    /**
     * Create rate limiter for file uploads
     */
    public static function forUploads() {
        return new self(10, 3600); // 10 uploads per hour
    }
    
    /**
     * Get current rate limit status
     */
    public function getStatus($clientKey = null) {
        if (!$clientKey) {
            $clientKey = $this->getClientKey();
        }
        
        $requestData = $this->getRequestData($clientKey);
        $requestData = $this->cleanOldRequests($requestData, time());
        
        $remaining = max(0, $this->maxRequests - count($requestData));
        $oldestRequest = !empty($requestData) ? min($requestData) : time();
        $resetTime = $oldestRequest + $this->timeWindow;
        
        return [
            'limit' => $this->maxRequests,
            'remaining' => $remaining,
            'reset_time' => $resetTime,
            'reset_in' => max(0, $resetTime - time()),
            'requests_made' => count($requestData)
        ];
    }
    
    /**
     * Reset rate limit for a client
     */
    public function reset($clientKey = null) {
        if (!$clientKey) {
            $clientKey = $this->getClientKey();
        }
        
        $filename = $this->storage . '/' . md5($this->keyPrefix . $clientKey) . '.json';
        
        if (file_exists($filename)) {
            unlink($filename);
        }
        
        return true;
    }
    
    /**
     * Clean up old rate limit files
     */
    public function cleanup() {
        $files = glob($this->storage . '/*.json');
        $currentTime = time();
        $cleanupThreshold = $currentTime - ($this->timeWindow * 2); // Keep files for 2x time window
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            $updatedAt = $data['updated_at'] ?? 0;
            
            if ($updatedAt < $cleanupThreshold) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get rate limit statistics
     */
    public function getStatistics() {
        $files = glob($this->storage . '/*.json');
        $stats = [
            'total_clients' => 0,
            'active_clients' => 0,
            'total_requests' => 0,
            'clients_at_limit' => 0
        ];
        
        $currentTime = time();
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            $requests = $data['requests'] ?? [];
            
            // Clean old requests
            $activeRequests = array_filter($requests, function($timestamp) use ($currentTime) {
                return $timestamp > ($currentTime - $this->timeWindow);
            });
            
            $stats['total_clients']++;
            $stats['total_requests'] += count($activeRequests);
            
            if (!empty($activeRequests)) {
                $stats['active_clients']++;
                
                if (count($activeRequests) >= $this->maxRequests) {
                    $stats['clients_at_limit']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Check if client is currently rate limited
     */
    public function isRateLimited($clientKey = null) {
        if (!$clientKey) {
            $clientKey = $this->getClientKey();
        }
        
        $requestData = $this->getRequestData($clientKey);
        $requestData = $this->cleanOldRequests($requestData, time());
        
        return count($requestData) >= $this->maxRequests;
    }
}