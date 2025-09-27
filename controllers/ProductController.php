<?php
/**
 * Product Controller
 * Handles CRUD operations for products
 */

class ProductController extends BaseController {
    
    /**
     * Get all products (with pagination and filtering)
     */
    public function index() {
        try {
            // Get pagination parameters
            $page = (int)($this->getQueryParam('page') ?? 1);
            $limit = (int)($this->getQueryParam('limit') ?? PAGINATION_LIMIT);
            $offset = ($page - 1) * $limit;
            
            // Get filter parameters
            $search = $this->getQueryParam('search');
            $category_id = $this->getQueryParam('category_id');
            $min_price = $this->getQueryParam('min_price');
            $max_price = $this->getQueryParam('max_price');
            $status = $this->getQueryParam('status') ?? 'active';
            
            // Build query
            $whereConditions = ["p.status = ?"];
            $params = [$status];
            
            if ($search) {
                $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if ($category_id) {
                $whereConditions[] = "p.category_id = ?";
                $params[] = $category_id;
            }
            
            if ($min_price) {
                $whereConditions[] = "p.price >= ?";
                $params[] = $min_price;
            }
            
            if ($max_price) {
                $whereConditions[] = "p.price <= ?";
                $params[] = $max_price;
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM products p $whereClause";
            $totalResult = $this->db->fetch($countQuery, $params);
            $total = $totalResult['total'];
            
            // Get products with category info
            $query = "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     $whereClause 
                     ORDER BY p.created_at DESC 
                     LIMIT $limit OFFSET $offset";
            $products = $this->db->fetchAll($query, $params);
            
            $this->response->paginated($products, $page, $limit, $total, 'Products retrieved successfully');
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to get products: ' . $e->getMessage());
        }
    }
    
    /**
     * Get single product by ID
     */
    public function show() {
        try {
            $id = $this->getParam('id');
            
            if (!$id) {
                $this->response->error('Product ID is required', 400);
                return;
            }
            
            $query = "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.id = ? AND p.status = 'active'";
            $product = $this->db->fetch($query, [$id]);
            
            if ($product) {
                $this->response->success(['product' => $product], 'Product retrieved successfully');
            } else {
                $this->response->notFound('Product not found');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to get product: ' . $e->getMessage());
        }
    }
    
    /**
     * Create new product
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
            $validator->required($data['name'] ?? '', 'Product name is required');
            $validator->minLength($data['name'] ?? '', 2, 'Product name must be at least 2 characters');
            $validator->required($data['description'] ?? '', 'Description is required');
            $validator->required($data['price'] ?? '', 'Price is required');
            $validator->numeric($data['price'] ?? '', 'Price must be a number');
            $validator->minValue($data['price'] ?? 0, 0, 'Price must be greater than or equal to 0');
            $validator->required($data['category_id'] ?? '', 'Category is required');
            $validator->integer($data['category_id'] ?? '', 'Invalid category ID');
            
            if ($validator->hasErrors()) {
                $this->response->validationError($validator->getErrors());
                return;
            }
            
            // Check if category exists
            $category = $this->db->fetch("SELECT id FROM categories WHERE id = ? AND status = 'active'", [$data['category_id']]);
            if (!$category) {
                $this->response->error('Invalid category', 400);
                return;
            }
            
            $name = $this->sanitize($data['name']);
            $description = $this->sanitize($data['description']);
            $price = (float)$data['price'];
            $category_id = (int)$data['category_id'];
            $stock = (int)($data['stock'] ?? 0);
            $sku = $this->sanitize($data['sku'] ?? '');
            $image_url = $this->sanitize($data['image_url'] ?? '');
            $status = $this->sanitize($data['status'] ?? 'active');
            
            // Insert new product
            $query = "INSERT INTO products (name, description, price, category_id, stock, sku, image_url, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $productId = $this->db->execute($query, [$name, $description, $price, $category_id, $stock, $sku, $image_url, $status]);
            
            if ($productId) {
                // Get created product with category info
                $createdProduct = $this->db->fetch(
                    "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.id = ?", 
                    [$productId]
                );
                
                $this->response->created(['product' => $createdProduct], 'Product created successfully');
            } else {
                $this->response->internalServerError('Failed to create product');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to create product: ' . $e->getMessage());
        }
    }
    
    /**
     * Update product
     */
    public function update() {
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
            
            $id = $this->getParam('id');
            $data = $this->getRequestData();
            
            if (!$id) {
                $this->response->error('Product ID is required', 400);
                return;
            }
            
            // Check if product exists
            $existingProduct = $this->db->fetch("SELECT * FROM products WHERE id = ?", [$id]);
            if (!$existingProduct) {
                $this->response->notFound('Product not found');
                return;
            }
            
            // Validate fields
            $validator = new Validator();
            
            if (isset($data['name'])) {
                $validator->required($data['name'], 'Product name is required');
                $validator->minLength($data['name'], 2, 'Product name must be at least 2 characters');
            }
            
            if (isset($data['price'])) {
                $validator->required($data['price'], 'Price is required');
                $validator->numeric($data['price'], 'Price must be a number');
                $validator->minValue($data['price'], 0, 'Price must be greater than or equal to 0');
            }
            
            if (isset($data['category_id'])) {
                $validator->required($data['category_id'], 'Category is required');
                $validator->integer($data['category_id'], 'Invalid category ID');
                
                // Check if category exists
                $category = $this->db->fetch("SELECT id FROM categories WHERE id = ? AND status = 'active'", [$data['category_id']]);
                if (!$category) {
                    $validator->addError('category_id', 'Invalid category');
                }
            }
            
            if (isset($data['stock'])) {
                $validator->integer($data['stock'], 'Stock must be an integer');
                $validator->minValue($data['stock'], 0, 'Stock must be greater than or equal to 0');
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
            
            if (isset($data['description'])) {
                $updateFields[] = "description = ?";
                $params[] = $this->sanitize($data['description']);
            }
            
            if (isset($data['price'])) {
                $updateFields[] = "price = ?";
                $params[] = (float)$data['price'];
            }
            
            if (isset($data['category_id'])) {
                $updateFields[] = "category_id = ?";
                $params[] = (int)$data['category_id'];
            }
            
            if (isset($data['stock'])) {
                $updateFields[] = "stock = ?";
                $params[] = (int)$data['stock'];
            }
            
            if (isset($data['sku'])) {
                $updateFields[] = "sku = ?";
                $params[] = $this->sanitize($data['sku']);
            }
            
            if (isset($data['image_url'])) {
                $updateFields[] = "image_url = ?";
                $params[] = $this->sanitize($data['image_url']);
            }
            
            if (isset($data['status'])) {
                $updateFields[] = "status = ?";
                $params[] = $this->sanitize($data['status']);
            }
            
            if (empty($updateFields)) {
                $this->response->error('No fields to update', 400);
                return;
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $id;
            
            $query = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $result = $this->db->execute($query, $params);
            
            if ($result) {
                // Get updated product with category info
                $updatedProduct = $this->db->fetch(
                    "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.id = ?", 
                    [$id]
                );
                $this->response->success(['product' => $updatedProduct], 'Product updated successfully');
            } else {
                $this->response->internalServerError('Failed to update product');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to update product: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete product
     */
    public function destroy() {
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
            
            $id = $this->getParam('id');
            
            if (!$id) {
                $this->response->error('Product ID is required', 400);
                return;
            }
            
            // Check if product exists
            $product = $this->db->fetch("SELECT * FROM products WHERE id = ?", [$id]);
            if (!$product) {
                $this->response->notFound('Product not found');
                return;
            }
            
            // Soft delete (update status to deleted)
            $query = "UPDATE products SET status = 'deleted', updated_at = NOW() WHERE id = ?";
            $result = $this->db->execute($query, [$id]);
            
            if ($result) {
                $this->response->success([], 'Product deleted successfully');
            } else {
                $this->response->internalServerError('Failed to delete product');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to delete product: ' . $e->getMessage());
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