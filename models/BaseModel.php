<?php
/**
 * Base Model
 * Provides common database operations for all models
 */

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = ['password'];
    protected $timestamps = true;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Find record by ID
     */
    public function find($id) {
        $query = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $result = $this->db->prepare($query);
        $result->execute([$id]);
        $data = $result->fetch(PDO::FETCH_ASSOC);
        
        return $data ? $this->hideFields($data) : null;
    }
    
    /**
     * Find record by field
     */
    public function findBy($field, $value) {
        $query = "SELECT * FROM {$this->table} WHERE {$field} = ?";
        $result = $this->db->prepare($query);
        $result->execute([$value]);
        $data = $result->fetch(PDO::FETCH_ASSOC);
        
        return $data ? $this->hideFields($data) : null;
    }
    
    /**
     * Get all records with optional conditions
     */
    public function all($conditions = [], $orderBy = null, $limit = null, $offset = null) {
        $query = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
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
        $data = $result->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map([$this, 'hideFields'], $data);
    }
    
    /**
     * Create new record
     */
    public function create($data) {
        $data = $this->filterFillable($data);
        
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $query = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $result = $this->db->prepare($query);
        $success = $result->execute(array_values($data));
        
        if ($success) {
            $id = $this->db->lastInsertId();
            return $this->find($id);
        }
        
        return false;
    }
    
    /**
     * Update record
     */
    public function update($id, $data) {
        $data = $this->filterFillable($data);
        
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $fields = array_keys($data);
        $setClause = array_map(function($field) {
            return "{$field} = ?";
        }, $fields);
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $setClause) . " WHERE {$this->primaryKey} = ?";
        
        $params = array_values($data);
        $params[] = $id;
        
        $result = $this->db->prepare($query);
        $success = $result->execute($params);
        
        return $success ? $this->find($id) : false;
    }
    
    /**
     * Delete record
     */
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $result = $this->db->prepare($query);
        return $result->execute([$id]);
    }
    
    /**
     * Soft delete record
     */
    public function softDelete($id) {
        return $this->update($id, ['status' => 'deleted']);
    }
    
    /**
     * Count records
     */
    public function count($conditions = []) {
        $query = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $result = $this->db->prepare($query);
        $result->execute($params);
        $data = $result->fetch(PDO::FETCH_ASSOC);
        
        return (int)$data['total'];
    }
    
    /**
     * Check if record exists
     */
    public function exists($field, $value, $excludeId = null) {
        $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$field} = ?";
        $params = [$value];
        
        if ($excludeId) {
            $query .= " AND {$this->primaryKey} != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->prepare($query);
        $result->execute($params);
        $data = $result->fetch(PDO::FETCH_ASSOC);
        
        return (int)$data['total'] > 0;
    }
    
    /**
     * Get paginated results
     */
    public function paginate($page = 1, $limit = 10, $conditions = [], $orderBy = null) {
        $offset = ($page - 1) * $limit;
        
        $total = $this->count($conditions);
        $data = $this->all($conditions, $orderBy, $limit, $offset);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit),
                'has_next' => $page < ceil($total / $limit),
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Execute custom query
     */
    public function query($sql, $params = []) {
        $result = $this->db->prepare($sql);
        $result->execute($params);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute custom query and return single result
     */
    public function queryOne($sql, $params = []) {
        $result = $this->db->prepare($sql);
        $result->execute($params);
        $data = $result->fetch(PDO::FETCH_ASSOC);
        
        return $data ? $this->hideFields($data) : null;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->db->rollback();
    }
    
    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Hide sensitive fields from output
     */
    protected function hideFields($data) {
        if (empty($this->hidden)) {
            return $data;
        }
        
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        
        return $data;
    }
    
    /**
     * Validate data before save
     */
    protected function validate($data) {
        // Override in child classes for specific validation
        return true;
    }
    
    /**
     * Get table name
     */
    public function getTable() {
        return $this->table;
    }
    
    /**
     * Get primary key
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
    }
    
    /**
     * Get fillable fields
     */
    public function getFillable() {
        return $this->fillable;
    }
}