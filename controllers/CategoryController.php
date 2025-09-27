<?php
/**
 * Category Controller
 * Handles CRUD operations for categories
 */

class CategoryController extends BaseController {
    
    /**
     * Get all categories (with pagination and filtering)
     */
    public function index() {
        try {
            // Get pagination parameters
            $page = (int)($this->getQueryParam('page') ?? 1);
            $limit = (int)($this->getQueryParam('limit') ?? PAGINATION_LIMIT);
            $offset = ($page - 1) * $limit;
            
            // Get filter parameters
            $search = $this->getQueryParam('search');
            $status = $this->getQueryParam('status') ?? 'active';
            
            // Build query
            $whereConditions = ["status = ?"];
            $params = [$status];
            
            if ($search) {
                $whereConditions[] = "(name LIKE ? OR description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM categories $whereClause";
            $totalResult = $this->db->fetch($countQuery, $params);
            $total = $totalResult['total'];
            
            // Get categories with product count
            $query = "SELECT c.*, 
                            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') as product_count
                     FROM categories c 
                     $whereClause 
                     ORDER BY c.name ASC 
                     LIMIT $limit OFFSET $offset";
            $categories = $this->db->fetchAll($query, $params);
            
            $this->response->paginated($categories, $page, $limit, $total, 'Categories retrieved successfully');
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to get categories: ' . $e->getMessage());
        }
    }
    
    /**
     * Get single category by ID
     */
    public function show() {
        try {
            $id = $this->getParam('id');
            
            if (!$id) {
                $this->response->error('Category ID is required', 400);
                return;
            }
            
            $query = "SELECT c.*, 
                            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') as product_count
                     FROM categories c 
                     WHERE c.id = ? AND c.status = 'active'";
            $category = $this->db->fetch($query, [$id]);
            
            if ($category) {
                // Get products in this category
                $productsQuery = "SELECT id, name, price, stock, image_url FROM products WHERE category_id = ? AND status = 'active' ORDER BY name ASC";
                $products = $this->db->fetchAll($productsQuery, [$id]);
                
                $category['products'] = $products;
                
                $this->response->success(['category' => $category], 'Category retrieved successfully');
            } else {
                $this->response->notFound('Category not found');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to get category: ' . $e->getMessage());
        }
    }
    
    /**
     * Create new category
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
            $validator->required($data['name'] ?? '', 'Category name is required');
            $validator->minLength($data['name'] ?? '', 2, 'Category name must be at least 2 characters');
            $validator->unique('categories', 'name', $data['name'] ?? '', 'Category name already exists');
            
            if ($validator->hasErrors()) {
                $this->response->validationError($validator->getErrors());
                return;
            }
            
            $name = $this->sanitize($data['name']);
            $description = $this->sanitize($data['description'] ?? '');
            $image_url = $this->sanitize($data['image_url'] ?? '');
            $status = $this->sanitize($data['status'] ?? 'active');
            
            // Insert new category
            $query = "INSERT INTO categories (name, description, image_url, status, created_at) VALUES (?, ?, ?, ?, NOW())";
            $categoryId = $this->db->execute($query, [$name, $description, $image_url, $status]);
            
            if ($categoryId) {
                $this->response->created([
                    'category' => [
                        'id' => $categoryId,
                        'name' => $name,
                        'description' => $description,
                        'image_url' => $image_url,
                        'status' => $status,
                        'product_count' => 0
                    ]
                ], 'Category created successfully');
            } else {
                $this->response->internalServerError('Failed to create category');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to create category: ' . $e->getMessage());
        }
    }
    
    /**
     * Update category
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
                $this->response->error('Category ID is required', 400);
                return;
            }
            
            // Check if category exists
            $existingCategory = $this->db->fetch("SELECT * FROM categories WHERE id = ?", [$id]);
            if (!$existingCategory) {
                $this->response->notFound('Category not found');
                return;
            }
            
            // Validate fields
            $validator = new Validator();
            
            if (isset($data['name'])) {
                $validator->required($data['name'], 'Category name is required');
                $validator->minLength($data['name'], 2, 'Category name must be at least 2 characters');
                
                // Check if name is unique (excluding current category)
                $nameCheck = $this->db->fetch("SELECT id FROM categories WHERE name = ? AND id != ?", [$data['name'], $id]);
                if ($nameCheck) {
                    $validator->addError('name', 'Category name already exists');
                }
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
            
            $query = "UPDATE categories SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $result = $this->db->execute($query, $params);
            
            if ($result) {
                // Get updated category with product count
                $updatedCategory = $this->db->fetch(
                    "SELECT c.*, 
                            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') as product_count
                     FROM categories c 
                     WHERE c.id = ?", 
                    [$id]
                );
                $this->response->success(['category' => $updatedCategory], 'Category updated successfully');
            } else {
                $this->response->internalServerError('Failed to update category');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to update category: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete category
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
                $this->response->error('Category ID is required', 400);
                return;
            }
            
            // Check if category exists
            $category = $this->db->fetch("SELECT * FROM categories WHERE id = ?", [$id]);
            if (!$category) {
                $this->response->notFound('Category not found');
                return;
            }
            
            // Check if category has products
            $productCount = $this->db->fetch("SELECT COUNT(*) as count FROM products WHERE category_id = ? AND status = 'active'", [$id]);
            if ($productCount['count'] > 0) {
                $this->response->error('Cannot delete category with active products', 400);
                return;
            }
            
            // Soft delete (update status to deleted)
            $query = "UPDATE categories SET status = 'deleted', updated_at = NOW() WHERE id = ?";
            $result = $this->db->execute($query, [$id]);
            
            if ($result) {
                $this->response->success([], 'Category deleted successfully');
            } else {
                $this->response->internalServerError('Failed to delete category');
            }
            
        } catch (Exception $e) {
            $this->response->internalServerError('Failed to delete category: ' . $e->getMessage());
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