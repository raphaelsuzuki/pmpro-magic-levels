<?php
/**
 * Admin Interface for PMPro Magic Levels.
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * PMPRO_Magic_Levels_Admin class.
 *
 * Handles admin interface and settings.
 *
 * @since 1.0.0
 */
class PMPRO_Magic_Levels_Admin {

	/**
	 * Initialize admin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_pmpro_ml_regenerate_key', array( __CLASS__, 'regenerate_webhook_key' ) );
		add_action( 'admin_post_pmpro_ml_test_webhook', array( __CLASS__, 'test_webhook' ) );
		add_filter( 'debug_information', array( __CLASS__, 'add_site_health_info' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'add_site_health_tests' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function add_admin_menu() {
		$page_hook = add_submenu_page(
			'pmpro-membershiplevels',
			'Magic Levels',
			'Magic Levels',
			'manage_options',
			'pmpro-magic-levels',
			array( __CLASS__, 'render_admin_page' )
		);

		// Enqueue PMPro admin styles on our page.
		add_action( 'admin_print_styles-' . $page_hook, array( __CLASS__, 'enqueue_admin_styles' ) );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function enqueue_admin_styles() {
		if ( defined( 'PMPRO_VERSION' ) ) {
			wp_enqueue_style( 'pmpro-admin', plugins_url( 'paid-memberships-pro/css/admin.css' ), array(), PMPRO_VERSION );
		}
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting( 'pmpro_magic_levels', 'pmpro_ml_webhook_enabled' );
		register_setting( 'pmpro_magic_levels', 'pmpro_ml_webhook_key' );
	}

	/**
	 * Get or generate webhook key.
	 *
	 * @since 1.0.0
	 *
	 * @return string Webhook key.
	 */
	public static function get_webhook_key() {
		$key = get_option( 'pmpro_ml_webhook_key' );

		if ( empty( $key ) ) {
			$key = self::generate_webhook_key();
			update_option( 'pmpro_ml_webhook_key', $key );
		}

		return $key;
	}

	/**
	 * Generate a secure webhook key.
	 *
	 * Uses cryptographically secure random bytes.
	 *
	 * @since 1.0.0
	 *
	 * @return string Generated key (64 characters, base64 encoded).
	 */
	private static function generate_webhook_key() {
		// Generate 48 random bytes, base64 encode to get 64 characters.
		// This is equivalent to: openssl rand -base64 48
		$random_bytes = random_bytes( 48 );
		return base64_encode( $random_bytes );
	}

	/**
	 * Get webhook URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Webhook URL.
	 */
	public static function get_webhook_url() {
		$key = self::get_webhook_key();
		return rest_url( 'pmpro-magic-levels/v1/process?key=' . $key );
	}

	/**
	 * Regenerate webhook key.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function regenerate_webhook_key() {
		check_admin_referer( 'pmpro_ml_regenerate_key' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$new_key = self::generate_webhook_key();
		update_option( 'pmpro_ml_webhook_key', $new_key );

		wp_redirect( admin_url( 'admin.php?page=pmpro-magic-levels&regenerated=1' ) );
		exit;
	}

	/**
	 * Test webhook by creating a test level via HTTP request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function test_webhook() {
		check_admin_referer( 'pmpro_ml_test_webhook' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Get webhook key.
		$webhook_key = self::get_webhook_key();

		if ( empty( $webhook_key ) ) {
			wp_redirect( admin_url( 'admin.php?page=pmpro-magic-levels&test_error=' . urlencode( 'No webhook key configured' ) ) );
			exit;
		}

		// Generate random test data.
		$periods       = array( 'Month', 'Year' );
		$cycle_numbers = array( 1, 3, 6, 12 );
		$random_id     = wp_rand( 1000, 9999 );

		$billing_amount  = wp_rand( 10, 999 ) + ( wp_rand( 0, 99 ) / 100 );
		$initial_payment = wp_rand( 0, 1 ) ? ( $billing_amount * 0.5 ) : 0; // 50% chance of initial payment
		$trial_amount    = wp_rand( 0, 1 ) ? ( wp_rand( 1, 10 ) + ( wp_rand( 0, 99 ) / 100 ) ) : 0; // 50% chance of trial
		$trial_limit     = $trial_amount > 0 ? wp_rand( 1, 3 ) : 0; // 1-3 billing cycles for trial
		$billing_limit   = wp_rand( 0, 1 ) ? wp_rand( 6, 24 ) : 0; // 50% chance of billing limit

		$test_data = array(
			'name'              => 'TEST GROUP - TEST LEVEL ' . $random_id,
			'description'       => 'This is a test level created by PMPro Magic Levels webhook test. You can safely delete this level.',
			'billing_amount'    => $billing_amount,
			'cycle_period'      => $periods[ array_rand( $periods ) ],
			'cycle_number'      => $cycle_numbers[ array_rand( $cycle_numbers ) ],
			'initial_payment'   => $initial_payment,
			'billing_limit'     => $billing_limit,
			'trial_amount'      => $trial_amount,
			'trial_limit'       => $trial_limit,
			'expiration_number' => wp_rand( 0, 1 ) ? wp_rand( 1, 12 ) : 0,
			'expiration_period' => 'Month',
			'allow_signups'     => 0, // Disable signups for test levels
		);

		// Make HTTP POST request to webhook endpoint.
		$response = wp_remote_post(
			rest_url( 'pmpro-magic-levels/v1/process' ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $webhook_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $test_data ),
				'timeout' => 30,
			)
		);

		// Check for HTTP errors.
		if ( is_wp_error( $response ) ) {
			wp_redirect( admin_url( 'admin.php?page=pmpro-magic-levels&test_error=' . urlencode( 'HTTP Error: ' . $response->get_error_message() ) ) );
			exit;
		}

		// Parse response.
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$result      = json_decode( $body, true );

		// Check response status.
		if ( 200 !== $status_code ) {
			$error_msg = isset( $result['message'] ) ? $result['message'] : 'HTTP ' . $status_code;
			wp_redirect( admin_url( 'admin.php?page=pmpro-magic-levels&test_error=' . urlencode( $error_msg ) ) );
			exit;
		}

		// Check if level was created.
		if ( isset( $result['success'] ) && $result['success'] && isset( $result['level_id'] ) ) {
			// Redirect to edit the created level.
			wp_redirect( admin_url( 'admin.php?page=pmpro-membershiplevels&edit=' . $result['level_id'] . '&test_created=1' ) );
		} else {
			$error_msg = isset( $result['error'] ) ? $result['error'] : 'Unknown error';
			wp_redirect( admin_url( 'admin.php?page=pmpro-magic-levels&test_error=' . urlencode( $error_msg ) ) );
		}
		exit;
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle form submission.
		if ( isset( $_POST['pmpro_ml_save_settings'] ) ) {
			check_admin_referer( 'pmpro_ml_settings' );
			update_option( 'pmpro_ml_webhook_enabled', isset( $_POST['pmpro_ml_webhook_enabled'] ) ? '1' : '0' );
			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}

		$webhook_enabled = get_option( 'pmpro_ml_webhook_enabled', '0' );
		$webhook_key     = get_option( 'pmpro_ml_webhook_key' );
		$webhook_url     = self::get_webhook_url();
		$regenerated     = isset( $_GET['regenerated'] ) ? true : false;
		$test_error      = isset( $_GET['test_error'] ) ? sanitize_text_field( wp_unslash( $_GET['test_error'] ) ) : '';
		?>
		<div class="wrap pmpro_admin">
			<h1 class="wp-heading-inline">PMPro Magic Levels</h1>
			<hr class="wp-header-end">

			<?php if ( $regenerated ) : ?>
				<div class="notice notice-warning is-dismissible">
					<p><strong>Webhook URL regenerated!</strong> The previous URL will no longer work. Update any forms or integrations using the old URL.</p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $test_error ) ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><strong>Test failed:</strong> <?php echo esc_html( $test_error ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'pmpro_ml_settings' ); ?>

				<!-- Webhook Endpoint Section -->
				<div class="pmpro_section" data-visibility="shown" data-activated="true">
					<div class="pmpro_section_toggle">
						<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
							<span class="dashicons dashicons-arrow-up-alt2"></span>
							Webhook Endpoint
						</button>
					</div>
					<div class="pmpro_section_inside">
						<p>The Webhook Endpoint allows external services and forms to create membership levels via HTTP POST requests. All requests are secured with Bearer token authentication using a cryptographically secure 64-character key.</p>

						<table class="form-table">
							<tr>
								<th scope="row">Enable Webhook Endpoint</th>
								<td>
									<label>
										<input type="checkbox" name="pmpro_ml_webhook_enabled" value="1" <?php checked( $webhook_enabled, '1' ); ?>>
										Enable webhook endpoint
									</label>
									<p class="description">When disabled, the webhook URL will return a 403 error.</p>
								</td>
							</tr>

							<?php if ( '1' === $webhook_enabled ) : ?>
							<tr>
								<th scope="row">Webhook URL</th>
								<td>
									<input type="text" readonly value="<?php echo esc_attr( rest_url( 'pmpro-magic-levels/v1/process' ) ); ?>" class="large-text code" onclick="this.select();">
									<p class="description">Send POST requests to this endpoint with Bearer token authentication.</p>
								</td>
							</tr>

							<tr>
								<th scope="row">Bearer Token</th>
								<td>
									<input type="text" readonly value="<?php echo esc_attr( self::get_webhook_key() ); ?>" class="large-text code" onclick="this.select();" style="font-family: monospace;">
									<p class="description">Token only (for curl or code integrations)</p>
								</td>
							</tr>

							<tr>
								<th scope="row">Authorization Header Value</th>
								<td>
									<input type="text" readonly value="Bearer <?php echo esc_attr( self::get_webhook_key() ); ?>" class="large-text code" onclick="this.select();" style="font-family: monospace;">
									<p class="description">Full header value (for form plugins). <strong>This is the same token as above, just formatted differently.</strong></p>
								</td>
							</tr>

							<tr>
								<th scope="row">Regenerate Security Key</th>
								<td>
									<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=pmpro_ml_regenerate_key' ), 'pmpro_ml_regenerate_key' ); ?>" class="button" onclick="return confirm('Are you sure? The current webhook URL will stop working and you will need to update all integrations.');">Regenerate Key</a>
									<p class="description">Generate a new security key. This will invalidate the current webhook URL.</p>
								</td>
							</tr>

							<tr>
								<th scope="row">Test Webhook</th>
								<td>
									<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=pmpro_ml_test_webhook' ), 'pmpro_ml_test_webhook' ); ?>" class="button button-secondary">Create Test Level</a>
									<p class="description">Creates a test membership level with random data. You'll be redirected to edit the created level.</p>
								</td>
							</tr>
							<?php endif; ?>
						</table>

						<p class="submit">
							<input type="submit" name="pmpro_ml_save_settings" class="button button-primary" value="Save Settings">
						</p>
					</div>
				</div>

			</form>

			<!-- API Parameters Section -->
			<div class="pmpro_section" data-visibility="shown" data-activated="true">
				<div class="pmpro_section_toggle">
					<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
						<span class="dashicons dashicons-arrow-up-alt2"></span>
						API Parameters
					</button>
				</div>
				<div class="pmpro_section_inside">
					<p>Send a POST request to the webhook URL with JSON data containing these parameters:</p>

					<h3>Required Parameters</h3>
					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width: 200px;">Parameter</th>
								<th style="width: 100px;">Type</th>
								<th>Description</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><input type="text" readonly value="name" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 100px;"></td>
								<td>string</td>
								<td>Level name in format "GroupName - LevelName" (e.g., "Premium - Gold Monthly")</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="billing_amount" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 150px;"></td>
								<td>float</td>
								<td>Recurring billing amount (required to prevent free levels)</td>
							</tr>
						</tbody>
					</table>

					<h3>Optional Parameters</h3>
					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width: 200px;">Parameter</th>
								<th style="width: 100px;">Type</th>
								<th>Description</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><input type="text" readonly value="cycle_number" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 130px;"></td>
								<td>integer</td>
								<td>Billing cycle number (default: 1)</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="cycle_period" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 130px;"></td>
								<td>string</td>
								<td>Billing period: Day, Week, Month, Year (default: Month)</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="initial_payment" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 150px;"></td>
								<td>float</td>
								<td>One-time initial payment</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="billing_limit" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 130px;"></td>
								<td>integer</td>
								<td>Number of billing cycles before expiration</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="trial_amount" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 130px;"></td>
								<td>float</td>
								<td>Trial period amount</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="trial_limit" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 120px;"></td>
								<td>integer</td>
								<td>Trial period duration</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="expiration_number" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 170px;"></td>
								<td>integer</td>
								<td>Expiration duration</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="expiration_period" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 170px;"></td>
								<td>string</td>
								<td>Expiration period: Day, Week, Month, Year</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Validation Rules Section -->
			<div class="pmpro_section" data-visibility="shown" data-activated="true">
				<div class="pmpro_section_toggle">
					<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
						<span class="dashicons dashicons-arrow-up-alt2"></span>
						Validation Rules
					</button>
				</div>
				<div class="pmpro_section_inside">
					<p>The following validation rules are applied to all level creation requests (Webhook Endpoint and custom integrations):</p>

					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width: 250px;">Rule</th>
								<th style="width: 200px;">Default Value</th>
								<th>Filter</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>Allowed Periods</td>
								<td>Day, Week, Month, Year</td>
								<td><input type="text" readonly value="pmpro_magic_levels_allowed_periods" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 300px;"></td>
							</tr>
							<tr>
								<td>Allowed Cycle Numbers</td>
								<td>1, 2, 3, 6, 12</td>
								<td><input type="text" readonly value="pmpro_magic_levels_allowed_cycle_numbers" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 350px;"></td>
							</tr>
							<tr>
								<td>Min Name Length</td>
								<td>1</td>
								<td><input type="text" readonly value="pmpro_magic_levels_min_name_length" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 300px;"></td>
							</tr>
							<tr>
								<td>Max Name Length</td>
								<td>255</td>
								<td><input type="text" readonly value="pmpro_magic_levels_max_name_length" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 300px;"></td>
							</tr>
							<tr>
								<td>Rate Limit (requests/hour)</td>
								<td>100</td>
								<td><input type="text" readonly value="pmpro_magic_levels_rate_limit" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 250px;"></td>
							</tr>
							<tr>
								<td>Max Levels Per Day</td>
								<td>1000</td>
								<td><input type="text" readonly value="pmpro_magic_levels_max_levels_per_day" onclick="this.select();" style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 300px;"></td>
							</tr>
						</tbody>
					</table>

					<p class="description">
						Use these filters in your theme or plugin to customize validation rules. 
						<a href="https://github.com/YOUR_REPO/pmpro-magic-levels/blob/master/docs/README.md#advanced-validation" target="_blank">View advanced validation examples →</a>
					</p>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Add Site Health information.
	 *
	 * @since 1.0.0
	 *
	 * @param array $info Site Health info.
	 * @return array Modified info.
	 */
	public static function add_site_health_info( $info ) {
		$webhook_enabled = get_option( 'pmpro_ml_webhook_enabled', '0' );
		$webhook_key     = get_option( 'pmpro_ml_webhook_key' );

		$info['pmpro-magic-levels'] = array(
			'label'  => 'PMPro Magic Levels',
			'fields' => array(
				'webhook_status'   => array(
					'label' => 'Webhook Endpoint',
					'value' => '1' === $webhook_enabled ? 'Enabled' : 'Disabled',
				),
				'webhook_key_set'  => array(
					'label' => 'Security Key',
					'value' => ! empty( $webhook_key ) ? 'Configured' : 'Not Set',
				),
				'rest_api_enabled' => array(
					'label' => 'REST API',
					'value' => get_option( 'permalink_structure' ) ? 'Available' : 'Requires Pretty Permalinks',
				),
				'pmpro_version'    => array(
					'label' => 'PMPro Version',
					'value' => defined( 'PMPRO_VERSION' ) ? PMPRO_VERSION : 'Not Detected',
				),
			),
		);

		return $info;
	}

	/**
	 * Add Site Health tests.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tests Site Health tests.
	 * @return array Modified tests.
	 */
	public static function add_site_health_tests( $tests ) {
		$tests['direct']['pmpro_magic_levels_rest_api'] = array(
			'label' => 'PMPro Magic Levels REST API',
			'test'  => array( __CLASS__, 'test_rest_api' ),
		);

		$tests['direct']['pmpro_magic_levels_webhook'] = array(
			'label' => 'PMPro Magic Levels Webhook',
			'test'  => array( __CLASS__, 'test_webhook_config' ),
		);

		return $tests;
	}

	/**
	 * Test REST API availability.
	 *
	 * @since 1.0.0
	 *
	 * @return array Test result.
	 */
	public static function test_rest_api() {
		$result = array(
			'label'       => 'REST API is available',
			'status'      => 'good',
			'badge'       => array(
				'label' => 'PMPro Magic Levels',
				'color' => 'blue',
			),
			'description' => '<p>The WordPress REST API is properly configured and accessible.</p>',
			'test'        => 'pmpro_magic_levels_rest_api',
		);

		// Check if pretty permalinks are enabled.
		if ( ! get_option( 'permalink_structure' ) ) {
			$result['status']      = 'critical';
			$result['label']       = 'REST API requires pretty permalinks';
			$result['description'] = '<p>PMPro Magic Levels requires pretty permalinks to be enabled for the REST API to work. Go to Settings > Permalinks and select any option other than "Plain".</p>';
			return $result;
		}

		// Test if REST API is accessible.
		$response = wp_remote_get( rest_url( 'pmpro-magic-levels/v1/process' ) );

		if ( is_wp_error( $response ) ) {
			$result['status']      = 'critical';
			$result['label']       = 'REST API is not accessible';
			$result['description'] = '<p>The REST API endpoint could not be reached. Error: ' . esc_html( $response->get_error_message() ) . '</p>';
			return $result;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// 403 is expected if webhook is disabled or no key provided.
		if ( 403 === $status_code || 405 === $status_code ) {
			$result['description'] = '<p>The REST API endpoint is accessible and properly secured.</p>';
			return $result;
		}

		return $result;
	}

	/**
	 * Test webhook configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array Test result.
	 */
	public static function test_webhook_config() {
		$webhook_enabled = get_option( 'pmpro_ml_webhook_enabled', '0' );
		$webhook_key     = get_option( 'pmpro_ml_webhook_key' );

		$result = array(
			'label'       => 'Webhook Endpoint is configured',
			'status'      => 'good',
			'badge'       => array(
				'label' => 'PMPro Magic Levels',
				'color' => 'blue',
			),
			'description' => '<p>The Webhook Endpoint is properly configured and ready to use.</p>',
			'test'        => 'pmpro_magic_levels_webhook',
		);

		if ( '1' !== $webhook_enabled ) {
			$result['status']      = 'recommended';
			$result['label']       = 'Webhook Endpoint is disabled';
			$result['description'] = '<p>The Webhook Endpoint is currently disabled. If you plan to use external forms or services, enable it in <a href="' . admin_url( 'admin.php?page=pmpro-magic-levels' ) . '">PMPro > Magic Levels</a>.</p>';
			return $result;
		}

		if ( empty( $webhook_key ) ) {
			$result['status']      = 'critical';
			$result['label']       = 'Webhook security key is missing';
			$result['description'] = '<p>The webhook is enabled but no security key is configured. Visit <a href="' . admin_url( 'admin.php?page=pmpro-magic-levels' ) . '">PMPro > Magic Levels</a> to generate a key.</p>';
			return $result;
		}

		return $result;
	}
}
