<?php
/**
 * Sitemap Generator for Attendance Management System
 * Author: Your Project
 */

define('APP_URL', 'https://qrbasedattendance.gt.tc'); // 🔴 CHANGE THIS
$baseDir = __DIR__;

/**
 * Pages to include manually (important routes)
 */
$staticPages = [
    '/',
    '/index.php',
    '/auth/login.php',
    '/auth/register.php'
];

/**
 * Directories to scan automatically
 */
$scanDirs = [
    '/admin',
    '/faculty',
    '/student',
    '/profile'
];

$urls = [];

/**
 * Add static pages
 */
foreach ($staticPages as $page) {
    $urls[] = [
        'loc' => APP_URL . $page,
        'priority' => '1.0',
        'changefreq' => 'daily'
    ];
}

/**
 * Scan directories for PHP files
 */
foreach ($scanDirs as $dir) {
    $fullPath = $baseDir . $dir;
    if (!is_dir($fullPath)) continue;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPath)
    );

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {

            // Skip config, ajax, includes
            if (preg_match('/(config|include|ajax|header|footer)/i', $file->getFilename())) {
                continue;
            }

            $relativePath = str_replace($baseDir, '', $file->getPathname());

            $urls[] = [
                'loc' => APP_URL . str_replace('\\', '/', $relativePath),
                'priority' => '0.8',
                'changefreq' => 'weekly'
            ];
        }
    }
}

/**
 * Create sitemap XML
 */
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$urlset = $xml->createElement('urlset');
$urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

foreach ($urls as $url) {
    $urlTag = $xml->createElement('url');

    $loc = $xml->createElement('loc', htmlspecialchars($url['loc']));
    $lastmod = $xml->createElement('lastmod', date('Y-m-d'));
    $changefreq = $xml->createElement('changefreq', $url['changefreq']);
    $priority = $xml->createElement('priority', $url['priority']);

    $urlTag->appendChild($loc);
    $urlTag->appendChild($lastmod);
    $urlTag->appendChild($changefreq);
    $urlTag->appendChild($priority);

    $urlset->appendChild($urlTag);
}

$xml->appendChild($urlset);

/**
 * Save sitemap
 */
$xml->save($baseDir . '/sitemap.xml');

echo "✅ sitemap.xml generated successfully!";
