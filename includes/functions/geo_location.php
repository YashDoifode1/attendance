<?php
/**
 * GEO Location Functions
 * Enterprise-grade location validation using Haversine formula
 */

/**
 * Calculate distance between two coordinates using Haversine formula
 * 
 * @param float $lat1 Faculty latitude
 * @param float $lon1 Faculty longitude
 * @param float $lat2 Student latitude
 * @param float $lon2 Student longitude
 * @return float Distance in meters
 */
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    // Convert degrees to radians
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    // Haversine formula
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    
    $a = sin($dlat/2) * sin($dlat/2) + 
         cos($lat1) * cos($lat2) * 
         sin($dlon/2) * sin($dlon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    // Earth's radius in meters (6371000)
    return 6371000 * $c;
}

/**
 * Validate coordinate format and range
 * 
 * @param float $lat Latitude
 * @param float $lng Longitude
 * @return bool
 */
function validateCoordinates($lat, $lng) {
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return false;
    }
    
    // Latitude range: -90 to 90
    if ($lat < -90 || $lat > 90) {
        return false;
    }
    
    // Longitude range: -180 to 180
    if ($lng < -180 || $lng > 180) {
        return false;
    }
    
    return true;
}

/**
 * Calculate distance with error margin based on GPS accuracy
 * 
 * @param float $lat1
 * @param float $lon1
 * @param float $lat2
 * @param float $lon2
 * @param float $accuracy1 Faculty GPS accuracy
 * @param float $accuracy2 Student GPS accuracy
 * @return array Distance with confidence interval
 */
function calculateDistanceWithConfidence($lat1, $lon1, $lat2, $lon2, $accuracy1, $accuracy2) {
    $distance = haversineDistance($lat1, $lon1, $lat2, $lon2);
    
    // Calculate confidence interval based on GPS accuracies
    // Uses error propagation formula
    $margin = sqrt(pow($accuracy1, 2) + pow($accuracy2, 2));
    
    // Earth's curvature effect at larger distances (simplified)
    $curvature_effect = $distance * 0.000015; // ~15mm per km
    
    $total_margin = $margin + $curvature_effect;
    
    return [
        'distance' => round($distance, 2),
        'min_possible' => max(0, round($distance - $total_margin, 2)),
        'max_possible' => round($distance + $total_margin, 2),
        'margin_of_error' => round($total_margin, 2),
        'confidence' => $total_margin < 20 ? 'HIGH' : ($total_margin < 50 ? 'MEDIUM' : 'LOW')
    ];
}

/**
 * Calculate bearing between two points
 * 
 * @param float $lat1
 * @param float $lon1
 * @param float $lat2
 * @param float $lon2
 * @return float Bearing in degrees
 */
function calculateBearing($lat1, $lon1, $lat2, $lon2) {
    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);
    $lon1 = deg2rad($lon1);
    $lon2 = deg2rad($lon2);
    
    $y = sin($lon2 - $lon1) * cos($lat2);
    $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($lon2 - $lon1);
    
    $bearing = rad2deg(atan2($y, $x));
    
    return ($bearing + 360) % 360;
}

/**
 * Get cardinal direction from bearing
 * 
 * @param float $bearing
 * @return string
 */
function bearingToDirection($bearing) {
    $directions = ['North', 'Northeast', 'East', 'Southeast', 'South', 'Southwest', 'West', 'Northwest'];
    return $directions[round($bearing / 45) % 8];
}

/**
 * Calculate midpoint between two coordinates
 * 
 * @param float $lat1
 * @param float $lon1
 * @param float $lat2
 * @param float $lon2
 * @return array Midpoint coordinates
 */
function calculateMidpoint($lat1, $lon1, $lat2, $lon2) {
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $bx = cos($lat2) * cos($lon2 - $lon1);
    $by = cos($lat2) * sin($lon2 - $lon1);
    
    $lat3 = atan2(sin($lat1) + sin($lat2), 
                  sqrt((cos($lat1) + $bx) * (cos($lat1) + $bx) + $by * $by));
    $lon3 = $lon1 + atan2($by, cos($lat1) + $bx);
    
    return [
        'lat' => rad2deg($lat3),
        'lng' => rad2deg($lon3)
    ];
}

/**
 * Check if a point is within a polygon (for classroom boundaries)
 * 
 * @param float $point_lat
 * @param float $point_lng
 * @param array $polygon Array of ['lat' => float, 'lng' => float]
 * @return bool
 */
function pointInPolygon($point_lat, $point_lng, $polygon) {
    if (!is_array($polygon) || count($polygon) < 3) {
        return true; // No polygon defined
    }
    
    $intersections = 0;
    $vertices_count = count($polygon);
    
    for ($i = 0; $i < $vertices_count; $i++) {
        $vertex1 = $polygon[$i];
        $vertex2 = $polygon[($i + 1) % $vertices_count];
        
        // Check if the point is on the vertex
        if ($vertex1['lat'] == $point_lat && $vertex1['lng'] == $point_lng) {
            return true;
        }
        
        // Check if the segment straddles the horizontal line at y = $point_lat
        if (($vertex1['lat'] > $point_lat) != ($vertex2['lat'] > $point_lat)) {
            // Compute intersection point
            $x_intersect = $vertex1['lng'] + ($point_lat - $vertex1['lat']) * 
                          ($vertex2['lng'] - $vertex1['lng']) / 
                          ($vertex2['lat'] - $vertex1['lat']);
            
            if ($x_intersect > $point_lng) {
                $intersections++;
            }
        }
    }
    
    // Point is inside if number of intersections is odd
    return ($intersections % 2) == 1;
}

/**
 * Get geohash for a coordinate (simplified)
 * 
 * @param float $lat
 * @param float $lng
 * @param int $precision
 * @return string
 */
function getGeohash($lat, $lng, $precision = 8) {
    $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
    $lat_range = [-90, 90];
    $lng_range = [-180, 180];
    $hash = '';
    $bits_total = $precision * 5;
    $bit = 0;
    $ch = 0;
    
    for ($i = 0; $i < $bits_total; $i++) {
        if ($i % 2 == 0) {
            // Even bits: longitude
            $mid = ($lng_range[0] + $lng_range[1]) / 2;
            if ($lng > $mid) {
                $ch |= 1 << (4 - $bit);
                $lng_range[0] = $mid;
            } else {
                $lng_range[1] = $mid;
            }
        } else {
            // Odd bits: latitude
            $mid = ($lat_range[0] + $lat_range[1]) / 2;
            if ($lat > $mid) {
                $ch |= 1 << (4 - $bit);
                $lat_range[0] = $mid;
            } else {
                $lat_range[1] = $mid;
            }
        }
        
        $bit++;
        if ($bit == 5) {
            $hash .= $base32[$ch];
            $bit = 0;
            $ch = 0;
        }
    }
    
    return $hash;
}

/**
 * Calculate optimal radius based on classroom type and size
 * 
 * @param string $session_type
 * @param float $classroom_area Optional classroom area in sq meters
 * @return int
 */
function calculateOptimalRadius($session_type, $classroom_area = null) {
    $base_radius = match($session_type) {
        'Exam' => defined('DEFAULT_RADIUS_EXAM') ? DEFAULT_RADIUS_EXAM : 60,
        'Lab' => defined('DEFAULT_RADIUS_LAB') ? DEFAULT_RADIUS_LAB : 40,
        default => defined('DEFAULT_RADIUS_LECTURE') ? DEFAULT_RADIUS_LECTURE : 30
    };
    
    if ($classroom_area && $classroom_area > 0) {
        // Adjust radius based on classroom size
        // Assuming square classroom, radius = sqrt(area/π)
        $calculated_radius = sqrt($classroom_area / M_PI);
        return max($base_radius, min(100, round($calculated_radius * 1.2))); // Add 20% buffer
    }
    
    return $base_radius;
}

/**
 * Format distance for display
 * 
 * @param float $distance
 * @return string
 */
function formatDistance($distance) {
    if ($distance < 1) {
        return round($distance * 100) . ' cm';
    } elseif ($distance < 1000) {
        return round($distance, 1) . ' m';
    } else {
        return round($distance / 1000, 2) . ' km';
    }
}

/**
 * Check if coordinates are in India (or specified country)
 * 
 * @param float $lat
 * @param float $lng
 * @param string $country
 * @return bool
 */
function isInCountry($lat, $lng, $country = 'IN') {
    // Rough bounding boxes for countries
    $bounds = [
        'IN' => ['min_lat' => 6.0, 'max_lat' => 37.0, 'min_lng' => 68.0, 'max_lng' => 97.0], // India
        'US' => ['min_lat' => 24.0, 'max_lat' => 49.0, 'min_lng' => -125.0, 'max_lng' => -66.0], // USA
        'UK' => ['min_lat' => 49.0, 'max_lat' => 61.0, 'min_lng' => -8.0, 'max_lng' => 2.0], // UK
        'AE' => ['min_lat' => 22.0, 'max_lat' => 26.0, 'min_lng' => 51.0, 'max_lng' => 56.0], // UAE
        'SG' => ['min_lat' => 1.2, 'max_lat' => 1.5, 'min_lng' => 103.6, 'max_lng' => 104.1], // Singapore
    ];
    
    $bound = $bounds[$country] ?? $bounds['IN'];
    
    return ($lat >= $bound['min_lat'] && $lat <= $bound['max_lat'] &&
            $lng >= $bound['min_lng'] && $lng <= $bound['max_lng']);
}
?>