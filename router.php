<?php
/**
 * Local Development Router Script for PHP Built-in Web Server.
 * Emulates the Apache .htaccess Rewrite rules.
 * 
 * Usage: php -S localhost:8000 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the resource is a physical static file (like images, css, js), serve it directly
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    if (pathinfo($uri, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}

// Route mapping rules
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/public/index.php';
} elseif ($uri === '/products') {
    require __DIR__ . '/public/products.php';
} elseif (preg_match('#^/product/([^/]+)$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/public/product.php';
} elseif (preg_match('#^/category/([^/]+)$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/public/category.php';
} elseif ($uri === '/contact') {
    require __DIR__ . '/public/contact.php';
} elseif ($uri === '/admin' || $uri === '/admin/dashboard.php') {
    require __DIR__ . '/admin/dashboard.php';
} elseif ($uri === '/admin/login' || $uri === '/admin/login.php') {
    require __DIR__ . '/admin/login.php';
} elseif ($uri === '/admin/logout' || $uri === '/admin/logout.php') {
    require __DIR__ . '/admin/logout.php';
} elseif ($uri === '/admin/products' || $uri === '/admin/products.php') {
    require __DIR__ . '/admin/products.php';
} elseif ($uri === '/admin/categories' || $uri === '/admin/categories.php') {
    require __DIR__ . '/admin/categories.php';
} elseif ($uri === '/admin/brands' || $uri === '/admin/brands.php') {
    require __DIR__ . '/admin/brands.php';
} elseif ($uri === '/admin/settings' || $uri === '/admin/settings.php') {
    require __DIR__ . '/admin/settings.php';
} elseif ($uri === '/admin/media' || $uri === '/admin/media.php') {
    require __DIR__ . '/admin/media.php';
} elseif ($uri === '/api/products') {
    require __DIR__ . '/api/products.php';
} elseif ($uri === '/api/categories') {
    require __DIR__ . '/api/categories.php';
} elseif ($uri === '/api/auth') {
    require __DIR__ . '/api/auth.php';
} else {
    // Return 404
    header("HTTP/1.1 404 Not Found");
    echo "404 Not Found - INCA Router";
}
