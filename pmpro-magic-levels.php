<?php
/**
 * Plugin Name: PMPro Magic Levels
 * Plugin URI: https://github.com/yourusername/pmpro-magic-levels
 * Description: Create or find membership levels from form submissions and redirect to checkout
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: pmpro-magic-levels
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'PMPRO_MAGIC_LEVELS_VERSION', '1.0.0' );
define( 'PMPRO_MAGIC_LEVELS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PMPRO_MAGIC_LEVELS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if PMPro is active.
 *
 * @since 1.0.0
 *
 * @return bool True if PMPro is active, false otherwise.
 */
function pmpro_magic_levels_check_dependencies() {
	if ( ! defined( 'PMPRO_VERSION' ) ) {
		add_action( 'admin_notices', 'pmpro_magic_levels_dependency_notice' );
		return false;
	}
	return true;
}

/**
 * Show admin notice if PMPro is not active.
 *
 * @since 1.0.0
 *
 * @return void
 */
function pmpro_magic_levels_dependency_notice() {
	?>
	<div class="error">
		<p><?php esc_html_e( 'PMPro Magic Levels requires Paid Memberships Pro to be installed and activated.', 'pmpro-magic-levels' ); ?></p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 *
 * @return void
 */
function pmpro_magic_levels_init() {
	// Check dependencies.
	if ( ! pmpro_magic_levels_check_dependencies() ) {
		return;
	}

	// Load plugin files.
	require_once PMPRO_MAGIC_LEVELS_DIR . 'includes/class-cache.php';
	require_once PMPRO_MAGIC_LEVELS_DIR . 'includes/class-validator.php';
	require_once PMPRO_MAGIC_LEVELS_DIR . 'includes/class-level-matcher.php';
	require_once PMPRO_MAGIC_LEVELS_DIR . 'includes/class-webhook-handler.php';

	// Initialize webhook handler.
	PMPRO_Magic_Levels_Webhook_Handler::init();

	// Setup cache invalidation hooks.
	add_action( 'pmpro_added_membership_level', 'pmpro_magic_levels_clear_cache' );
	add_action( 'pmpro_updated_membership_level', 'pmpro_magic_levels_clear_cache' );
	add_action( 'pmpro_deleted_membership_level', 'pmpro_magic_levels_clear_cache' );
	// Initialize admin interface.
	if ( is_admin() ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-page.php';
		PMPRO_Magic_Levels_Admin::init();
	}
}
add_action( 'plugins_loaded', 'pmpro_magic_levels_init' );

/**
 * Clear all caches.
 *
 * @since 1.0.0
 *
 * @return void
 */
function pmpro_magic_levels_clear_cache() {
	PMPRO_Magic_Levels_Cache::clear_all();
}

/**
 * Public API function to process level data.
 *
 * @since 1.0.0
 *
 * @param array $level_data Level parameters.
 * @return array Result with success, level_id, redirect_url, or error.
 */
function pmpro_magic_levels_process( $level_data ) {
	// Validate.
	$validator  = new PMPRO_Magic_Levels_Validator();
	$validation = $validator->validate( $level_data );

	if ( ! $validation['valid'] ) {
		return array(
			'success' => false,
			'error'   => $validation['error'],
			'code'    => $validation['code'],
		);
	}

	// Find or create level.
	$matcher = new PMPRO_Magic_Levels_Level_Matcher();
	$result  = $matcher->find_or_create( $level_data );

	if ( ! $result['success'] ) {
		return $result;
	}

	return array(
		'success'       => true,
		'level_id'      => $result['level_id'],
		'level_created' => $result['level_created'],
		'cached'        => isset( $result['cached'] ) ? $result['cached'] : false,
		'message'       => $result['level_created'] ? 'New level created' : 'Existing level found',
	);
}

/**
 * Plugin activation.
 *
 * @since 1.0.0
 *
 * @return void
 */
function pmpro_magic_levels_activate() {
	// Check if PMPro is active.
	if ( ! defined( 'PMPRO_VERSION' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'PMPro Magic Levels requires Paid Memberships Pro to be installed and activated.', 'pmpro-magic-levels' ) );
	}

	// Add database index for faster lookups.
	global $wpdb;
	$table_name = $wpdb->pmpro_membership_levels;

	// Check if index exists.
	$index_exists = $wpdb->get_results(
		$wpdb->prepare(
			"SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
			'idx_magic_level_lookup'
		)
	);

	if ( empty( $index_exists ) ) {
		$wpdb->query(
			"CREATE INDEX idx_magic_level_lookup 
			ON {$table_name} (name(50), billing_amount, cycle_period(10), cycle_number)"
		);
	}
}
register_activation_hook( __FILE__, 'pmpro_magic_levels_activate' );

/**
 * Plugin deactivation.
 *
 * @since 1.0.0
 *
 * @return void
 */
function pmpro_magic_levels_deactivate() {
	// Clear all caches.
	pmpro_magic_levels_clear_cache();
}
register_deactivation_hook( __FILE__, 'pmpro_magic_levels_deactivate' );
