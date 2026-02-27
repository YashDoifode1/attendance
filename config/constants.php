<?php
// config/constants.php
// Enterprise configuration for location-based attendance system

// ============================================
// INSTITUTION CONFIGURATION
// ============================================
// define('INSTITUTION_ID', 'YOUR_INSTITUTION_ID');
// define('SECRET_KEY', 'your-secret-key-here-change-in-production');
// define('INSTITUTION_NAME', 'Your Institution Name');

// // ============================================
// // SESSION & QR CONFIGURATION
// // ============================================
// define('QR_EXPIRY_MINUTES', 15);
define('QR_MAX_USES', 1); // 1 = single use per student
define('SESSION_TOKEN_LENGTH', 32);
define('QR_ENCRYPTION_ALGO', 'AES-256-CBC');

// ============================================
// GPS ACCURACY CONFIGURATION - More realistic for classrooms
// ============================================
// Accuracy thresholds (in meters)
define('GPS_ACCURACY_EXCELLENT', 15);  // <=15m: Perfect for verification
define('GPS_ACCURACY_GOOD', 30);       // 16-30m: Acceptable with confidence
define('GPS_ACCURACY_FAIR', 50);       // 31-50m: Accept but log warning
define('GPS_ACCURACY_POOR', 100);      // 51-100m: Use only with additional checks
define('GPS_ACCURACY_UNUSABLE', 100);  // >100m: Reject

// Required accuracy based on session type
define('GPS_REQUIRED_ACCURACY_LECTURE', 50);  // Lecture halls can be large
define('GPS_REQUIRED_ACCURACY_LAB', 30);       // Labs are smaller spaces
define('GPS_REQUIRED_ACCURACY_EXAM', 20);      // Exams need stricter verification

// GPS timeout settings (milliseconds)
define('GPS_TIMEOUT_MS', 15000);        // 15 seconds max wait
define('GPS_MAX_AGE_MS', 30000);         // 30 seconds max age
define('GPS_RETRY_COUNT', 3);            // Number of retry attempts
define('GPS_RETRY_DELAY_MS', 2000);       // 2 seconds between retries

// ============================================
// LOCATION RADIUS CONFIGURATION
// ============================================
define('DEFAULT_RADIUS_LECTURE', 30);
define('DEFAULT_RADIUS_LAB', 40);
define('DEFAULT_RADIUS_EXAM', 60);
define('MAX_LOCATION_ACCURACY', 50); // Maximum allowed GPS accuracy in meters
define('MIN_LOCATION_ACCURACY', 10); // Minimum required GPS accuracy

// Proximity override (if student is very close, override poor GPS)
define('PROXIMITY_OVERRIDE_DISTANCE', 10); // meters
define('PROXIMITY_OVERRIDE_MAX_ACCURACY', 100); // meters

// ============================================
// RATE LIMITING
// ============================================
define('RATE_LIMIT_ATTENDANCE', 5); // Max attempts per minute
define('RATE_LIMIT_QR_GENERATE', 3); // Max QR generations per hour
define('RATE_LIMIT_WINDOW_MINUTES', 1);

// ============================================
// SECURITY CONFIGURATION
// ============================================
define('ALLOWED_CLOCK_SKEW', 300); // 5 minutes in seconds
define('MAX_REPLAY_WINDOW', 300); // 5 minutes
define('BCRYPT_COST', 12);
define('SESSION_TIMEOUT_MINUTES', 120);

// ============================================
// LOCATION VERIFICATION MODES
// ============================================
define('LOCATION_VERIFICATION_STRICT', 'strict');     // High accuracy required
define('LOCATION_VERIFICATION_NORMAL', 'normal');     // Balance
define('LOCATION_VERIFICATION_RELAXED', 'relaxed');   // More tolerant

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('Asia/Kolkata');

// ============================================
// ERROR REPORTING (Set to 0 in production)
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);