<?php
/**
 * Detailed Categories Seeder.
 * Seeds specific subcategories from the provided images, avoiding slug duplication.
 */

if (PHP_SAPI !== 'cli') {
    header("HTTP/1.1 403 Forbidden");
    exit("This script can only be run from the command line.");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$subCategories = [
    // BLUM ÜRÜNLERİ
    'Blum Menteşe Grubu',
    'Blum Çekmece Rayları',
    'Blum Kalkar Kapak Sistemleri',
    'Blum Revego',
    'Blum Servo Drive',
    'Blum Tezgah Üstü Aksesuar',
    'Blum Tamamlayıcı Ürünler',

    // MOBİLYA AKSESUARLARI
    'Mobilya Menteşe Grubu',
    'Mobilya Çekmece Rayları',
    'Mobilya Kulp Çeşitleri',
    'Mobilya PVC Bant ve Tutkal',
    'Mobilya Askı Çeşitleri',
    'Mobilya Bağlantı Elemanları',
    'Mobilya Ayak Çeşitleri',
    'Mobilya Sunta Vidaları',
    'Mobilya Yapıştırıcı Ürünler',
    'Mobilya Teker Çeşitleri',
    'Mobilya Epoksi Reçine',
    'Mobilya Kilit Çeşitleri',
    'Mobilya Modüler Sistemler',
    'Mobilya Tamamlayıcı Ürünler',

    // HIRDAVAT MALZEMELERİ
    'Hırdavat Elektrikli Grubu',
    'Hırdavat Akülü Grubu',
    'Hırdavat Havalı Grubu',
    'Hırdavat Kesici Takımlar',
    'Hırdavat El Takımları',
    'Hırdavat Ambalaj Malzemeleri',
    'Hırdavat Takım Çantaları',
    'Hırdavat Zımpara Takımları',
    'Hırdavat Boya Aksesuarları',
    'Hırdavat İş Güvenliği',
    'Hırdavat Aydınlatma Grubu',

    // MUTFAK AKSESUARLARI
    'Mutfak Ankastre Cihazları',
    'Mutfak Kalkar Kapak Sistemleri',
    'Mutfak Çekmece İçi Aksesuar',
    'Mutfak Kiler Sistemleri',
    'Mutfak Çöp Kovaları',
    'Mutfak Tezgah Altı Baza Çeşitleri',
    'Mutfak Eviye ve Aksesuarları',

    // KAPI AKSESUARLARI
    'Kapı Akıllı Kapı Kilitleri',
    'Kapı Kapı Kolları',
    'Kapı Kapı Kilitleri',
    'Kapı Kapı Menteşeleri',
    'Kapı Kapı Fitilleri',
    'Kapı Kapı Tamponları',
    'Kapı Çekme Kollar',
    'Diğer Kapı Malzemeleri'
];

try {
    $db = getDBConnection();
    echo "=== Seeding Subcategories ===\n";
    
    foreach ($subCategories as $name) {
        $slug = slugify($name);
        
        // Check duplication
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
