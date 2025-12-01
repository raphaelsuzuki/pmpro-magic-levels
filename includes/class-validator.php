<?php
/**
 * Validator for Magic Levels
 */

defined( 'ABSPATH' ) || exit;

class PMPRO_Magic_Levels_Validator {

    /**
     * Get default validation rules
     */
    private function get_defaults() {
        return [
            'price_increment' => apply_filters( 'pmpro_magic_levels_price_increment', 1.00 ),
            'min_price' => apply_filters( 'pmpro_magic_levels_min_price', 0.00 ),
            'max_price' => apply_filters( 'pmpro_magic_levels_max_price', 9999.99 ),
            'allowed_periods' => apply_filters( 'pmpro_magic_levels_allowed_periods', [ 'Day', 'Week', 'Month', 'Year' ] ),
            'allowed_cycle_numbers' => apply_filters( 'pmpro_magic_levels_allowed_cycle_numbers', [ 1, 2, 3, 6, 12 ] ),
            'max_billing_limit' => apply_filters( 'pmpro_magic_levels_max_billing_limit', 999 ),
            'min_name_length' => apply_filters( 'pmpro_magic_levels_min_name_length', 1 ),
            'max_name_length' => apply_filters( 'pmpro_magic_levels_max_name_length', 255 ),
            'name_pattern' => apply_filters( 'pmpro_magic_levels_name_pattern', null ),
            'allow_free_levels' => apply_filters( 'pmpro_magic_levels_allow_free_levels', true ),
            'require_initial_payment' => apply_filters( 'pmpro_magic_levels_require_initial_payment', false ),
            'name_blacklist' => apply_filters( 'pmpro_magic_levels_name_blacklist', [] ),
            'max_levels_per_day' => apply_filters( 'pmpro_magic_levels_max_levels_per_day', 1000 ),
        ];
    }

    /**
     * Validate level data
     */
    public function validate( $data ) {
        $rules = $this->get_defaults();

        // Check required fields
        if ( empty( $data['name'] ) ) {
            return $this->error( 'Name is required', 'missing_required_field' );
        }

        // Validate name length
        $name_length = strlen( $data['name'] );
        if ( $name_length < $rules['min_name_length'] ) {
            return $this->error( "Name must be at least {$rules['min_name_length']} characters", 'name_too_short' );
        }
        if ( $name_length > $rules['max_name_length'] ) {
            return $this->error( "Name must be less than {$rules['max_name_length']} characters", 'name_too_long' );
        }

        // Validate name pattern
        if ( ! empty( $rules['name_pattern'] ) && ! preg_match( $rules['name_pattern'], $data['name'] ) ) {
            return $this->error( 'Name contains invalid characters', 'invalid_name_pattern' );
        }

        // Check name blacklist
        foreach ( $rules['name_blacklist'] as $blacklisted ) {
            if ( stripos( $data['name'], $blacklisted ) !== false ) {
                return $this->error( 'Name contains blacklisted word', 'blacklisted_name' );
            }
        }

        // Validate prices
        if ( isset( $data['billing_amount'] ) ) {
            $billing_amount = floatval( $data['billing_amount'] );
            
            // Check free levels
            if ( $billing_amount == 0 && ! $rules['allow_free_levels'] ) {
                return $this->error( 'Free levels are not allowed', 'free_levels_disabled' );
            }

            // Check price increment
            if ( $rules['price_increment'] > 0 && fmod( $billing_amount, $rules['price_increment'] ) != 0 ) {
                return $this->error( "Price must be a multiple of \${$rules['price_increment']}", 'invalid_price_increment' );
            }

            // Check min/max price
            if ( $billing_amount < $rules['min_price'] ) {
                return $this->error( "Price must be at least \${$rules['min_price']}", 'price_below_minimum' );
            }
            if ( $billing_amount > $rules['max_price'] ) {
                return $this->error( "Price cannot exceed \${$rules['max_price']}", 'price_above_maximum' );
            }
        }

        // Validate initial payment
        if ( isset( $data['initial_payment'] ) ) {
            $initial_payment = floatval( $data['initial_payment'] );
            
            if ( $rules['require_initial_payment'] && $initial_payment == 0 ) {
                return $this->error( 'Initial payment is required', 'initial_payment_required' );
            }

            if ( $initial_payment < $rules['min_price'] ) {
                return $this->error( "Initial payment must be at least \${$rules['min_price']}", 'price_below_minimum' );
            }
            if ( $initial_payment > $rules['max_price'] ) {
                return $this->error( "Initial payment cannot exceed \${$rules['max_price']}", 'price_above_maximum' );
            }
        }

        // Validate cycle period
        if ( isset( $data['cycle_period'] ) && ! empty( $data['cycle_period'] ) ) {
            if ( ! in_array( $data['cycle_period'], $rules['allowed_periods'] ) ) {
                return $this->error( 'Invalid cycle period', 'invalid_cycle_period' );
            }
        }

        // Validate cycle number
        if ( isset( $data['cycle_number'] ) && ! empty( $data['cycle_number'] ) ) {
            if ( ! in_array( intval( $data['cycle_number'] ), $rules['allowed_cycle_numbers'] ) ) {
                return $this->error( 'Invalid cycle number', 'invalid_cycle_number' );
            }
        }

        // Validate billing limit
        if ( isset( $data['billing_limit'] ) && intval( $data['billing_limit'] ) > $rules['max_billing_limit'] ) {
            return $this->error( "Billing limit cannot exceed {$rules['max_billing_limit']}", 'billing_limit_exceeded' );
        }

        // Check rate limiting
        $rate_limit_check = $this->check_rate_limit();
        if ( ! $rate_limit_check['valid'] ) {
            return $rate_limit_check;
        }

        // Check daily limit
        $daily_limit_check = $this->check_daily_limit( $rules['max_levels_per_day'] );
        if ( ! $daily_limit_check['valid'] ) {
            return $daily_limit_check;
        }

        return [ 'valid' => true ];
    }

    /**
     * Check rate limiting
     */
    private function check_rate_limit() {
        $rate_limit = apply_filters( 'pmpro_magic_levels_rate_limit', [
            'max_requests' => 100,
            'time_window' => 3600,
            'by' => 'ip'
        ] );

        $identifier = $rate_limit['by'] === 'user' ? get_current_user_id() : $this->get_client_ip();
        $transient_key = 'pmpro_magic_levels_rate_' . md5( $identifier );
        
        $requests = get_transient( $transient_key );
        
        if ( $requests === false ) {
            set_transient( $transient_key, 1, $rate_limit['time_window'] );
            return [ 'valid' => true ];
        }

        if ( $requests >= $rate_limit['max_requests'] ) {
            $ttl = get_option( '_transient_timeout_' . $transient_key ) - time();
            $minutes = ceil( $ttl / 60 );
            return $this->error( "Rate limit exceeded. Try again in {$minutes} minutes.", 'rate_limit_exceeded' );
        }

        set_transient( $transient_key, $requests + 1, $rate_limit['time_window'] );
        return [ 'valid' => true ];
    }

    /**
     * Check daily level creation limit
     */
    private function check_daily_limit( $max_levels ) {
        $today = date( 'Y-m-d' );
        $count_key = 'pmpro_magic_levels_count_' . $today;
        
        $count = get_transient( $count_key );
        
        if ( $count === false ) {
            set_transient( $count_key, 0, DAY_IN_SECONDS );
            return [ 'valid' => true ];
        }

        if ( $count >= $max_levels ) {
            return $this->error( 'Daily level creation limit exceeded', 'daily_limit_exceeded' );
        }

        return [ 'valid' => true ];
    }

    /**
     * Increment daily counter
     */
    public function increment_daily_counter() {
        $today = date( 'Y-m-d' );
        $count_key = 'pmpro_magic_levels_count_' . $today;
        $count = get_transient( $count_key );
        set_transient( $count_key, $count + 1, DAY_IN_SECONDS );
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * Return error response
     */
    private function error( $message, $code ) {
        return [
            'valid' => false,
            'error' => $message,
            'code' => $code
        ];
    }
}
