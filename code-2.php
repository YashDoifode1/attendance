<?php
/**
 * Robots.txt Generator
 * Attendance Management System
 */

define('APP_URL', 'https://yourdomain.com'); // 🔴 CHANGE THIS

$robots = <<<TXT
User-agent: *
Allow: /

# Block sensitive areas
Disallow: /admin/
Disallow: /faculty/
Disallow: /student/
Disallow: /profile/
Disallow: /auth/
Disallow: /includes/
Disallow: /config/
Disallow: /uploads/private/

# Allow public assets
Allow: /assets/
Allow: /uploads/public/

# Sitemap
Sitemap: {APP_URL}/sitemap.xml
TXT;

$robots = str_replace('{APP_URL}', APP_URL, $robots);

file_put_contents(__DIR__ . '/robots.txt', trim($robots));

echo "✅ robots.txt generated successfully!";
