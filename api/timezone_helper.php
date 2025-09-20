<?php
/**
 * Timezone Helper Functions
 * Provides timezone-aware time formatting for template processing
 */

require_once 'db_config.php';

/**
 * Get user's configured timezone from ai_settings
 */
function getUserTimezone() {
    global $ai_pdo;
    
    try {
        $stmt = $ai_pdo->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = 'user_timezone'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : 'America/New_York';
    } catch (Exception $e) {
        error_log("Failed to get user timezone: " . $e->getMessage());
        return 'America/New_York'; // Default fallback
    }
}

/**
 * Format current time in user's timezone
 */
function formatCurrentTime($format = 'Y-m-d H:i:s T') {
    $timezone = getUserTimezone();
    
    try {
        $date = new DateTime('now', new DateTimeZone($timezone));
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Timezone formatting error: " . $e->getMessage());
        // Fallback to system time
        return date($format);
    }
}

/**
 * Format a specific timestamp in user's timezone
 */
function formatTimestampInUserTimezone($timestamp, $format = 'Y-m-d H:i:s T') {
    $timezone = getUserTimezone();
    
    try {
        $date = new DateTime($timestamp, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone($timezone));
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Timestamp conversion error: " . $e->getMessage());
        return date($format, strtotime($timestamp));
    }
}

/**
 * Process template variables in text with timezone awareness
 */
function processTemplateVariables($text) {
    $timezone = getUserTimezone();
    
    // Replace time-related template variables
    $replacements = [
        '{current_time}' => formatCurrentTime('g:i A'),
        '{current_date}' => formatCurrentTime('F j, Y'),
        '{current_datetime}' => formatCurrentTime('F j, Y g:i A T'),
        '{current_day}' => formatCurrentTime('l'),
        '{current_timezone}' => $timezone,
        '{timezone_name}' => getTimezoneDisplayName($timezone)
    ];
    
    // Process each replacement
    foreach ($replacements as $placeholder => $value) {
        $text = str_replace($placeholder, $value, $text);
    }
    
    return $text;
}

/**
 * Get human-readable timezone name
 */
function getTimezoneDisplayName($timezone) {
    $timezoneMap = [
        'America/New_York' => 'Eastern Time',
        'America/Chicago' => 'Central Time', 
        'America/Denver' => 'Mountain Time',
        'America/Los_Angeles' => 'Pacific Time',
        'America/Phoenix' => 'Arizona Time',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time',
        'UTC' => 'UTC',
        'Europe/London' => 'GMT',
        'Europe/Paris' => 'Central European Time',
        'Europe/Rome' => 'Central European Time',
        'Europe/Moscow' => 'Moscow Time',
        'Asia/Tokyo' => 'Japan Standard Time',
        'Asia/Shanghai' => 'China Standard Time',
        'Asia/Mumbai' => 'India Standard Time',
        'Asia/Dubai' => 'Gulf Standard Time',
        'Australia/Sydney' => 'Australian Eastern Time',
        'Australia/Melbourne' => 'Australian Eastern Time',
        'Pacific/Auckland' => 'New Zealand Time'
    ];
    
    return $timezoneMap[$timezone] ?? $timezone;
}

/**
 * Get relative time string in user's timezone
 */
function getRelativeTime($timestamp) {
    $timezone = getUserTimezone();
    
    try {
        $date = new DateTime($timestamp, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone($timezone));
        $now = new DateTime('now', new DateTimeZone($timezone));
        
        $diff = $now->diff($date);
        
        if ($diff->days > 7) {
            return $date->format('M j, Y');
        } elseif ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'just now';
        }
    } catch (Exception $e) {
        error_log("Relative time error: " . $e->getMessage());
        return 'recently';
    }
}

/**
 * Enhanced template processing with timezone support
 * Can be integrated into existing chat processing
 */
function enhanceMessageWithTimezone($message, $context = []) {
    // Process basic template variables
    $message = processTemplateVariables($message);
    
    // Add context-specific timezone information if needed
    if (isset($context['include_time']) && $context['include_time']) {
        $currentTime = formatCurrentTime('g:i A T');
        $message = "[$currentTime] " . $message;
    }
    
    return $message;
}
?>