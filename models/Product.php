<?php
/**
 * Product Model
 * Handles product-specific database operations
 */

class Product extends BaseModel {
    protected $table = 'products';
    protected $fillable = ['name', 'description', 'price', 'category_id', 'stock', 'sku', 'image_url', 'status'];
    
    /**
     * Get products with category information
     */
    public function getWithCategory($conditions = [], $orderBy = 'p.created_at DESC', $limit = null, $offset = null) {
        $query = "SELECT p.*, c.name as category_name 
                 FROM {$this->table} p 
                 LEFT JOIN categories c ON p.category_id = c.id";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "p.{$field} = ?";
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
     * Get single product with category
     */
    public function findWithCategory($id) {
        $query = "SELECT p.*, c.name as category_name 
                 FROM {$this->table} p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.id = ?";
        $result = $this->db->prepare($query);
        $result->execute([$id]);
        return $result->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get products by category
     */
    public function getByCategory($categoryId, $limit = null, $offset = null) {
        return $this->getWithCategory(['category_id' => $categoryId, 'status' => 'active'], 'p.name ASC', $limit, $offset);
    }
    
    /**
     * Search products
     */
    public function searchProducts($search, $categoryId = null, $minPrice = null, $maxPrice = null, $limit = null, $offset = null) {
        $query = "SELECT p.*, c.name as category_name 
                 FROM {$this->table} p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.status = 'active' AND (p.name LIKE ? OR p.description LIKE ?)";
        
        $searchTerm = "%{$search}%";
        $params = [$searchTerm, $searchTerm];
        
        if ($categoryId) {
            $query .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        
        if ($minPrice !== null) {
            $query .= " AND p.price >= ?";
            $params[] = $minPrice;
        }
        
        if ($maxPrice !== null) {
            $query .= " AND p.price <= ?";
            $params[] = $maxPrice;
        }
        
        $query .= " ORDER BY p.name ASC";
        
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
     * Get featured products
     */
    public function getFeaturedProducts($limit = 10) {
        return $this->getWithCategory(['status' => 'active'], 'p.created_at DESC', $limit);
    }
    
    /**
     * Get low stock products
     */
    public function getLowStockProducts($threshold = 10) {
        $query = "SELECT p.*, c.name as category_name 
                 FROM {$this->table} p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.status = 'active' AND p.stock <= ? 
                 ORDER BY p.stock ASC";
        
        $result = $this->db->prepare($query);
        $result->execute([$threshold]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update stock
     */
    public function updateStock($id, $quantity, $operation = 'decrease') {
        $operator = $operation === 'increase' ? '+' : '-';
        
        $query = "UPDATE {$this->table} SET stock = stock {$operator} ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->prepare($query);
        return $result->execute([$quantity, $id]);
    }
    
    /**
     * Check stock availability
     */
    public function checkStock($id, $quantity) {
        $product = $this->find($id);
        return $product && $product['stock'] >= $quantity;
    }
    
    /**
     * Get products by price range
     */
    public function getByPriceRange($minPrice, $maxPrice, $limit = null, $offset = null) {
        $query = "SELECT p.*, c.name as category_name 
                 FROM {$this->table} p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.status = 'active' AND p.price BETWEEN ? AND ? 
                 ORDER BY p.price ASC";
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
            if ($offset) {
                $query .= " OFFSET {$offset}";
            }
        }
        
        $result = $this->db->prepare($query);
        $result->execute([$minPrice, $maxPrice]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get product statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total products
        $stats['total'] = $this->count(['status' => 'active']);
        
        // Products by category
        $categoryQuery = "SELECT c.name, COUNT(p.id) as count 
                         FROM categories c 
                         LEFT JOIN {$this->table} p ON c.id = p.category_id AND p.status = 'active' 
                         WHERE c.status = 'active' 
                         GROUP BY c.id, c.name";
        $result = $this->db->prepare($categoryQuery);
        $result->execute();
        $categoryStats = $result->fetchAll(PDO::FETCH_ASSOC);
        
        $stats['by_category'] = [];
        foreach ($categoryStats as $categoryStat) {
            $stats['by_category'][$categoryStat['name']] = (int)$categoryStat['count'];
        }
        
        // Low stock products
        $lowStockQuery = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = 'active' AND stock <= 10";
        $result = $this->db->prepare($lowStockQuery);
        $result->execute();
        $lowStockData = $result->fetch(PDO::FETCH_ASSOC);
        $stats['low_stock'] = (int)$lowStockData['count'];
        
        // Out of stock products
        $outOfStockQuery = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = 'active' AND stock = 0";
        $result = $this->db->prepare($outOfStockQuery);
        $result->execute();
        $outOfStockData = $result->fetch(PDO::FETCH_ASSOC);
        $stats['out_of_stock'] = (int)$outOfStockData['count'];
        
        // Average price
        $avgPriceQuery = "SELECT AVG(price) as avg_price FROM {$this->table} WHERE status = 'active'";
        $result = $this->db->prepare($avgPriceQuery);
        $result->execute();
        $avgPriceData = $result->fetch(PDO::FETCH_ASSOC);
        $stats['average_price'] = (float)($avgPriceData['avg_price'] ?? 0);
        
        // Total inventory value
        $inventoryQuery = "SELECT SUM(price * stock) as total_value FROM {$this->table} WHERE status = 'active'";
        $result = $this->db->prepare($inventoryQuery);
        $result->execute();
        $inventoryData = $result->fetch(PDO::FETCH_ASSOC);
        $stats['total_inventory_value'] = (float)($inventoryData['total_value'] ?? 0);
        
        return $stats;
    }
    
    /**
     * Check if SKU exists
     */
    public function skuExists($sku, $excludeId = null) {
        if (empty($sku)) {
            return false;
        }
        return $this->exists('sku', $sku, $excludeId);
    }
    
    /**
     * Get related products (same category)
     */
    public function getRelatedProducts($productId, $limit = 5) {
        $product = $this->find($productId);
        if (!$product) {
            return [];
        }
        
        $query = "SELECT p.*, c.name as category_name 
                 FROM {$this->table} p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' 
                 ORDER BY RAND() 
                 LIMIT ?";
        
        $result = $this->db->prepare($query);
        $result->execute([$product['category_id'], $productId, $limit]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get top selling products
     */
    public function getTopSellingProducts($limit = 10) {
        $query = "SELECT p.*, c.name as category_name, 
                        COALESCE(SUM(oi.quantity), 0) as total_sold
                 FROM {$this->table} p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 LEFT JOIN order_items oi ON p.id = oi.product_id 
                 LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
                 WHERE p.status = 'active' 
                 GROUP BY p.id 
                 ORDER BY total_sold DESC 
                 LIMIT ?";
        
        $result = $this->db->prepare($query);
        $result->execute([$limit]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Validate product data
     */
    protected function validate($data) {
        $errors = [];
        
        // Name validation
        if (isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Product name is required';
            } elseif (strlen($data['name']) < 2) {
                $errors['name'] = 'Product name must be at least 2 characters';
            }
        }
        
        // Price validation
        if (isset($data['price'])) {
            if (!is_numeric($data['price'])) {
                $errors['price'] = 'Price must be a number';
            } elseif ($data['price'] < 0) {
                $errors['price'] = 'Price must be greater than or equal to 0';
            }
        }
        
        // Stock validation
        if (isset($data['stock'])) {
            if (!is_numeric($data['stock']) || !is_int((int)$data['stock'])) {
                $errors['stock'] = 'Stock must be an integer';
            } elseif ($data['stock'] < 0) {
                $errors['stock'] = 'Stock must be greater than or equal to 0';
            }
        }
        
        // Category validation
        if (isset($data['category_id'])) {
            if (!is_numeric($data['category_id'])) {
                $errors['category_id'] = 'Invalid category ID';
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