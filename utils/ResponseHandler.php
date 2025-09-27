<?php
/**
 * Response Handler Utility
 * Standardizes API responses
 */

class ResponseHandler {
    
    /**
     * Send internal server error response (alias)
     */
    public function internalServerError($message = 'Internal server error') {
        $this->serverError($message);
    }
    /**
     * Send success response
     */
    public function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send error response
     */
    public function error($message = 'Error', $code = 400, $errors = null) {
        http_response_code($code);
        
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send paginated response
     */
    public function paginated($data, $pagination, $message = 'Success') {
        http_response_code(200);
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send created response
     */
    public function created($data = null, $message = 'Created successfully') {
        $this->success($data, $message, 201);
    }
    
    /**
     * Send no content response
     */
    public function noContent($message = 'No content') {
        http_response_code(204);
        echo json_encode([
            'success' => true,
            'message' => $message
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send not found response
     */
    public function notFound($message = 'Resource not found') {
        $this->error($message, 404);
    }
    
    /**
     * Send unauthorized response
     */
    public function unauthorized($message = 'Unauthorized') {
        $this->error($message, 401);
    }
    
    /**
     * Send forbidden response
     */
    public function forbidden($message = 'Forbidden') {
        $this->error($message, 403);
    }
    
    /**
     * Send validation error response
     */
    public function validationError($errors, $message = 'Validation failed') {
        $this->error($message, 422, $errors);
    }
    
    /**
     * Send internal server error response
     */
    public function serverError($message = 'Internal server error') {
        $this->error($message, 500);
    }
}