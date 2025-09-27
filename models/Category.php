<?php
/**
 * Category Model
 * Handles category-specific database operations
 */

class Category extends BaseModel {
    protected $table = 'categories';
    protected $fillable = ['name', 'description', 'image_url', 'status'];
    
    /**
     * Get categories with product count
     */
    public function getWithProductCount($conditions = [], $orderBy = 'name ASC', $limit = null, $offset = null) {
        $query = "SELECT c.*, 
                        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') as product_count
                 FROM {$this->table} c";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "c.{$field} = ?";
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
     * Get single category with product count
     */
    public function findWithProductCount($id) {
        $query = "SELECT c.*, 
                        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') as product_count
                 FROM {$this->table} c 
                 WHERE c.id = ?";
        $result = $this->db->prepare($query);
        $result->execute([$id]);
        return $result->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get active categories
     */
    public function getActiveCategories($limit = null, $offset = null) {
        return $this->getWithProductCount(['status' => 'active'], 'name ASC', $limit, $offset);
    }
    
    /**
     * Get categories with products
     */
    public function getCategoriesWithProducts($categoryId = null) {
        $query = "SELECT c.*, p.id as product_id, p.name as product_name, p.price, p.stock, p.image_url as product_image
                 FROM {$this->table} c 
                 LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active' 
                 WHERE c.status = 'active'";
        
        $params = [];
        if ($categoryId) {
            $query .= " AND c.id = ?";
            $params[] = $categoryId;
        }
        
        $query .= " ORDER BY c.name ASC, p.name ASC";
        
        $result = $this->db->prepare($query);
        $result->execute($params);
        $data = $result->fetchAll(PDO::FETCH_ASSOC);
        
        // Group products by category
        $categories = [];
        foreach ($data as $row) {
            $categoryId = $row['id'];
            
            if (!isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'image_url' => $row['image_url'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'products' => []
                ];
            }
            
            if ($row['product_id']) {
                $categories[$categoryId]['products'][] = [
                    'id' => $row['product_id'],
                    'name' => $row['product_name'],
                    'price' => $row['price'],
                    'stock' => $row['stock'],
                    'image_url' => $row['product_image']
                ];
            }
        }
        
        return array_values($categories);
    }
    
    /**
     * Search categories
     */
    public function searchCategories($search, $limit = null, $offset = null) {
        $query = "SELECT c.*, 
                        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') as product_count
                 FROM {$this->table} c 
                 WHERE c.status = 'active' AND (c.name LIKE ? OR c.description LIKE ?) 
                 ORDER BY c.name ASC";
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
            if ($offset) {
                $query .= " OFFSET {$offset}";
            }
        }
        
        $searchTerm = "%{$search}%";
        $result = $this->db->prepare($query);
        $result->execute([$searchTerm, $searchTerm]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get popular categories (by product count)
     */
    public function getPopularCategories($limit = 10) {
        $query = "SELECT c.*, 
                        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') as product_count
                 FROM {$this->table} c 
                 WHERE c.status = 'active' 
                 HAVING product_count > 0 
                 ORDER BY product_count DESC 
                 LIMIT ?";
        
        $result = $this->db->prepare($query);
        $result->execute([$limit]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get categories by product sales
     */
    public function getCategoriesBySales($limit = 10) {
        $query = "SELECT c.*, 
                        COALESCE(SUM(oi.quantity), 0) as total_sold,
                        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') as product_count
                 FROM {$this->table} c 
                 LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                 LEFT JOIN order_items oi ON p.id = oi.product_id 
                 LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
                 WHERE c.status = 'active' 
                 GROUP BY c.id 
                 ORDER BY total_sold DESC 
                 LIMIT ?";
        
        $result = $this->db->prepare($query);
        $result->execute([$limit]);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if category has products
     */
    public function hasProducts($id) {
        $query = "SELECT COUNT(*) as count FROM products WHERE category_id = ? AND status = 'active'";
        $result = $this->db->prepare($query);
        $result->execute([$id]);
        $data = $result->fetch(PDO::FETCH_ASSOC);
        
        return (int)$data['count'] > 0;
    }
    
    /**
     * Check if category name exists
     */
    public function nameExists($name, $excludeId = null) {
        return $this->exists('name', $name, $excludeId);
    }
    
    /**
     * Get category statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total categories
        $stats['total'] = $this->count(['status' => 'active']);
        
        // Categories with products
        $withProductsQuery = "SELECT COUNT(DISTINCT c.id) as count 
                             FROM {$this->table} c 
                             INNER JOIN products p ON c.id = p.category_id 
                             WHERE c.status = 'active' AND p.status = 'active'";
        $result = $this->db->prepare($withProductsQuery);
        $result->execute();
        $withProductsData = $result->fetch(PDO::FETCH_ASSOC);
        $stats['with_products'] = (int)$withProductsData['count'];
        
        // Empty categories
        $stats['empty'] = $stats['total'] - $stats['with_products'];
        
        // Average products per category
        $avgProductsQuery = "SELECT AVG(product_count) as avg_products 
                            FROM (
                                SELECT COUNT(p.id) as product_count 
                                FROM {$this->table} c 
                                LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active' 
                                WHERE c.status = 'active' 
                                GROUP BY c.id
                            ) as category_counts";
        $result = $this->db->prepare($avgProductsQuery);
        $result->execute();
        $avgProductsData = $result->fetch(PDO::FETCH_ASSOC);
        $stats['avg_products_per_category'] = (float)($avgProductsData['avg_products'] ?? 0);
        
        // Most popular category
        $popularQuery = "SELECT c.name, COUNT(p.id) as product_count 
                        FROM {$this->table} c 
                        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active' 
                        WHERE c.status = 'active' 
                        GROUP BY c.id, c.name 
                        ORDER BY product_count DESC 
                        LIMIT 1";
        $result = $this->db->prepare($popularQuery);
        $result->execute();
        $popularData = $result->fetch(PDO::FETCH_ASSOC);
        $stats['most_popular'] = $popularData ? [
            'name' => $popularData['name'],
            'product_count' => (int)$popularData['product_count']
        ] : null;
        
        return $stats;
    }
    
    /**
     * Get category tree (if implementing hierarchical categories)
     */
    public function getCategoryTree($parentId = null) {
        // This method can be extended if you implement parent-child relationships
        // For now, return flat structure
        return $this->getActiveCategories();
    }
    
    /**
     * Validate category data
     */
    protected function validate($data) {
        $errors = [];
        
        // Name validation
        if (isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Category name is required';
            } elseif (strlen($data['name']) < 2) {
                $errors['name'] = 'Category name must be at least 2 characters';
            }
        }
        
        // Status validation
        if (isset($data['status'])) {
            $validStatuses = ['active', 'inactive', 'deleted'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status';
            }
        }
        
        // Image URL validation
        if (isset($data['image_url']) && !empty($data['image_url'])) {
            if (!filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
                $errors['image_url'] = 'Invalid image URL';
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}