<?php
/**
 * Simple JWT Implementation
 * For authentication tokens
 */

class JWT {
    
    /**
     * Encode JWT token
     */
    public static function encode($payload, $secret = null) {
        $secret = $secret ?: JWT_SECRET;
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Decode JWT token
     */
    public static function decode($token, $secret = null) {
        $secret = $secret ?: JWT_SECRET;
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        
        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Header)), true);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);
        
        // Verify signature
        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Signature));
        $expectedSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Invalid token signature');
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('Token has expired');
        }
        
        return $payload;
    }
    
    /**
     * Create token for user
     */
    public static function createUserToken($userId, $username, $role = 'user') {
        $payload = [
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRATION
        ];
        
        return self::encode($payload);
    }
    
    /**
     * Verify token and return user data
     */
    public static function verifyUserToken($token) {
        try {
            $payload = self::decode($token);
            return [
                'user_id' => $payload['user_id'],
                'username' => $payload['username'],
                'role' => $payload['role']
            ];
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get token from Authorization header
     */
    public static function getTokenFromHeader() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
}