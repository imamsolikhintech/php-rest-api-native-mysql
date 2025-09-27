<?php
/**
 * Validation Utility
 * Provides common validation methods
 */

class Validator {
    private $errors = [];
    /**
     * Validate minimum numeric value
     */
    public function minValue($value, $field, $min) {
        if (!empty($value) && is_numeric($value) && $value < $min) {
            $this->errors[$field] = ucfirst($field) . " must be at least {$min}";
            return false;
        }
        return true;
    }
    /**
     * Add a custom error message for a field
     */
    public function addError($field, $message) {
        $this->errors[$field] = $message;
    }
    /**
     * Validate password strength
     */
    public function passwordStrength($value, $field) {
        if (empty($value)) {
            return true;
        }
        
        $errors = [];
        
        if (strlen($value) < 8) {
            $errors[] = 'at least 8 characters';
        }
        
        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = 'at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = 'at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $value)) {
            $errors[] = 'at least one number';
        }
        
        if (!empty($errors)) {
            $this->errors[$field] = 'Password must contain ' . implode(', ', $errors);
            return false;
        }
        
        return true;
    }
    /**
     * Check if there are any errors
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    /**
     * Validate required field
     */
    public function required($value, $field) {
        if (empty($value) && $value !== '0') {
            $this->errors[$field] = ucfirst($field) . ' is required';
            return false;
        }
        return true;
    }
    
    /**
     * Validate email format
     */
    public function email($value, $field) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = ucfirst($field) . ' must be a valid email address';
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($value, $field, $min) {
        if (!empty($value) && strlen($value) < $min) {
            $this->errors[$field] = ucfirst($field) . " must be at least {$min} characters long";
            return false;
        }
        return true;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($value, $field, $max) {
        if (!empty($value) && strlen($value) > $max) {
            $this->errors[$field] = ucfirst($field) . " must not exceed {$max} characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate numeric value
     */
    public function numeric($value, $field) {
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$field] = ucfirst($field) . ' must be a number';
            return false;
        }
        return true;
    }
    
    /**
     * Validate integer value
     */
    public function integer($value, $field) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->errors[$field] = ucfirst($field) . ' must be an integer';
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum value
     */
    public function min($value, $field, $min) {
        if (!empty($value) && $value < $min) {
            $this->errors[$field] = ucfirst($field) . " must be at least {$min}";
            return false;
        }
        return true;
    }
    
    /**
     * Validate maximum value
     */
    public function max($value, $field, $max) {
        if (!empty($value) && $value > $max) {
            $this->errors[$field] = ucfirst($field) . " must not exceed {$max}";
            return false;
        }
        return true;
    }
    
    /**
     * Validate value is in array
     */
    public function in($value, $field, $array) {
        if (!empty($value) && !in_array($value, $array)) {
            $this->errors[$field] = ucfirst($field) . ' must be one of: ' . implode(', ', $array);
            return false;
        }
        return true;
    }
    
    /**
     * Validate unique value in database
     */
    public function unique($value, $field, $table, $column, $excludeId = null) {
        if (empty($value)) {
            return true;
        }
        
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
        $params = [$value];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $db->fetch($sql, $params);
        
        if ($result['count'] > 0) {
            $this->errors[$field] = ucfirst($field) . ' already exists';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate password strength
     */
    public function password($value, $field) {
        if (empty($value)) {
            return true;
        }
        
        $errors = [];
        
        if (strlen($value) < 8) {
            $errors[] = 'at least 8 characters';
        }
        
        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = 'at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = 'at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $value)) {
            $errors[] = 'at least one number';
        }
        
        if (!empty($errors)) {
            $this->errors[$field] = 'Password must contain ' . implode(', ', $errors);
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate URL format
     */
    public function url($value, $field) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errors[$field] = ucfirst($field) . ' must be a valid URL';
            return false;
        }
        return true;
    }
    
    /**
     * Validate date format
     */
    public function date($value, $field, $format = 'Y-m-d') {
        if (!empty($value)) {
            $d = DateTime::createFromFormat($format, $value);
            if (!$d || $d->format($format) !== $value) {
                $this->errors[$field] = ucfirst($field) . " must be a valid date in format {$format}";
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get all validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid() {
        return empty($this->errors);
    }
    
    /**
     * Clear all errors
     */
    public function clearErrors() {
        $this->errors = [];
    }
    
    /**
     * Validate data against rules
     */
    public function validate($data, $rules) {
        $this->clearErrors();
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? '';
            
            foreach ($fieldRules as $rule => $params) {
                if (is_numeric($rule)) {
                    // Simple rule without parameters
                    $rule = $params;
                    $params = [];
                } elseif (!is_array($params)) {
                    // Single parameter
                    $params = [$params];
                }
                
                // Call validation method
                if (method_exists($this, $rule)) {
                    call_user_func_array([$this, $rule], array_merge([$value, $field], $params));
                }
            }
        }
        
        return $this->isValid();
    }
}