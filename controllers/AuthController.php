<?php
/**
 * Authentication Controller
 * Handles user authentication operations
 */

class AuthController extends BaseController {
    
    /**
     * User login
     */
    public function login() {
        try {
            $data = $this->getRequestData();
            
            // Validate required fields
            $validator = new Validator();
            $validator->required($data['email'] ?? '', 'Email is required');
            $validator->email($data['email'] ?? '', 'Invalid email format');
            $validator->required($data['password'] ?? '', 'Password is required');
            
            if ($validator->hasErrors()) {
                $this->response->validationError($validator->getErrors());
                return;
            }
            
            $email = $this->sanitize($data['email']);
            $password = $data['password'];
            
            // Check user credentials
            $query = "SELECT id, name, email, password, role FROM users WHERE email = ? AND status = 'active'";
            $user = $this->db->fetch($query, [$email]);
            
            if (!$user || !password_verify($password, $user['password'])) {
                $this->response->unauthorized('Invalid credentials');
                return;
            }
            
            // Generate JWT token
            $token = JWT::createUserToken(
                $user['id'],
                $user['email'],
                $user['role']
            );
            
            // Update last login
            $timestamp = $this->db->getCurrentTimestamp();
            $this->db->execute("UPDATE users SET last_login = {$timestamp} WHERE id = ?", [$user['id']]);
            
            $this->response->success([
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ],
                'token' => $token,
                'login_time' => date('Y-m-d H:i:s')
            ], 'Login successful');
            
        } catch (Exception $e) {
            $this->response->internalServerError('Login failed: ' . $e->getMessage());
        }
    }
    
    /**
     * User registration
     */
    public function register() {
        try {
            $data = $this->getRequestData();
            
            // Validate required fields
            $validator = new Validator();
            $validator->required($data['name'] ?? '', 'Name is required');
            $validator->minLength($data['name'] ?? '', 2, 'Name must be at least 2 characters');
            $validator->required($data['email'] ?? '', 'Email is required');
            $validator->email($data['email'] ?? '', 'Invalid email format');
            $validator->unique($data['email'] ?? '', 'email', 'users', 'email');
            $validator->required($data['password'] ?? '', 'Password is required');
            $validator->passwordStrength($data['password'] ?? '', 'Password must be at least 8 characters with uppercase, lowercase, and number');
            
            if ($validator->hasErrors()) {
                $this->response->validationError($validator->getErrors());
                return;
            }
            
            $name = $this->sanitize($data['name']);
            $email = $this->sanitize($data['email']);
            $username = $this->sanitize($data['username']);
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $role = $this->sanitize($data['role'] ?? 'user');
            
            // Insert new user
            $timestamp = $this->db->getCurrentTimestamp();
            $query = "INSERT INTO users (name, email, username, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', {$timestamp})";
            $userId = $this->db->execute($query, [$name, $email, $username, $password, $role]);
            
            if ($userId) {
                $this->response->created([
                    'user' => [
                        'id' => $userId,
                        'name' => $name,
                        'email' => $email,
                        'role' => $role
                    ]
                ], 'Registration successful');
            } else {
                $this->response->internalServerError('Registration failed');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Registration failed: ' . $e->getMessage());
        }
    }
    
    /**
     * User logout
     */
    public function logout() {
        try {
            // Destroy session
            session_destroy();
            
            $this->response->success([], 'Logout successful');
            
        } catch (Exception $e) {
            $this->response->internalServerError('Logout failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check authentication status
     */
    public function status() {
        try {
            if ($this->isAuthenticated()) {
                $user = $this->getCurrentUser();
                $this->response->success([
                    'authenticated' => true,
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ]
                ], 'User is authenticated');
            } else {
                $this->response->success([
                    'authenticated' => false
                ], 'User is not authenticated');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Status check failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get current user info
     */
    public function user() {
        try {
            if (!$this->isAuthenticated()) {
                $this->response->unauthorized('Authentication required');
                return;
            }
            
            $user = $this->getCurrentUser();
            
            if ($user) {
                // Get additional user info from database
                $query = "SELECT id, name, email, role, created_at, last_login FROM users WHERE id = ?";
                $userDetails = $this->db->fetch($query, [$user['id']]);
                
                $this->response->success([
                    'user' => $userDetails
                ], 'User information retrieved');
            } else {
                $this->response->notFound('User not found');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to get user info: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle request routing
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getUriSegment(1);
        
        switch ($method) {
            case 'POST':
                if ($path === 'login') {
                    $this->login();
                } elseif ($path === 'register') {
                    $this->register();
                } elseif ($path === 'logout') {
                    $this->logout();
                } else {
                    $this->response->notFound('Endpoint not found');
                }
                break;
                
            case 'GET':
                if ($path === 'status') {
                    $this->status();
                } elseif ($path === 'user') {
                    $this->user();
                } else {
                    $this->response->notFound('Endpoint not found');
                }
                break;
                
            default:
                $this->response->error('Method not allowed', 405);
                break;
        }
    }
}