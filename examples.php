<?php
/**
 * API Usage Examples
 * This file contains practical examples of how to use the Bootcamp API
 */

// Example 1: User Registration and Login
function exampleUserAuth() {
    $apiUrl = 'http://localhost/bootcamp/api';
    
    // Register a new user
    $registerData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123'
    ];
    
    $response = makeApiRequest($apiUrl . '/auth/register', 'POST', $registerData);
    echo "Registration Response:\n";
    print_r($response);
    
    // Login to get token
    $loginData = [
        'email' => 'john@example.com',
        'password' => 'password123'
    ];
    
    $response = makeApiRequest($apiUrl . '/auth/login', 'POST', $loginData);
    echo "\nLogin Response:\n";
    print_r($response);
    
    if ($response['success']) {
        $token = $response['data']['token'];
        echo "\nToken: " . $token . "\n";
        return $token;
    }
    
    return null;
}

// Example 2: Product Management
function exampleProductManagement($token) {
    $apiUrl = 'http://localhost/bootcamp/api';
    
    // Create a new product (Admin required)
    $productData = [
        'name' => 'Gaming Laptop',
        'description' => 'High-performance gaming laptop with RTX graphics',
        'price' => 1299.99,
        'stock' => 50,
        'category_id' => 1,
        'sku' => 'LAPTOP001'
    ];
    
    $response = makeApiRequest($apiUrl . '/products', 'POST', $productData, $token);
    echo "Create Product Response:\n";
    print_r($response);
    
    // Get all products
    $response = makeApiRequest($apiUrl . '/products?page=1&limit=10');
    echo "\nGet Products Response:\n";
    print_r($response);
    
    // Search products
    $response = makeApiRequest($apiUrl . '/products?search=gaming&min_price=1000');
    echo "\nSearch Products Response:\n";
    print_r($response);
}

// Example 3: Category Management
function exampleCategoryManagement($token) {
    $apiUrl = 'http://localhost/bootcamp/api';
    
    // Create a new category (Admin required)
    $categoryData = [
        'name' => 'Electronics',
        'description' => 'Electronic devices and gadgets'
    ];
    
    $response = makeApiRequest($apiUrl . '/categories', 'POST', $categoryData, $token);
    echo "Create Category Response:\n";
    print_r($response);
    
    // Get all categories
    $response = makeApiRequest($apiUrl . '/categories');
    echo "\nGet Categories Response:\n";
    print_r($response);
}

// Example 4: Order Management
function exampleOrderManagement($token) {
    $apiUrl = 'http://localhost/bootcamp/api';
    
    // Create a new order
    $orderData = [
        'items' => [
            [
                'product_id' => 1,
                'quantity' => 2,
                'price' => 1299.99
            ],
            [
                'product_id' => 2,
                'quantity' => 1,
                'price' => 599.99
            ]
        ],
        'shipping_address' => '123 Main St, City, State 12345',
        'payment_method' => 'credit_card'
    ];
    
    $response = makeApiRequest($apiUrl . '/orders', 'POST', $orderData, $token);
    echo "Create Order Response:\n";
    print_r($response);
    
    // Get user's orders
    $response = makeApiRequest($apiUrl . '/orders', 'GET', null, $token);
    echo "\nGet Orders Response:\n";
    print_r($response);
}

// Example 5: File Upload
function exampleFileUpload($token) {
    $apiUrl = 'http://localhost/bootcamp/api';
    
    // Note: This is a simplified example. In practice, you'd use cURL with file upload
    echo "File Upload Example:\n";
    echo "Use the following cURL command to upload a file:\n";
    echo "curl -X POST {$apiUrl}/upload \\\n";
    echo "  -H \"Authorization: Bearer {$token}\" \\\n";
    echo "  -F \"file=@image.jpg\" \\\n";
    echo "  -F \"type=product_image\"\n";
}

// Example 6: Error Handling
function exampleErrorHandling() {
    $apiUrl = 'http://localhost/bootcamp/api';
    
    // Try to access protected endpoint without token
    $response = makeApiRequest($apiUrl . '/users');
    echo "Unauthorized Access Response:\n";
    print_r($response);
    
    // Try to create user with invalid data
    $invalidData = [
        'name' => '', // Empty name
        'email' => 'invalid-email', // Invalid email
        'password' => '123' // Too short password
    ];
    
    $response = makeApiRequest($apiUrl . '/auth/register', 'POST', $invalidData);
    echo "\nValidation Error Response:\n";
    print_r($response);
}

// Example 7: Pagination and Filtering
function examplePaginationAndFiltering() {
    $apiUrl = 'http://localhost/bootcamp/api';
    
    // Get products with pagination
    $response = makeApiRequest($apiUrl . '/products?page=1&limit=5');
    echo "Paginated Products Response:\n";
    print_r($response);
    
    // Get products with filtering
    $response = makeApiRequest($apiUrl . '/products?category=1&min_price=500&max_price=2000&sort=price&order=asc');
    echo "\nFiltered Products Response:\n";
    print_r($response);
}

// Example 8: Rate Limiting
function exampleRateLimiting() {
    $apiUrl = 'http://localhost/bootcamp/api';
    
    echo "Rate Limiting Example:\n";
    echo "Make multiple requests quickly to see rate limiting in action:\n";
    
    for ($i = 1; $i <= 5; $i++) {
        $response = makeApiRequest($apiUrl . '/health');
        echo "Request {$i}: ";
        
        if (isset($response['success'])) {
            echo "Success\n";
        } else {
            echo "Failed - " . ($response['error']['message'] ?? 'Unknown error') . "\n";
        }
        
        // Check rate limit headers (would be in actual HTTP response headers)
        echo "  Rate Limit Info would be in headers\n";
    }
}

// Helper function to make API requests
function makeApiRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $headers = ['Content-Type: application/json'];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        return [
            'success' => false,
            'error' => [
                'code' => 0,
                'message' => 'cURL Error: ' . curl_error($ch)
            ]
        ];
    }
    
    curl_close($ch);
    
    $decodedResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => [
                'code' => $httpCode,
                'message' => 'Invalid JSON response'
            ]
        ];
    }
    
    return $decodedResponse;
}

// JavaScript/AJAX Examples
function generateJavaScriptExamples() {
    echo "\n=== JavaScript/AJAX Examples ===\n";
    
    $jsExamples = '
// Example 1: User Login with JavaScript
async function loginUser(email, password) {
    try {
        const response = await fetch("http://localhost/bootcamp/api/auth/login", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                email: email,
                password: password
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            localStorage.setItem("token", data.data.token);
            console.log("Login successful:", data.data.user);
            return data.data.token;
        } else {
            console.error("Login failed:", data.error.message);
            return null;
        }
    } catch (error) {
        console.error("Network error:", error);
        return null;
    }
}

// Example 2: Get Products with Authentication
async function getProducts(page = 1, limit = 10, search = "") {
    const token = localStorage.getItem("token");
    
    try {
        const url = new URL("http://localhost/bootcamp/api/products");
        url.searchParams.append("page", page);
        url.searchParams.append("limit", limit);
        if (search) url.searchParams.append("search", search);
        
        const response = await fetch(url, {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
                "Authorization": token ? `Bearer ${token}` : ""
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log("Products:", data.data);
            console.log("Pagination:", data.pagination);
            return data;
        } else {
            console.error("Failed to get products:", data.error.message);
            return null;
        }
    } catch (error) {
        console.error("Network error:", error);
        return null;
    }
}

// Example 3: Create Product (Admin)
async function createProduct(productData) {
    const token = localStorage.getItem("token");
    
    if (!token) {
        console.error("No authentication token found");
        return null;
    }
    
    try {
        const response = await fetch("http://localhost/bootcamp/api/products", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": `Bearer ${token}`
            },
            body: JSON.stringify(productData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log("Product created:", data.data);
            return data.data;
        } else {
            console.error("Failed to create product:", data.error.message);
            if (data.error.details) {
                console.error("Validation errors:", data.error.details);
            }
            return null;
        }
    } catch (error) {
        console.error("Network error:", error);
        return null;
    }
}

// Example 4: File Upload with Progress
async function uploadFile(file, type = "product_image") {
    const token = localStorage.getItem("token");
    
    if (!token) {
        console.error("No authentication token found");
        return null;
    }
    
    const formData = new FormData();
    formData.append("file", file);
    formData.append("type", type);
    
    try {
        const response = await fetch("http://localhost/bootcamp/api/upload", {
            method: "POST",
            headers: {
                "Authorization": `Bearer ${token}`
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log("File uploaded:", data.data);
            return data.data;
        } else {
            console.error("Upload failed:", data.error.message);
            return null;
        }
    } catch (error) {
        console.error("Upload error:", error);
        return null;
    }
}

// Example 5: Error Handling with Retry
async function apiRequestWithRetry(url, options, maxRetries = 3) {
    for (let i = 0; i < maxRetries; i++) {
        try {
            const response = await fetch(url, options);
            const data = await response.json();
            
            if (response.status === 429) {
                // Rate limited, wait and retry
                const retryAfter = response.headers.get("Retry-After") || 60;
                console.log(`Rate limited. Retrying after ${retryAfter} seconds...`);
                await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
                continue;
            }
            
            return data;
        } catch (error) {
            if (i === maxRetries - 1) throw error;
            console.log(`Request failed, retrying... (${i + 1}/${maxRetries})`);
            await new Promise(resolve => setTimeout(resolve, 1000 * (i + 1)));
        }
    }
}

// Usage Examples:
// loginUser("user@example.com", "password123");
// getProducts(1, 10, "laptop");
// createProduct({
//     name: "Gaming Mouse",
//     description: "High-precision gaming mouse",
//     price: 79.99,
//     stock: 100,
//     category_id: 1,
//     sku: "MOUSE001"
// });
';
    
    echo $jsExamples;
}

// Run examples (uncomment to test)
if (php_sapi_name() === 'cli') {
    echo "=== Bootcamp API Usage Examples ===\n\n";
    
    // Example 1: Authentication
    echo "1. User Authentication Example:\n";
    $token = exampleUserAuth();
    
    if ($token) {
        echo "\n2. Product Management Example:\n";
        exampleProductManagement($token);
        
        echo "\n3. Category Management Example:\n";
        exampleCategoryManagement($token);
        
        echo "\n4. Order Management Example:\n";
        exampleOrderManagement($token);
        
        echo "\n5. File Upload Example:\n";
        exampleFileUpload($token);
    }
    
    echo "\n6. Error Handling Example:\n";
    exampleErrorHandling();
    
    echo "\n7. Pagination and Filtering Example:\n";
    examplePaginationAndFiltering();
    
    echo "\n8. Rate Limiting Example:\n";
    exampleRateLimiting();
    
    // Generate JavaScript examples
    generateJavaScriptExamples();
    
    echo "\n=== Examples Complete ===\n";
    echo "Note: Make sure your API server is running and database is set up before testing these examples.\n";
}
?>