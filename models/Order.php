<?php
/**
 * Order Model
 * Handles order-specific database operations
 */

class Order extends BaseModel {
    protected $table = 'orders';
    protected $fillable = ['user_id', 'total_amount', 'status', 'shipping_address', 'payment_method', 'notes'];
    
    /**
     * Get orders with user information
     */
    public function getWithUser($conditions = [], $orderBy = 'created_at DESC', $limit = null, $offset = null) {
        $query = "SELECT o.*, u.name as user_name, u.email as user_email
                 FROM {$this->table} o 
                 LEFT JOIN users u ON o.user_id = u.id";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "o.{$field} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($orderBy) {
            $query .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
            if ($offset) {
                $query .= " OFFSET {$offset}";
            }
        }
        
        $result = $this->db->prepare($query);
        $result->execute($params);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get single order with user and items
     */
    public function findWithDetails($id) {
        // Get order with user info
        $orderQuery = "SELECT o.*, u.name as user_name, u.email as user_email
                      FROM {$this->table} o 
                      LEFT JOIN users u ON o.user_id = u.id 
                      WHERE o.id = ?";
        $result = $this->db->prepare($orderQuery);
        $result->execute([$id]);
        $order = $result->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return null;
        }
        
        // Get order items
        $itemsQuery = "SELECT oi.*, p.name as product_name, p.image_url as product_image
                      FROM order_items oi 
                      LEFT JOIN products p ON oi.product_id = p.id 
                      WHERE oi.order_id = ?";
        $result = $this->db->prepare($itemsQuery);
        $result->execute([$id]);
        $order['items'] = $result->fetchAll(PDO::FETCH_ASSOC);
        
        return $order;
    }
    
    /**
     * Get orders by user
     */
    public function getByUser($userId, $status = null, $limit = null, $offset = null) {
        $conditions = ['user_id' => $userId];
        if ($status) {
            $conditions['status'] = $status;
        }
        
        return $this->getWithUser($conditions, 'created_at DESC', $limit, $offset);
    }
    
    /**
     * Get orders by status
     */
    public function getByStatus($status, $limit = null, $offset = null) {
        return $this->getWithUser(['status' => $status], 'created_at DESC', $limit, $offset);
    }
    
    /**
     * Get orders by date range
     */
    public function getByDateRange($startDate, $endDate, $limit = null, $offset = null) {
        $query = "SELECT o.*, u.name as user_name, u.email as user_email
                 FROM {$this->table} o 
                 LEFT JOIN users u ON o.user_id = u.id 
                 WHERE DATE(o.created_at) BETWEEN ? AND ? 
                 ORDER BY o.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
            if ($offset) {
                $query .= " OFFSET {$offset}";
            }
        }
        
        $result = $this->db->prepare($query);
        $result->execute([$startDate, $endDate]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create order with items
     */
    public function createWithItems($orderData, $items) {
        try {
            $this->db->beginTransaction();
            
            // Create order
            $orderId = $this->create($orderData);
            
            // Create order items
            foreach ($items as $item) {
                $itemData = [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ];
                
                $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                $result = $this->db->prepare($itemQuery);
                $result->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
                
                // Update product stock
                $stockQuery = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $stockResult = $this->db->prepare($stockQuery);
                $stockResult->execute([$item['quantity'], $item['product_id']]);
            }
            
            $this->db->commit();
            return $orderId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Update order status
     */
    public function updateStatus($id, $status) {
        $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException('Invalid order status');
        }
        
        return $this->update($id, ['status' => $status]);
    }
    
    /**
     * Cancel order
     */
    public function cancelOrder($id) {
        try {
            $this->db->beginTransaction();
            
            // Get order items to restore stock
            $itemsQuery = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
            $result = $this->db->prepare($itemsQuery);
            $result->execute([$id]);
            $items = $result->fetchAll(PDO::FETCH_ASSOC);
            
            // Restore product stock
            foreach ($items as $item) {
                $stockQuery = "UPDATE products SET stock = stock + ? WHERE id = ?";
                $stockResult = $this->db->prepare($stockQuery);
                $stockResult->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Update order status
            $this->update($id, ['status' => 'cancelled']);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get order statistics
     */
    public function getStatistics($startDate = null, $endDate = null) {
        $stats = [];
        $dateCondition = '';
        $params = [];
        
        if ($startDate && $endDate) {
            $dateCondition = " WHERE DATE(created_at) BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
        }
        
        // Total orders
        $totalQuery = "SELECT COUNT(*) as count FROM {$this->table}{$dateCondition}";
        $result = $this->db->prepare($totalQuery);
        $result->execute($params);
        $stats['total_orders'] = (int)$result->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Orders by status
        $statusQuery = "SELECT status, COUNT(*) as count FROM {$this->table}{$dateCondition} GROUP BY status";
        $result = $this->db->prepare($statusQuery);
        $result->execute($params);
        $statusData = $result->fetchAll(PDO::FETCH_ASSOC);
        $stats['by_status'] = [];
        foreach ($statusData as $row) {
            $stats['by_status'][$row['status']] = (int)$row['count'];
        }
        
        // Total revenue
        $revenueQuery = "SELECT SUM(total_amount) as total FROM {$this->table}{$dateCondition} AND status = 'completed'";
        $revenueParams = $params;
        if (!empty($params)) {
            $revenueParams[] = 'completed';
        } else {
            $revenueQuery = "SELECT SUM(total_amount) as total FROM {$this->table} WHERE status = 'completed'";
            $revenueParams = ['completed'];
        }
        $result = $this->db->prepare($revenueQuery);
        $result->execute($revenueParams);
        $stats['total_revenue'] = (float)($result->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // Average order value
        $avgQuery = "SELECT AVG(total_amount) as avg FROM {$this->table}{$dateCondition} AND status = 'completed'";
        $result = $this->db->prepare($avgQuery);
        $result->execute($revenueParams);
        $stats['average_order_value'] = (float)($result->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0);
        
        // Recent orders (last 7 days)
        $recentQuery = "SELECT COUNT(*) as count FROM {$this->table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $result = $this->db->prepare($recentQuery);
        $result->execute();
        $stats['recent_orders'] = (int)$result->fetch(PDO::FETCH_ASSOC)['count'];
        
        return $stats;
    }
    
    /**
     * Get top customers by order count
     */
    public function getTopCustomers($limit = 10) {
        $query = "SELECT u.id, u.name, u.email, 
                        COUNT(o.id) as order_count, 
                        SUM(o.total_amount) as total_spent
                 FROM users u 
                 INNER JOIN {$this->table} o ON u.id = o.user_id 
                 WHERE o.status = 'completed' 
                 GROUP BY u.id, u.name, u.email 
                 ORDER BY order_count DESC 
                 LIMIT ?";
        
        $result = $this->db->prepare($query);
        $result->execute([$limit]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get monthly sales data
     */
    public function getMonthlySales($year = null) {
        if (!$year) {
            $year = date('Y');
        }
        
        $query = "SELECT 
                    MONTH(created_at) as month,
                    COUNT(*) as order_count,
                    SUM(total_amount) as total_revenue
                 FROM {$this->table} 
                 WHERE YEAR(created_at) = ? AND status = 'completed'
                 GROUP BY MONTH(created_at) 
                 ORDER BY month";
        
        $result = $this->db->prepare($query);
        $result->execute([$year]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get pending orders count
     */
    public function getPendingCount() {
        return $this->count(['status' => 'pending']);
    }
    
    /**
     * Search orders
     */
    public function searchOrders($search, $limit = null, $offset = null) {
        $query = "SELECT o.*, u.name as user_name, u.email as user_email
                 FROM {$this->table} o 
                 LEFT JOIN users u ON o.user_id = u.id 
                 WHERE o.id LIKE ? OR u.name LIKE ? OR u.email LIKE ? 
                 ORDER BY o.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
            if ($offset) {
                $query .= " OFFSET {$offset}";
            }
        }
        
        $searchTerm = "%{$search}%";
        $result = $this->db->prepare($query);
        $result->execute([$searchTerm, $searchTerm, $searchTerm]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Validate order data
     */
    protected function validate($data) {
        $errors = [];
        
        // User ID validation
        if (isset($data['user_id'])) {
            if (empty($data['user_id'])) {
                $errors['user_id'] = 'User ID is required';
            } elseif (!is_numeric($data['user_id'])) {
                $errors['user_id'] = 'User ID must be numeric';
            }
        }
        
        // Total amount validation
        if (isset($data['total_amount'])) {
            if (empty($data['total_amount'])) {
                $errors['total_amount'] = 'Total amount is required';
            } elseif (!is_numeric($data['total_amount']) || $data['total_amount'] <= 0) {
                $errors['total_amount'] = 'Total amount must be a positive number';
            }
        }
        
        // Status validation
        if (isset($data['status'])) {
            $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status';
            }
        }
        
        // Payment method validation
        if (isset($data['payment_method'])) {
            $validMethods = ['cash', 'credit_card', 'debit_card', 'bank_transfer', 'e_wallet'];
            if (!in_array($data['payment_method'], $validMethods)) {
                $errors['payment_method'] = 'Invalid payment method';
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}