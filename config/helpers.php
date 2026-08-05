<?php
/**
 * General utility and helper functions for İncaksesuar.
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'helpers.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

require_once __DIR__ . '/version.php';

/**
 * Generates an SEO-friendly URL slug from string (Turkish language support included).
 * 
 * @param string $text
 * @return string
 */
if (!function_exists('slugify')) {
    function slugify(string $text): string {
        $find =    ['Ç', 'Ş', 'Ğ', 'Ü', 'İ', 'Ö', 'ç', 'ş', 'ğ', 'ü', 'ö', 'ı', '+', '#', ' '];
        $replace = ['c', 's', 'g', 'u', 'i', 'o', 'c', 's', 'g', 'u', 'o', 'i', 'plus', 'sharp', '-'];
        
        $text = str_replace($find, $replace, $text);
        $text = preg_replace('/[^a-zA-Z0-9\-]/', '', $text); // Remove remaining special characters
        $text = preg_replace('/-+/', '-', $text);           // Replace repeating dashes
        $text = trim($text, '-');                           // Trim border dashes
        return strtolower($text);
    }
}

/**
 * Extracts the 11-character YouTube video ID from various YouTube URL formats.
 * Supports standard watch links, mobile short links, embed links, and nocookie domains.
 * 
 * @param string $url
 * @return string|null The 11-char video ID, or null if not parsed.
 */
if (!function_exists('get_youtube_video_id')) {
    function get_youtube_video_id(string $url): ?string {
        $pattern = '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i';
        if (preg_match($pattern, $url, $match)) {
            return $match[1];
        }
        return null;
    }
}
