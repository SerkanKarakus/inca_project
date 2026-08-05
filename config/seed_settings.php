<?php
/**
 * Site Settings Seeder.
 * Creates and populates default settings table.
 */

if (PHP_SAPI !== 'cli') {
    header("HTTP/1.1 403 Forbidden");
    exit("This script can only be run from the command line.");
}

require_once __DIR__ . '/db.php';

try {
    $db = getDBConnection();
    echo "=== Seeding Site Settings ===\n";

    // Create table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS `site_settings` (
          `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
          `setting_value` TEXT NULL,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $defaultSettings = [
        'homepage_title' => 'İncaksesuar | Hırdavat ve Mobilya Aksesuarları',
        'homepage_description' => 'Blum, Hafele, Samet, Starax ve Fors gibi sektörün öncü markalarının menteşe, ray ve mobilya aksesuar gruplarını uygun fiyatlarla sunuyoruz.',
        'homepage_keywords' => 'incaksesuar, inca hırdavat, mobilya aksesuarları, blum menteşe, hafele, samet, starax, fors, çekmece rayı, ikitelli keresteciler sitesi, başakşehir',
        'homepage_slider_title' => 'Kaliteli Hırdavat & Mobilya Malzemeleri',
        'homepage_slider_desc' => 'İncaksesuar olarak, en seçkin markaların ürünlerini en uygun fiyatlarla mağazamızda sizlerle buluşturuyoruz. En iyi fiyat tekliflerimiz için dükkanımıza davetlisiniz.',
        'company_phone' => '0555 066 33 27',
        'company_email' => 'info@incaksesuar.com',
        'company_address' => 'İkitelli Keresteciler Sitesi 21. Blok No:15, Başakşehir / İstanbul',
        'facebook_url' => 'https://facebook.com/incaksesuar',
        'instagram_url' => 'https://instagram.com/incaksesuar'
    ];

    foreach ($defaultSettings as $key => $val) {
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $val, $val]);
        echo "SET: '{$key}' => '{$val}'\n";
    }

    echo "SUCCESS: Settings seeding completed.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
