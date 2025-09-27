<?php
/**
 * Authentication Middleware
 * Handles JWT token verification and user authentication
 */

class AuthMiddleware {
    private $userModel;
    private $response;
    
    public function __construct() {
        $this->userModel = new User();
        $this->response = new ResponseHandler();    
    }
    
    /**
     * Handle authentication middleware
     */
    public function handle($request = null) {
        try {
            // Get token from Authorization header
            $token = JWT::getTokenFromHeader();
            
            if (!$token) {
                $this->response->unauthorized('Access token is required');
                return false;
            }
            
            // Verify and decode token
            $decoded = JWT::verifyUserToken($token);
            
            if (!$decoded) {
                $this->response->unauthorized('Invalid or expired token');
                return false;
            }
            
            // Get user from database
            $user = $this->userModel->find($decoded['user_id']);
            
            if (!$user) {
                $this->response->unauthorized('User not found');
                return false;
            }
            
            // Check if user is active
            if ($user['status'] !== 'active') {
                $this->response->unauthorized('User account is inactive');
                return false;
            }
            
            // Store user data in global variable for access in controllers
            $GLOBALS['current_user'] = $user;
            $GLOBALS['current_user_id'] = $user['id'];
            $GLOBALS['current_user_role'] = $user['role'];
            
            return true;
            
        } catch (Exception $e) {
            $this->response->unauthorized('Authentication failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has required role
     */
    public function requireRole($requiredRole) {
        if (!isset($GLOBALS['current_user_role'])) {
            $this->response->unauthorized('Authentication required');
            return false;
        }
        
        $userRole = $GLOBALS['current_user_role'];
        
        // Define role hierarchy
        $roleHierarchy = [
            'user' => 1,
            'admin' => 2,
            'super_admin' => 3
        ];
        
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        
        if ($userLevel < $requiredLevel) {
            $this->response->forbidden('Insufficient permissions');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if user is admin
     */
    public function requireAdmin() {
        return $this->requireRole('admin');
    }
    
    /**
     * Check if user is super admin
     */
    public function requireSuperAdmin() {
        return $this->requireRole('super_admin');
    }
    
    /**
     * Check if user owns the resource or is admin
     */
    public function requireOwnershipOrAdmin($resourceUserId) {
        if (!isset($GLOBALS['current_user_id'])) {
            $this->response->unauthorized('Authentication required');
            return false;
        }
        
        $currentUserId = $GLOBALS['current_user_id'];
        $currentUserRole = $GLOBALS['current_user_role'] ?? 'user';
        
        // Allow if user owns the resource or is admin
        if ($currentUserId == $resourceUserId || in_array($currentUserRole, ['admin', 'super_admin'])) {
            return true;
        }
        
        $this->response->forbidden('Access denied');
        return false;
    }
    
    /**
     * Optional authentication (doesn't fail if no token)
     */
    public function optionalAuth() {
        try {
            $token = JWT::getTokenFromHeader();
            
            if ($token) {
                $decoded = JWT::verifyUserToken($token);
                
                if ($decoded) {
                    $user = $this->userModel->find($decoded['user_id']);
                    
                    if ($user && $user['status'] === 'active') {
                        $GLOBALS['current_user'] = $user;
                        $GLOBALS['current_user_id'] = $user['id'];
                        $GLOBALS['current_user_role'] = $user['role'];
                        return true;
                    }
                }
            }
            
            // Set guest user
            $GLOBALS['current_user'] = null;
            $GLOBALS['current_user_id'] = null;
            $GLOBALS['current_user_role'] = 'guest';
            
            return true;
            
        } catch (Exception $e) {
            // Set guest user on error
            $GLOBALS['current_user'] = null;
            $GLOBALS['current_user_id'] = null;
            $GLOBALS['current_user_role'] = 'guest';
            
            return true;
        }
    }
    
    /**
     * Get current authenticated user
     */
    public static function getCurrentUser() {
        return $GLOBALS['current_user'] ?? null;
    }
    
    /**
     * Get current user ID
     */
    public static function getCurrentUserId() {
        return $GLOBALS['current_user_id'] ?? null;
    }
    
    /**
     * Get current user role
     */
    public static function getCurrentUserRole() {
        return $GLOBALS['current_user_role'] ?? 'guest';
    }
    
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        return isset($GLOBALS['current_user']) && $GLOBALS['current_user'] !== null;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        $role = self::getCurrentUserRole();
        return in_array($role, ['admin', 'super_admin']);
    }
    
    /**
     * Check if user is guest
     */
    public static function isGuest() {
        return self::getCurrentUserRole() === 'guest';
    }
    
    /**
     * Logout user (invalidate token)
     * Note: In a production environment, you might want to maintain a blacklist of tokens
     */
    public function logout() {
        // Clear global user data
        unset($GLOBALS['current_user']);
        unset($GLOBALS['current_user_id']);
        unset($GLOBALS['current_user_role']);
        
        // In a production environment, you might want to:
        // 1. Add token to blacklist
        // 2. Update user's last_logout timestamp
        // 3. Log the logout event
        
        $this->response->success(null, 'Logged out successfully');
    }
    
    /**
     * Refresh token
     */
    public function refreshToken() {
        try {
            $token = JWT::getTokenFromHeader();
            
            if (!$token) {
                $this->response->unauthorized('Access token is required');
                return false;
            }
            
            $decoded = JWT::verifyUserToken($token);
            
            if (!$decoded) {
                $this->response->unauthorized('Invalid or expired token');
                return false;
            }
            
            // Get user from database
            $user = $this->userModel->find($decoded['user_id']);
            
            if (!$user || $user['status'] !== 'active') {
                $this->response->unauthorized('User not found or inactive');
                return false;
            }
            
            // Generate new token
            $newToken = JWT::createUserToken($user['id'], $user['email'], $user['role']);
            
            $this->response->success([
                'token' => $newToken,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ], 'Token refreshed successfully');
            
            return true;
            
        } catch (Exception $e) {
            $this->response->unauthorized('Token refresh failed: ' . $e->getMessage());
            return false;
        }
    }
}