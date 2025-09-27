<?php
/**
 * User Model
 * Handles user-specific database operations
 */

class User extends BaseModel {
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password', 'role', 'status'];
    protected $hidden = ['password'];
    
    /**
     * Find user by email
     */
    public function findByEmail($email) {
        return $this->findBy('email', $email);
    }
    
    /**
     * Find user by email including password (for authentication)
     */
    public function findByEmailWithPassword($email) {
        $query = "SELECT * FROM {$this->table} WHERE email = ?";
        $result = $this->db->prepare($query);
        $result->execute([$email]);
        return $result->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create user with hashed password
     */
    public function createUser($data) {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        $data['status'] = $data['status'] ?? 'active';
        $data['role'] = $data['role'] ?? 'user';
        
        return $this->create($data);
    }
    
    /**
     * Update user password
     */
    public function updatePassword($id, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->update($id, ['password' => $hashedPassword]);
    }
    
    /**
     * Verify user password
     */
    public function verifyPassword($email, $password) {
        $user = $this->findByEmailWithPassword($email);
        
        if ($user && password_verify($password, $user['password'])) {
            return $this->hideFields($user);
        }
        
        return false;
    }
    
    /**
     * Get active users
     */
    public function getActiveUsers($limit = null, $offset = null) {
        return $this->all(['status' => 'active'], 'created_at DESC', $limit, $offset);
    }
    
    /**
     * Get users by role
     */
    public function getUsersByRole($role, $limit = null, $offset = null) {
        return $this->all(['role' => $role, 'status' => 'active'], 'created_at DESC', $limit, $offset);
    }
    
    /**
     * Search users
     */
    public function searchUsers($search, $limit = null, $offset = null) {
        $query = "SELECT * FROM {$this->table} 
                 WHERE (name LIKE ? OR email LIKE ?) AND status = 'active' 
                 ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
            if ($offset) {
                $query .= " OFFSET {$offset}";
            }
        }
        
        $searchTerm = "%{$search}%";
        $result = $this->db->prepare($query);
        $result->execute([$searchTerm, $searchTerm]);
        $data = $result->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map([$this, 'hideFields'], $data);
    }
    
    /**
     * Count users by role
     */
    public function countByRole($role) {
        return $this->count(['role' => $role, 'status' => 'active']);
    }
    
    /**
     * Update last login
     */
    public function updateLastLogin($id) {
        $query = "UPDATE {$this->table} SET last_login = NOW() WHERE {$this->primaryKey} = ?";
        $result = $this->db->prepare($query);
        return $result->execute([$id]);
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        return $this->exists('email', $email, $excludeId);
    }
    
    /**
     * Get user statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total users
        $stats['total'] = $this->count(['status' => 'active']);
        
        // Users by role
        $roleQuery = "SELECT role, COUNT(*) as count FROM {$this->table} WHERE status = 'active' GROUP BY role";
        $result = $this->db->prepare($roleQuery);
        $result->execute();
        $roleStats = $result->fetchAll(PDO::FETCH_ASSOC);
        
        $stats['by_role'] = [];
        foreach ($roleStats as $roleStat) {
            $stats['by_role'][$roleStat['role']] = (int)$roleStat['count'];
        }
        
        // Recent registrations (last 30 days)
        $recentQuery = "SELECT COUNT(*) as count FROM {$this->table} 
                       WHERE status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $this->db->prepare($recentQuery);
        $result->execute();
        $recentData = $result->fetch(PDO::FETCH_ASSOC);
        $stats['recent_registrations'] = (int)$recentData['count'];
        
        // Active users (logged in last 30 days)
        $activeQuery = "SELECT COUNT(*) as count FROM {$this->table} 
                       WHERE status = 'active' AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $this->db->prepare($activeQuery);
        $result->execute();
        $activeData = $result->fetch(PDO::FETCH_ASSOC);
        $stats['active_users'] = (int)$activeData['count'];
        
        return $stats;
    }
    
    /**
     * Get user with additional info
     */
    public function getUserWithInfo($id) {
        $user = $this->find($id);
        
        if ($user) {
            // Add order count
            $orderQuery = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?";
            $result = $this->db->prepare($orderQuery);
            $result->execute([$id]);
            $orderData = $result->fetch(PDO::FETCH_ASSOC);
            $user['order_count'] = (int)$orderData['order_count'];
            
            // Add total spent
            $spentQuery = "SELECT SUM(total_amount) as total_spent FROM orders WHERE user_id = ? AND status = 'completed'";
            $result = $this->db->prepare($spentQuery);
            $result->execute([$id]);
            $spentData = $result->fetch(PDO::FETCH_ASSOC);
            $user['total_spent'] = (float)($spentData['total_spent'] ?? 0);
        }
        
        return $user;
    }
    
    /**
     * Validate user data
     */
    protected function validate($data) {
        $errors = [];
        
        // Name validation
        if (isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Name is required';
            } elseif (strlen($data['name']) < 2) {
                $errors['name'] = 'Name must be at least 2 characters';
            }
        }
        
        // Email validation
        if (isset($data['email'])) {
            if (empty($data['email'])) {
                $errors['email'] = 'Email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
        }
        
        // Password validation
        if (isset($data['password'])) {
            if (empty($data['password'])) {
                $errors['password'] = 'Password is required';
            } elseif (strlen($data['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters';
            }
        }
        
        // Role validation
        if (isset($data['role'])) {
            $validRoles = ['user', 'admin', 'moderator'];
            if (!in_array($data['role'], $validRoles)) {
                $errors['role'] = 'Invalid role';
            }
        }
        
        // Status validation
        if (isset($data['status'])) {
            $validStatuses = ['active', 'inactive', 'deleted'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status';
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}