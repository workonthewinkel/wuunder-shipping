<?php

namespace Wuunder\Shipping\Debug;

/**
 * Debug Logger for development only
 * This class is excluded from production builds
 */
class Logger {
    
    /**
     * Whether debug mode is enabled
     */
    private static bool $enabled = false;
    
    /**
     * Initialize the logger
     */
    public static function init(): void {
        // We're in development mode if this class exists (loaded via autoload-dev)
        self::$enabled = true;
        
        // Alternative: Check WordPress debug mode
        // self::$enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
        
        // Alternative: Check environment
        // self::$enabled = wp_get_environment_type() === 'development' || wp_get_environment_type() === 'local';
    }
    
    /**
     * Log debug information
     * 
     * @param string $message
     * @param mixed $data
     * @param string $level
     */
    public static function log( string $message, $data = null, string $level = 'info' ): void {
        if ( ! self::$enabled ) {
            return;
        }
        
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
        $caller = $backtrace[1] ?? [];
        $location = sprintf( '%s:%d', 
            basename( $caller['file'] ?? 'unknown' ), 
            $caller['line'] ?? 0 
        );
        
        $log_message = sprintf( 
            '[%s] [%s] [%s] %s', 
            $timestamp, 
            strtoupper( $level ), 
            $location,
            $message 
        );
        
        if ( $data !== null ) {
            $log_message .= ' | Data: ' . print_r( $data, true );
        }
        
        error_log( $log_message );
        
        // Optionally also log to a custom file
        self::logToFile( $log_message );
    }
    
    /**
     * Log to custom file
     */
    private static function logToFile( string $message ): void {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wuunder-logs';
        
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            
            // Add .htaccess to protect log files
            $htaccess = $log_dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                file_put_contents( $htaccess, 'Deny from all' );
            }
        }
        
        $log_file = $log_dir . '/debug-' . date( 'Y-m-d' ) . '.log';
        file_put_contents( $log_file, $message . PHP_EOL, FILE_APPEND | LOCK_EX );
    }
    
    /**
     * Log pickup point selection
     */
    public static function logPickupPoint( array $pickup_point, string $context = '' ): void {
        if ( ! self::$enabled ) {
            return;
        }
        
        self::log( 
            sprintf( 'Pickup point %s', $context ),
            [
                'id' => $pickup_point['id'] ?? 'N/A',
                'name' => $pickup_point['name'] ?? 'N/A',
                'carrier' => $pickup_point['carrier'] ?? 'N/A',
                'location' => sprintf( '%s, %s %s',
                    $pickup_point['street'] ?? '',
                    $pickup_point['postcode'] ?? '',
                    $pickup_point['city'] ?? ''
                )
            ]
        );
    }
    
    /**
     * Log API requests
     */
    public static function logApiRequest( string $endpoint, array $params = [], $response = null ): void {
        if ( ! self::$enabled ) {
            return;
        }
        
        self::log( 
            sprintf( 'API Request to %s', $endpoint ),
            [
                'params' => $params,
                'response' => $response
            ],
            'api'
        );
    }
    
    /**
     * Start timer for performance debugging
     */
    public static function startTimer( string $name ): void {
        if ( ! self::$enabled ) {
            return;
        }
        
        $GLOBALS['wuunder_debug_timers'][$name] = microtime( true );
    }
    
    /**
     * End timer and log elapsed time
     */
    public static function endTimer( string $name ): void {
        if ( ! self::$enabled ) {
            return;
        }
        
        if ( ! isset( $GLOBALS['wuunder_debug_timers'][$name] ) ) {
            return;
        }
        
        $elapsed = microtime( true ) - $GLOBALS['wuunder_debug_timers'][$name];
        self::log( sprintf( 'Timer "%s" took %.4f seconds', $name, $elapsed ), null, 'performance' );
        unset( $GLOBALS['wuunder_debug_timers'][$name] );
    }
    
    /**
     * Dump and die (development only)
     */
    public static function dd( $data, string $label = '' ): void {
        if ( ! self::$enabled ) {
            return;
        }
        
        echo '<pre style="background:#333; color:#0f0; padding:20px; margin:20px; border-radius:5px;">';
        if ( $label ) {
            echo '<strong style="color:#ff0;">' . esc_html( $label ) . '</strong>' . PHP_EOL . PHP_EOL;
        }
        var_dump( $data );
        echo '</pre>';
        die();
    }
}