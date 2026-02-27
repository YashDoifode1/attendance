<?php
/**
 * Security Functions
 * Rate limiting, token validation, anti-spoofing
 */

/**
 * Check rate limit for an action
 * 
 * @param PDO $pdo Database connection
 * @param string $identifier IP or user ID
 * @param string $action Action type
 * @param int $limit Max attempts
 * @param int $window Minutes window
 * @return bool True if allowed
 */
function checkRateLimit($pdo, $identifier, $action, $limit, $window = 1) {
    try {
        // Create table if not exists (for first run)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                identifier VARCHAR(255) NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expiry DATETIME NOT NULL,
                INDEX idx_identifier (identifier, action_type),
                INDEX idx_expiry (expiry)
            )
        ");
        
        // Clean old entries
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE expiry < NOW()");
        $stmt->execute();
        
        // Check recent attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM rate_limits 
            WHERE identifier = ? AND action_type = ? AND timestamp > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$identifier, $action, $window]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['attempts'] >= $limit) {
            return false;
        }
        
        // Log this attempt
        $stmt = $pdo->prepare("
            INSERT INTO rate_limits (identifier, action_type, expiry) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
        ");
        $stmt->execute([$identifier, $action, $window]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return true; // Fail open in case of DB error
    }
}

/**
 * Validate QR token with replay protection
 * 
 * @param PDO $pdo Database connection
 * @param string $token Session token
 * @param int $session_id Session ID
 * @return array|false Session data or false
 */
function validateQRToken($pdo, $token, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM attendance_sessions 
            WHERE id = ? AND token = ? AND expiry_timestamp > NOW()
        ");
        $stmt->execute([$session_id, $token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return false;
        }
        
        // Check if session has location data
        if (is_null($session['faculty_lat']) || is_null($session['faculty_lng'])) {
            return false; // Location not captured
        }
        
        return $session;
    } catch (PDOException $e) {
        error_log("Token validation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced GPS spoofing detection
 * 
 * @param float $lat Latitude
 * @param float $lng Longitude
 * @param float $accuracy GPS accuracy
 * @param string $ip_address Student IP
 * @param float $altitude Optional altitude
 * @return array Detection result
 */
function detectGPSSpoofing($lat, $lng, $accuracy, $ip_address, $altitude = null) {
    $suspicious = false;
    $reasons = [];
    $score = 1.0; // Confidence score (1.0 = legitimate, 0.0 = definitely spoofed)
    
    // Check 1: Impossible or perfect accuracy (common in emulators)
    if ($accuracy < 0.1) {
        $suspicious = true;
        $reasons[] = 'Impossibly precise GPS (<0.1m)';
        $score -= 0.4;
    } elseif ($accuracy < 1) {
        $reasons[] = 'Suspiciously precise GPS';
        $score -= 0.2;
    }
    
    // Check 2: Zero coordinates (Null Island - common in emulators)
    if (round($lat, 2) == 0 && round($lng, 2) == 0) {
        $suspicious = true;
        $reasons[] = 'Null Island coordinates (0,0)';
        $score -= 0.5;
    }
    
    // Check 3: Check if coordinates are in the ocean (simplified)
    $ocean_areas = [
        ['min_lat' => -90, 'max_lat' => 90, 'min_lng' => -180, 'max_lng' => -130], // Pacific
        ['min_lat' => -90, 'max_lat' => 90, 'min_lng' => 130, 'max_lng' => 180], // Pacific
        ['min_lat' => -60, 'max_lat' => -30, 'min_lng' => -70, 'max_lng' => -40], // South Atlantic
        ['min_lat' => -40, 'max_lat' => -20, 'min_lng' => 20, 'max_lng' => 40], // South Indian
        ['min_lat' => 30, 'max_lat' => 45, 'min_lng' => -60, 'max_lng' => -40], // North Atlantic
    ];
    
    foreach ($ocean_areas as $ocean) {
        if ($lat >= $ocean['min_lat'] && $lat <= $ocean['max_lat'] &&
            $lng >= $ocean['min_lng'] && $lng <= $ocean['max_lng']) {
            $suspicious = true;
            $reasons[] = 'Location in ocean';
            $score -= 0.4;
            break;
        }
    }
    
    // Check 4: Check if coordinates are in known deserts (unlikely for classroom)
    $desert_areas = [
        ['min_lat' => 20, 'max_lat' => 30, 'min_lng' => 20, 'max_lng' => 30], // Sahara
        ['min_lat' => -30, 'max_lat' => -20, 'min_lng' => 120, 'max_lng' => 130], // Australian desert
    ];
    
    foreach ($desert_areas as $desert) {
        if ($lat >= $desert['min_lat'] && $lat <= $desert['max_lat'] &&
            $lng >= $desert['min_lng'] && $lng <= $desert['max_lng']) {
            $reasons[] = 'Unlikely classroom location';
            $score -= 0.2;
            break;
        }
    }
    
    // Check 5: Altitude anomalies (if provided)
    if ($altitude !== null) {
        if ($altitude > 10000 || $altitude < -500) {
            $suspicious = true;
            $reasons[] = 'Impossible altitude';
            $score -= 0.3;
        } elseif ($altitude > 3000) {
            $reasons[] = 'Unusually high altitude';
            $score -= 0.1;
        }
    }
    
    // Check 6: Check if coordinates are within reasonable range for institution
    // This would need institution location configured
    if (defined('INSTITUTION_LAT') && defined('INSTITUTION_LNG')) {
        $distance_from_institution = haversineDistance($lat, $lng, INSTITUTION_LAT, INSTITUTION_LNG);
        if ($distance_from_institution > 50000) { // >50km from institution
            $reasons[] = 'Far from institution location';
            $score -= 0.2;
        }
    }
    
    // Check 7: Suspicious accuracy patterns
    if ($accuracy < 5 && $accuracy > 0 && mt_rand(1, 100) > 95) { // Random sampling
        $reasons[] = 'Consistently high precision';
        $score -= 0.1;
    }
    
    return [
        'suspicious' => $suspicious,
        'reasons' => $reasons,
        'score' => max(0, $score),
        'level' => $score > 0.7 ? 'LOW' : ($score > 0.4 ? 'MEDIUM' : 'HIGH')
    ];
}

/**
 * Generate secure token
 * 
 * @param int $length Token length
 * @return string
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Log location verification attempt
 * 
 * @param PDO $pdo Database connection
 * @param array $data Verification data
 * @return int Log ID
 */
function logLocationVerification($pdo, $data) {
    try {
        // Create table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS location_verification_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                attendance_id INT,
                student_id INT NOT NULL,
                session_id INT NOT NULL,
                faculty_lat DECIMAL(10,8),
                faculty_lng DECIMAL(11,8),
                student_lat DECIMAL(10,8),
                student_lng DECIMAL(11,8),
                calculated_distance DECIMAL(10,2),
                allowed_radius INT,
                verification_result ENUM('PASSED','FAILED','PASSED_WITH_WARNING'),
                failure_reason TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student (student_id),
                INDEX idx_created (created_at)
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO location_verification_log 
            (attendance_id, student_id, session_id, faculty_lat, faculty_lng, 
             student_lat, student_lng, calculated_distance, allowed_radius, 
             verification_result, failure_reason, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['attendance_id'] ?? null,
            $data['student_id'],
            $data['session_id'],
            $data['faculty_lat'],
            $data['faculty_lng'],
            $data['student_lat'],
            $data['student_lng'],
            $data['calculated_distance'],
            $data['allowed_radius'],
            $data['verification_result'],
            $data['failure_reason'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Failed to log verification: " . $e->getMessage());
        return 0;
    }
}

/**
 * Validate request signature
 * 
 * @param array $data Request data
 * @param string $signature Provided signature
 * @param string $secret Secret key
 * @return bool
 */
function validateRequestSignature($data, $signature, $secret) {
    ksort($data);
    $payload = http_build_query($data);
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}

/**
 * Check for duplicate attendance
 * 
 * @param PDO $pdo Database connection
 * @param int $student_id
 * @param int $session_id
 * @return bool
 */
function isDuplicateAttendance($pdo, $student_id, $session_id) {
    $stmt = $pdo->prepare("
        SELECT id FROM attendance 
        WHERE student_id = ? AND session_id = ?
    ");
    $stmt->execute([$student_id, $session_id]);
    return $stmt->fetch() !== false;
}

/**
 * Sanitize and validate input
 * 
 * @param mixed $input
 * @param string $type
 * @return mixed
 */
function sanitizeInput($input, $type = 'string') {
    if ($input === null) {
        return null;
    }
    
    switch ($type) {
        case 'lat':
            $filtered = filter_var($input, FILTER_VALIDATE_FLOAT);
            return ($filtered !== false && $filtered >= -90 && $filtered <= 90) ? $filtered : null;
            
        case 'lng':
            $filtered = filter_var($input, FILTER_VALIDATE_FLOAT);
            return ($filtered !== false && $filtered >= -180 && $filtered <= 180) ? $filtered : null;
            
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
            
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);
            
        case 'bool':
            return filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            
        default:
            return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Generate CSRF token
 * 
 * @return string
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token
 * @return bool
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get client IP address with proxy support
 * 
 * @return string
 */
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check if request is from mobile device
 * 
 * @return bool
 */
function isMobileDevice() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobile_agents = ['Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone'];
    
    foreach ($mobile_agents as $agent) {
        if (stripos($user_agent, $agent) !== false) {
            return true;
        }
    }
    
    return false;
}
?>