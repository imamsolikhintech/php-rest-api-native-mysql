<?php
/**
 * User Controller
 * Handles CRUD operations for users
 */

class UserController extends BaseController {
    
    /**
     * Get all users (with pagination and filtering)
     */
    public function index() {
        try {
            if (!$this->isAuthenticated()) {
                $this->response->unauthorized('Authentication required');
                return;
            }
            
            // Get pagination parameters
            $page = (int)($this->getQueryParam('page') ?? 1);
            $limit = (int)($this->getQueryParam('limit') ?? PAGINATION_LIMIT);
            $offset = ($page - 1) * $limit;
            
            // Get filter parameters
            $search = $this->getQueryParam('search');
            $role = $this->getQueryParam('role');
            $status = $this->getQueryParam('status');
            
            // Build query
            $whereConditions = [];
            $params = [];
            
            if ($search) {
                $whereConditions[] = "(name LIKE ? OR email LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if ($role) {
                $whereConditions[] = "role = ?";
                $params[] = $role;
            }
            
            if ($status) {
                $whereConditions[] = "status = ?";
                $params[] = $status;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
            $totalResult = $this->db->fetch($countQuery, $params);
            $total = $totalResult['total'];
            
            // Get users
            $query = "SELECT id, name, email, role, status, created_at, last_login 
                     FROM users $whereClause 
                     ORDER BY created_at DESC 
                     LIMIT $limit OFFSET $offset";
            $users = $this->db->fetchAll($query, $params);
            
            $this->response->paginated($users, $page, $limit, $total, 'Users retrieved successfully');
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to get users: ' . $e->getMessage());
        }
    }
    
    /**
     * Get single user by ID
     */
    public function show() {
        try {
            if (!$this->isAuthenticated()) {
                $this->response->unauthorized('Authentication required');
                return;
            }
            
            $id = $this->getParam('id');
            
            if (!$id) {
                $this->response->error('User ID is required', 400);
                return;
            }
            
            $query = "SELECT id, name, email, role, status, created_at, last_login FROM users WHERE id = ?";
            $user = $this->db->fetch($query, [$id]);
            
            if ($user) {
                $this->response->success(['user' => $user], 'User retrieved successfully');
            } else {
                $this->response->notFound('User not found');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to get user: ' . $e->getMessage());
        }
    }
    
    /**
     * Create new user
     */
    public function store() {
        try {
            if (!$this->isAuthenticated()) {
                $this->response->unauthorized('Authentication required');
                return;
            }
            
            // Check if user has admin role
            if (!$this->hasRole('admin')) {
                $this->response->forbidden('Admin access required');
                return;
            }
            
            $data = $this->getRequestData();
            
            // Validate required fields
            $validator = new Validator();
            $validator->required($data['name'] ?? '', 'Name is required');
            $validator->minLength($data['name'] ?? '', 2, 'Name must be at least 2 characters');
            $validator->required($data['email'] ?? '', 'Email is required');
            $validator->email($data['email'] ?? '', 'Invalid email format');
            $validator->unique('users', 'email', $data['email'] ?? '', 'Email already exists');
            $validator->required($data['password'] ?? '', 'Password is required');
            $validator->passwordStrength($data['password'] ?? '', 'Password must be at least 8 characters with uppercase, lowercase, and number');
            
            if ($validator->hasErrors()) {
                $this->response->validationError($validator->getErrors());
                return;
            }
            
            $name = $this->sanitize($data['name']);
            $email = $this->sanitize($data['email']);
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $role = $this->sanitize($data['role'] ?? 'user');
            $status = $this->sanitize($data['status'] ?? 'active');
            
            // Insert new user
            $query = "INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $userId = $this->db->execute($query, [$name, $email, $password, $role, $status]);
            
            if ($userId) {
                $this->response->created([
                    'user' => [
                        'id' => $userId,
                        'name' => $name,
                        'email' => $email,
                        'role' => $role,
                        'status' => $status
                    ]
                ], 'User created successfully');
            } else {
                $this->response->internalServerError('Failed to create user');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to create user: ' . $e->getMessage());
        }
    }
    
    /**
     * Update user
     */
    public function update() {
        try {
            if (!$this->isAuthenticated()) {
                $this->response->unauthorized('Authentication required');
                return;
            }
            
            $id = $this->getParam('id');
            $data = $this->getRequestData();
            
            if (!$id) {
                $this->response->error('User ID is required', 400);
                return;
            }
            
            // Check if user exists
            $existingUser = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
            if (!$existingUser) {
                $this->response->notFound('User not found');
                return;
            }
            
            // Check permissions (admin or own profile)
            if (!$this->hasRole('admin') && $this->getCurrentUserId() != $id) {
                $this->response->forbidden('Access denied');
                return;
            }
            
            // Validate fields
            $validator = new Validator();
            
            if (isset($data['name'])) {
                $validator->required($data['name'], 'Name is required');
                $validator->minLength($data['name'], 2, 'Name must be at least 2 characters');
            }
            
            if (isset($data['email'])) {
                $validator->required($data['email'], 'Email is required');
                $validator->email($data['email'], 'Invalid email format');
                // Check if email is unique (excluding current user)
                $emailCheck = $this->db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$data['email'], $id]);
                if ($emailCheck) {
                    $validator->addError('email', 'Email already exists');
                }
            }
            
            if (isset($data['password']) && !empty($data['password'])) {
                $validator->passwordStrength($data['password'], 'Password must be at least 8 characters with uppercase, lowercase, and number');
            }
            
            if ($validator->hasErrors()) {
                $this->response->validationError($validator->getErrors());
                return;
            }
            
            // Build update query
            $updateFields = [];
            $params = [];
            
            if (isset($data['name'])) {
                $updateFields[] = "name = ?";
                $params[] = $this->sanitize($data['name']);
            }
            
            if (isset($data['email'])) {
                $updateFields[] = "email = ?";
                $params[] = $this->sanitize($data['email']);
            }
            
            if (isset($data['password']) && !empty($data['password'])) {
                $updateFields[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            // Only admin can update role and status
            if ($this->hasRole('admin')) {
                if (isset($data['role'])) {
                    $updateFields[] = "role = ?";
                    $params[] = $this->sanitize($data['role']);
                }
                
                if (isset($data['status'])) {
                    $updateFields[] = "status = ?";
                    $params[] = $this->sanitize($data['status']);
                }
            }
            
            if (empty($updateFields)) {
                $this->response->error('No fields to update', 400);
                return;
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $id;
            
            $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $result = $this->db->execute($query, $params);
            
            if ($result) {
                // Get updated user
                $updatedUser = $this->db->fetch("SELECT id, name, email, role, status, created_at, updated_at FROM users WHERE id = ?", [$id]);
                $this->response->success(['user' => $updatedUser], 'User updated successfully');
            } else {
                $this->response->internalServerError('Failed to update user');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to update user: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete user
     */
    public function destroy() {
        try {
            if (!$this->isAuthenticated()) {
                $this->response->unauthorized('Authentication required');
                return;
            }
            
            // Only admin can delete users
            if (!$this->hasRole('admin')) {
                $this->response->forbidden('Admin access required');
                return;
            }
            
            $id = $this->getParam('id');
            
            if (!$id) {
                $this->response->error('User ID is required', 400);
                return;
            }
            
            // Check if user exists
            $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
            if (!$user) {
                $this->response->notFound('User not found');
                return;
            }
            
            // Prevent deleting own account
            if ($this->getCurrentUserId() == $id) {
                $this->response->error('Cannot delete your own account', 400);
                return;
            }
            
            // Soft delete (update status to deleted)
            $query = "UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?";
            $result = $this->db->execute($query, [$id]);
            
            if ($result) {
                $this->response->success([], 'User deleted successfully');
            } else {
                $this->response->internalServerError('Failed to delete user');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to delete user: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle request routing
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                if ($this->getParam('id')) {
                    $this->show();
                } else {
                    $this->index();
                }
                break;
                
            case 'POST':
                $this->store();
                break;
                
            case 'PUT':
                $this->update();
                break;
                
            case 'DELETE':
                $this->destroy();
                break;
                
            default:
                $this->response->error('Method not allowed', 405);
                break;
        }
    }
}