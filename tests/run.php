<?php
/**
 * Lightweight, Zero-Dependency Test Suite for İncaksesuar.
 * Runs locally to verify helpers and database connection.
 */

// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Color helpers for CLI output
define('CLI_GREEN', "\e[32m");
define('CLI_RED', "\e[31m");
define('CLI_RESET', "\e[0m");

// Load the app configurations and helpers
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$failedTestsCount = 0;

/**
 * Asserts that two values are equal.
 * 
 * @param mixed $actual
 * @param mixed $expected
 * @param string $testName
 */
function assertEquals($actual, $expected, string $testName) {
    global $failedTestsCount;
    if ($actual === $expected) {
        echo CLI_GREEN . "  [PASS] " . CLI_RESET . $testName . "\n";
    } else {
        echo CLI_RED . "  [FAIL] " . CLI_RESET . $testName . "\n";
        echo "         Expected: " . var_export($expected, true) . "\n";
        echo "         Actual:   " . var_export($actual, true) . "\n";
        $failedTestsCount++;
    }
}

/**
 * Test case for the Turkish slugify helper.
 */
function testSlugify() {
    echo "Running testSlugify...\n";
    assertEquals(slugify('Ürün Örneği ŞıkĞı'), 'urun-ornegi-sikgi', 'Convert Turkish letters and spaces to lower-dash');
    assertEquals(slugify('Blum Menteşe #123 + Kılavuz'), 'blum-mentese-sharp123-plus-kilavuz', 'Handle special characters like # and +');
    assertEquals(slugify('  ray---grubu   '), 'ray-grubu', 'Trim border and remove repeating dashes');
}

/**
 * Test case for the YouTube Video ID extraction helper.
 */
function testYoutubeParser() {
    echo "Running testYoutubeParser...\n";
    assertEquals(get_youtube_video_id('https://www.youtube.com/watch?v=dQw4w9WgXcQ'), 'dQw4w9WgXcQ', 'Parse standard desktop watch link');
    assertEquals(get_youtube_video_id('https://youtu.be/dQw4w9WgXcQ'), 'dQw4w9WgXcQ', 'Parse mobile short link');
    assertEquals(get_youtube_video_id('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'), 'dQw4w9WgXcQ', 'Parse privacy-enhanced nocookie embed link');
    assertEquals(get_youtube_video_id('https://google.com'), null, 'Return null for non-YouTube domains');
}

/**
 * Test case to verify local database connection.
 */
function testDatabaseConnection() {
    global $failedTestsCount;
    echo "Running testDatabaseConnection...\n";
    try {
        $db = getDBConnection();
        if ($db instanceof PDO) {
            echo CLI_GREEN . "  [PASS] " . CLI_RESET . "Database connection successful\n";
        } else {
            echo CLI_RED . "  [FAIL] " . CLI_RESET . "Connection succeeded but did not return a PDO object\n";
            $failedTestsCount++;
        }
    } catch (Exception $e) {
        echo CLI_RED . "  [FAIL] " . CLI_RESET . "Database connection failed with error: " . $e->getMessage() . "\n";
        $failedTestsCount++;
    }
}

/**
 * Test case for HTTP Page Integration.
 * Asserts that all public and admin pages load successfully (HTTP 200) and contain expected HTML nodes.
 */
function testPageIntegration() {
    global $failedTestsCount;
    echo "Running testPageIntegration (connecting to local XAMPP server)...\n";
    
    // Check if server is running on localhost:8000
    $connection = @fsockopen('localhost', 8000, $errno, $errstr, 1);
    if (!$connection) {
        echo CLI_GREEN . "  [SKIP] " . CLI_RESET . "Local server (http://localhost:8000) is not running.\n";
        echo "         To run page integration tests, please run: php -S localhost:8000 router.php\n";
        return;
    }
    fclose($connection);
    
    $pages = [
        ['url' => '/', 'status' => 200, 'content' => 'İncaksesuar'],
        ['url' => '/products', 'status' => 200, 'content' => 'Filtrele'],
        ['url' => '/category/blum-urunleri', 'status' => 200, 'content' => 'BLUM'],
        ['url' => '/contact', 'status' => 200, 'content' => 'Mesaj'],
        ['url' => '/admin/login', 'status' => 200, 'content' => 'Giriş'],
        ['url' => '/admin', 'status' => 302, 'content' => ''], // Redirects to login
        ['url' => '/admin/media', 'status' => 302, 'content' => ''] // Redirects to login
    ];
    
    foreach ($pages as $page) {
        $url = $page['url'];
        $expectedStatus = $page['status'];
        $expectedContent = $page['content'];
        
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 2,
                'follow_location' => 0,
                'max_redirects' => 0,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) IntegrationTestHarness/1.0\r\n"
            ]
        ]);
        
        $fullUrl = 'http://localhost:8000' . $url;
        $content = @file_get_contents($fullUrl, false, $context);
        
        if ($content === false) {
            echo CLI_RED . "  [FAIL] " . CLI_RESET . "GET $url: Could not connect to local server\n";
            $failedTestsCount++;
            continue;
        }
        
        $statusCode = 500;
        if (isset($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches)) {
                $statusCode = (int)$matches[1];
            }
        }
        
        $statusPass = ($statusCode === $expectedStatus);
        $contentPass = true;
        if (!empty($expectedContent) && strpos($content, $expectedContent) === false) {
            $contentPass = false;
        }
        
        if ($statusPass && $contentPass) {
            echo CLI_GREEN . "  [PASS] " . CLI_RESET . "GET $url (HTML contains: '$expectedContent')\n";
        } else {
            echo CLI_RED . "  [FAIL] " . CLI_RESET . "GET $url\n";
            if (!$statusPass) {
                echo "         Expected Status Code: $expectedStatus, Got: $statusCode\n";
            }
            if (!$contentPass) {
                echo "         Expected HTML content missing: '$expectedContent'\n";
            }
            $failedTestsCount++;
        }
    }
}

// Start Runner
echo "\n=========================================\n";
echo "       RUNNING İNCAKSESUAR TESTS        \n";
echo "=========================================\n";

// Auto-load unit tests and run all test* functions
function loadTestFiles(string $directory) {
    foreach (glob($directory . '/*.php') as $file) {
        require_once $file;
    }
}
loadTestFiles(__DIR__ . '/unit');

// Dynamically invoke all test* functions
$testFunctions = array_filter(get_defined_functions()['user'], function($fn) {
    return strpos($fn, 'test') === 0;
});
foreach ($testFunctions as $fn) {
    $fn();
}

echo "\n=========================================\n";
if ($failedTestsCount === 0) {
    echo CLI_GREEN . "🎉 SUCCESS: All tests passed successfully!" . CLI_RESET . "\n";
    exit(0);
} else {
    echo CLI_RED . "❌ FAILURE: {$failedTestsCount} test(s) failed!" . CLI_RESET . "\n";
    exit(1);
}
echo "=========================================\n\n";
