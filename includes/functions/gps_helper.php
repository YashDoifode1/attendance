<?php
/**
 * GPS Helper Functions
 * Provides utilities for GPS accuracy handling and decision making
 */

/**
 * Determine if GPS accuracy is acceptable for a given context
 * 
 * @param float $accuracy GPS accuracy in meters
 * @param string $session_type Lecture/Lab/Exam
 * @param float $distance Distance from faculty in meters
 * @return array Decision with reason and confidence
 */
function isAccuracyAcceptable($accuracy, $session_type = 'Lecture', $distance = null) {
    // Get required accuracy based on session type
    $required = match($session_type) {
        'Exam' => defined('GPS_REQUIRED_ACCURACY_EXAM') ? GPS_REQUIRED_ACCURACY_EXAM : 20,
        'Lab' => defined('GPS_REQUIRED_ACCURACY_LAB') ? GPS_REQUIRED_ACCURACY_LAB : 30,
        default => defined('GPS_REQUIRED_ACCURACY_LECTURE') ? GPS_REQUIRED_ACCURACY_LECTURE : 50
    };
    
    // Decision matrix
    if ($accuracy <= (defined('GPS_ACCURACY_EXCELLENT') ? GPS_ACCURACY_EXCELLENT : 15)) {
        return [
            'acceptable' => true,
            'reason' => 'Excellent GPS accuracy',
            'confidence' => 'HIGH',
            'score' => 100
        ];
    }
    
    if ($accuracy <= (defined('GPS_ACCURACY_GOOD') ? GPS_ACCURACY_GOOD : 30)) {
        return [
            'acceptable' => true,
            'reason' => 'Good GPS accuracy',
            'confidence' => 'HIGH',
            'score' => 90
        ];
    }
    
    if ($accuracy <= (defined('GPS_ACCURACY_FAIR') ? GPS_ACCURACY_FAIR : 50)) {
        // Fair accuracy - acceptable for most cases
        return [
            'acceptable' => true,
            'reason' => 'Fair GPS accuracy - acceptable',
            'confidence' => 'MEDIUM',
            'score' => 75
        ];
    }
    
    if ($accuracy <= (defined('GPS_ACCURACY_POOR') ? GPS_ACCURACY_POOR : 100)) {
        // Poor accuracy - check context
        if ($session_type === 'Exam') {
            return [
                'acceptable' => false,
                'reason' => "Poor GPS accuracy ({$accuracy}m) not acceptable for exam",
                'confidence' => 'LOW',
                'score' => 40
            ];
        }
        
        // For lectures/labs, check if student is very close
        if ($distance !== null && $distance < 15) {
            return [
                'acceptable' => true,
                'reason' => "Student within 15m despite poor GPS ({$accuracy}m)",
                'confidence' => 'MEDIUM',
                'score' => 60
            ];
        }
        
        return [
            'acceptable' => true,
            'reason' => "Poor GPS accuracy ({$accuracy}m) but accepted for {$session_type}",
            'confidence' => 'LOW',
            'score' => 50
        ];
    }
    
    // Unusable accuracy
    return [
        'acceptable' => false,
        'reason' => "GPS accuracy too low ({$accuracy}m)",
        'confidence' => 'LOW',
        'score' => 0
    ];
}

/**
 * Get GPS troubleshooting tips based on error type
 * 
 * @param string $error_type Type of GPS error
 * @return array Tips for user
 */
function getGpsTips($error_type = 'low_accuracy') {
    $tips = [
        'low_accuracy' => [
            'Move closer to a window',
            'Step outside briefly for initial lock',
            'Enable WiFi scanning (helps GPS accuracy)',
            'Restart your phone\'s location services',
            'Avoid buildings with metal roofs',
            'Hold your phone away from your body'
        ],
        'no_signal' => [
            'Check if location is enabled in settings',
            'Restart your device',
            'Go to an open area away from buildings',
            'Disable power saving mode',
            'Toggle Airplane mode on/off',
            'Update your device\'s AGPS data'
        ],
        'timeout' => [
            'Try again in a few seconds',
            'Move to a different spot',
            'Clear any magnetic interference',
            'Check for GPS blocking materials',
            'Ensure you\'re not in a basement or underground'
        ],
        'permission_denied' => [
            'Click the location icon in browser address bar',
            'Allow location access for this site',
            'Check system location settings',
            'Refresh the page after enabling',
            'Clear browser cache and try again'
        ],
        'gps_disabled' => [
            'Enable Location/GPS in your phone settings',
            'Turn on High Accuracy mode if available',
            'Restart your phone after enabling GPS',
            'Check if any battery saver is blocking GPS'
        ]
    ];
    
    return $tips[$error_type] ?? $tips['low_accuracy'];
}

/**
 * Calculate confidence score for location verification
 * 
 * @param float $distance Distance from faculty
 * @param float $allowed_radius Allowed radius
 * @param float $accuracy GPS accuracy
 * @param string $session_type Session type
 * @return array Confidence score and level
 */
function calculateLocationConfidence($distance, $allowed_radius, $accuracy, $session_type) {
    $score = 100;
    
    // Deduct for distance (closer is better)
    $distance_ratio = $distance / $allowed_radius;
    if ($distance_ratio > 0.9) {
        $score -= 30;
    } elseif ($distance_ratio > 0.75) {
        $score -= 20;
    } elseif ($distance_ratio > 0.5) {
        $score -= 10;
    }
    
    // Deduct for poor accuracy
    if ($accuracy > (defined('GPS_ACCURACY_FAIR') ? GPS_ACCURACY_FAIR : 50)) {
        $score -= 20;
    } elseif ($accuracy > (defined('GPS_ACCURACY_GOOD') ? GPS_ACCURACY_GOOD : 30)) {
        $score -= 10;
    }
    
    // Bonus for excellent accuracy
    if ($accuracy <= (defined('GPS_ACCURACY_EXCELLENT') ? GPS_ACCURACY_EXCELLENT : 15)) {
        $score += 10;
    }
    
    // Session type multiplier
    $multiplier = match($session_type) {
        'Exam' => 1.2,  // Exams need higher confidence
        'Lab' => 1.0,
        default => 0.9
    };
    
    $final_score = min(100, max(0, $score * $multiplier));
    
    $level = 'HIGH';
    if ($final_score < 60) {
        $level = 'LOW';
    } elseif ($final_score < 80) {
        $level = 'MEDIUM';
    }
    
    return [
        'score' => round($final_score),
        'level' => $level,
        'factors' => [
            'distance_ratio' => round($distance_ratio * 100) . '%',
            'accuracy_quality' => getAccuracyQuality($accuracy),
            'session_type' => $session_type
        ]
    ];
}

/**
 * Get GPS status emoji based on accuracy
 * 
 * @param float $accuracy
 * @return string
 */
function getGpsStatusEmoji($accuracy) {
    if ($accuracy <= (defined('GPS_ACCURACY_EXCELLENT') ? GPS_ACCURACY_EXCELLENT : 15)) {
        return '🟢'; // Excellent - Green
    } elseif ($accuracy <= (defined('GPS_ACCURACY_GOOD') ? GPS_ACCURACY_GOOD : 30)) {
        return '🔵'; // Good - Blue
    } elseif ($accuracy <= (defined('GPS_ACCURACY_FAIR') ? GPS_ACCURACY_FAIR : 50)) {
        return '🟡'; // Fair - Yellow
    } elseif ($accuracy <= (defined('GPS_ACCURACY_POOR') ? GPS_ACCURACY_POOR : 100)) {
        return '🟠'; // Poor - Orange
    } else {
        return '🔴'; // Unusable - Red
    }
}

/**
 * Format GPS accuracy for display
 * 
 * @param float $accuracy
 * @return string
 */
function formatGpsAccuracy($accuracy) {
    if ($accuracy < 1) {
        return '<1m';
    } elseif ($accuracy < 10) {
        return round($accuracy, 1) . 'm';
    } else {
        return round($accuracy) . 'm';
    }
}

/**
 * Get accuracy quality label
 * 
 * @param float $accuracy
 * @return string
 */
function getAccuracyQuality($accuracy) {
    if ($accuracy <= (defined('GPS_ACCURACY_EXCELLENT') ? GPS_ACCURACY_EXCELLENT : 15)) {
        return 'Excellent';
    } elseif ($accuracy <= (defined('GPS_ACCURACY_GOOD') ? GPS_ACCURACY_GOOD : 30)) {
        return 'Good';
    } elseif ($accuracy <= (defined('GPS_ACCURACY_FAIR') ? GPS_ACCURACY_FAIR : 50)) {
        return 'Fair';
    } elseif ($accuracy <= (defined('GPS_ACCURACY_POOR') ? GPS_ACCURACY_POOR : 100)) {
        return 'Poor';
    } else {
        return 'Unusable';
    }
}

/**
 * Estimate number of satellites based on accuracy
 * 
 * @param float $accuracy
 * @return int
 */
function estimateSatelliteCount($accuracy) {
    if ($accuracy <= 5) return '12+';
    if ($accuracy <= 10) return '8-12';
    if ($accuracy <= 20) return '6-8';
    if ($accuracy <= 30) return '4-6';
    if ($accuracy <= 50) return '2-4';
    return '<2';
}

/**
 * Get GPS fix type
 * 
 * @param float $accuracy
 * @param bool $hasAltitude
 * @return string
 */
function getGpsFixType($accuracy, $hasAltitude = false) {
    if ($accuracy <= 3) return '3D Fix + DGPS';
    if ($accuracy <= 10) return '3D Fix';
    if ($accuracy <= 30) return '2D Fix';
    if ($accuracy <= 100) return 'Approximate';
    return 'No Fix';
}

/**
 * Check if location is within classroom bounds with buffer
 * 
 * @param float $lat
 * @param float $lng
 * @param array $classroom_bounds
 * @param float $buffer_meters
 * @return bool
 */
function isWithinClassroomBounds($lat, $lng, $classroom_bounds, $buffer_meters = 10) {
    if (empty($classroom_bounds)) {
        return true; // No bounds defined
    }
    
    // Convert buffer from meters to degrees (approximate)
    $buffer_degrees = $buffer_meters / 111000; // 1 degree ≈ 111km
    
    $min_lat = min(array_column($classroom_bounds, 'lat')) - $buffer_degrees;
    $max_lat = max(array_column($classroom_bounds, 'lat')) + $buffer_degrees;
    $min_lng = min(array_column($classroom_bounds, 'lng')) - $buffer_degrees;
    $max_lng = max(array_column($classroom_bounds, 'lng')) + $buffer_degrees;
    
    return ($lat >= $min_lat && $lat <= $max_lat && 
            $lng >= $min_lng && $lng <= $max_lng);
}
?>