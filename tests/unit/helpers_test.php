<?php
// tests/unit/helpers_test.php
require_once __DIR__ . '/../config/helpers.php';

function testSlugifyUnit() {
    echo "Running testSlugifyUnit...\n";
    assertEquals(
        slugify('Ürün Örneği ŞıkĞı'),
        'urun-ornegi-sikgi',
        'slugify – Turkish letters & spaces to lower‑dash'
    );
    assertEquals(
        slugify('Blum Menteşe #123 + Kılavuz'),
        'blum-mentese-sharp123-plus-kilavuz',
        'slugify – special characters'
    );
    assertEquals(
        slugify('  ray---grubu   '),
        'ray-grubu',
        'slugify – trim & collapse dashes'
    );
}

function testYoutubeParserUnit() {
    echo "Running testYoutubeParserUnit...\n";
    assertEquals(
        get_youtube_video_id('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        'dQw4w9WgXcQ',
        'youtube‑parser – standard watch URL'
    );
    assertEquals(
        get_youtube_video_id('https://youtu.be/dQw4w9WgXcQ'),
        'dQw4w9WgXcQ',
        'youtube‑parser – short URL'
    );
    assertEquals(
        get_youtube_video_id('https://google.com'),
        null,
        'youtube‑parser – non‑YouTube host returns null'
    );
}

// Execute unit tests when file is included
testSlugifyUnit();
testYoutubeParserUnit();
?>
