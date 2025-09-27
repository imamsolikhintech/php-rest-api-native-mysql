<?php
/**
 * Simple Autoloader for API Classes
 */

spl_autoload_register(function ($className) {
    // Define base directories for different types of classes
    $directories = [
        __DIR__ . '/../core/',
        __DIR__ . '/../controllers/',
        __DIR__ . '/../models/',
        __DIR__ . '/../middleware/',
        __DIR__ . '/../utils/',
        __DIR__ . '/../services/'
    ];
    
    // Try to load the class from each directory
    foreach ($directories as $directory) {
        $file = $directory . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    // If class not found, throw an exception
    throw new Exception("Class {$className} not found");
});