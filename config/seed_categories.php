<?php
/**
 * Category Seeder.
 * Seeds specific corporate product categories into the database.
 */

if (PHP_SAPI !== 'cli') {
    header("HTTP/1.1 403 Forbidden");
    exit("This script can only be run from the command line.");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$categories = [
    'BLUM ÜRÜNLERİ',
    'MOBİLYA AKSESUARLARI',
    'ALÜMİNYUM GRUBU',
    'HIRDAVAT MALZEMELERİ',
    'MUTFAK AKSESUARLARI',
    'KAPI AKSESUARLARI',
    'SÜRGÜ SİSTEMLERİ',
    'GARDOLAP AKSESUARLARI',
    'ESKİTME ÜRÜNLERİ',
    'EV GEREÇLERİ'
];

try {
    $db = getDBConnection();
    echo "=== seeding Categories ===\n";
    
    foreach ($categories as $name) {
        $slug = slugify($name);
        
        // Check if category name or slug already exists
        $stmt = $db->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
        $stmt->execute([$name, $slug]);
        
        if ($stmt->fetch()) {
            echo "SKIPPED: '{$name}' (already exists)\n";
        } else {
            $insert = $db->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            $insert->execute([$name, $slug]);
            echo "INSERTED: '{$name}' (slug: {$slug})\n";
        }
    }
    
    echo "SUCCESS: Seeding completed.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
