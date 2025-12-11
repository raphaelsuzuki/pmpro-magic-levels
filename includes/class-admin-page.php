<?php
/**
 * Admin Interface for PMPro Magic Levels.
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * PMPRO_Magic_Levels_Admin class.
 *
 * Handles admin interface and settings.
 *
 * @since 1.0.0
 */
class PMPRO_Magic_Levels_Admin
{

	/**
	 * Initialize admin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init()
	{
		add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
		add_action('admin_init', array(__CLASS__, 'register_settings'));
		add_action('admin_post_pmpro_ml_regenerate_key', array(__CLASS__, 'regenerate_webhook_key'));
		add_action('admin_post_pmpro_ml_test_webhook', array(__CLASS__, 'test_webhook'));
		add_filter('debug_information', array(__CLASS__, 'add_site_health_info'));
		add_filter('site_status_tests', array(__CLASS__, 'add_site_health_tests'));
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function add_admin_menu()
	{
		$page_hook = add_submenu_page(
			'pmpro-membershiplevels',
			__('Magic Levels', 'pmpro-magic-levels'),
			__('Magic Levels', 'pmpro-magic-levels'),
			'manage_options',
			'pmpro-magic-levels',
			array(__CLASS__, 'render_admin_page')
		);

		// Enqueue PMPro admin styles on our page.
		add_action('admin_print_styles-' . $page_hook, array(__CLASS__, 'enqueue_admin_styles'));
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function enqueue_admin_styles()
	{
		if (defined('PMPRO_VERSION')) {
			wp_enqueue_style('pmpro-admin', plugins_url('paid-memberships-pro/css/admin.css'), array(), PMPRO_VERSION);
		}
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_settings()
	{
		register_setting('pmpro_magic_levels', 'pmpro_ml_webhook_enabled');
		register_setting('pmpro_magic_levels', 'pmpro_ml_webhook_key');
	}

	/**
	 * Get or generate webhook key.
	 *
	 * @since 1.0.0
	 *
	 * @return string Webhook key.
	 */
	public static function get_webhook_key()
	{
		$key = get_option('pmpro_ml_webhook_key');

		if (empty($key)) {
			$key = self::generate_webhook_key();
			update_option('pmpro_ml_webhook_key', $key);
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
	private static function generate_webhook_key()
	{
		// Generate 48 random bytes, base64 encode to get 64 characters.
		// This is equivalent to: openssl rand -base64 48
		$random_bytes = random_bytes(48);
		return base64_encode($random_bytes);
	}



	/**
	 * Regenerate webhook key.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function regenerate_webhook_key()
	{
		check_admin_referer('pmpro_ml_regenerate_key');

		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized');
		}

		$new_key = self::generate_webhook_key();
		update_option('pmpro_ml_webhook_key', $new_key);

		wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&regenerated=1'));
		exit;
	}

	/**
	 * Test webhook by creating a test level via HTTP request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function test_webhook()
	{
		check_admin_referer('pmpro_ml_test_webhook');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'pmpro-magic-levels'));
		}

		// Get webhook key.
		$webhook_key = self::get_webhook_key();

		if (empty($webhook_key)) {
			wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&test_error=' . urlencode(__('No webhook key configured', 'pmpro-magic-levels'))));
			exit;
		}

		// Generate random test data.
		$periods = array('Month', 'Year');
		$cycle_numbers = array(1, 3, 6, 12);
		$random_id = wp_rand(1000, 9999);

		$billing_amount = wp_rand(10, 999) + (wp_rand(0, 99) / 100);
		$initial_payment = wp_rand(0, 1) ? ($billing_amount * 0.5) : 0; // 50% chance of initial payment
		$trial_amount = wp_rand(0, 1) ? (wp_rand(1, 10) + (wp_rand(0, 99) / 100)) : 0; // 50% chance of trial
		$trial_limit = $trial_amount > 0 ? wp_rand(1, 3) : 0; // 1-3 billing cycles for trial
		$billing_limit = wp_rand(0, 1) ? wp_rand(6, 24) : 0; // 50% chance of billing limit

		$test_data = array(
			'name' => 'TEST GROUP - ' . sprintf(__('TEST LEVEL %s', 'pmpro-magic-levels'), $random_id),
			'description' => __('This is a test level created by PMPro Magic Levels webhook test. You can safely delete this level.', 'pmpro-magic-levels'),
			'confirmation' => sprintf(__('Thank you for joining TEST LEVEL %s! This is a test confirmation message.', 'pmpro-magic-levels'), $random_id),
			'account_message' => sprintf(__('Welcome to TEST LEVEL %s! Your benefits include: Test access, Demo features, Sample content. This is a test account message.', 'pmpro-magic-levels'), $random_id),
			'billing_amount' => $billing_amount,
			'cycle_period' => $periods[array_rand($periods)],
			'cycle_number' => $cycle_numbers[array_rand($cycle_numbers)],
			'initial_payment' => $initial_payment,
			'billing_limit' => $billing_limit,
			'trial_amount' => $trial_amount,
			'trial_limit' => $trial_limit,
			'expiration_number' => wp_rand(0, 1) ? wp_rand(1, 12) : 0,
			'expiration_period' => 'Month',
			'allow_signups' => 0, // Disable signups for test levels
		);

		// Add content protection if categories/pages exist.
		$test_categories = get_categories(array('number' => 3, 'hide_empty' => false));
		if (!empty($test_categories)) {
			$test_data['protected_categories'] = array_map(function ($cat) {
				return $cat->term_id;
			}, array_slice($test_categories, 0, 2)); // Protect first 2 categories
		}

		$test_pages = get_pages(array('number' => 3));
		if (!empty($test_pages)) {
			$test_data['protected_pages'] = array_map(function ($page) {
				return $page->ID;
			}, array_slice($test_pages, 0, 1)); // Protect first page
		}

		// Make HTTP POST request to webhook endpoint.
		$response = wp_remote_post(
			rest_url('pmpro-magic-levels/v1/process'),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $webhook_key,
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode($test_data),
				'timeout' => 30,
			)
		);

		// Check for HTTP errors.
		if (is_wp_error($response)) {
			wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&test_error=' . urlencode(sprintf(__('HTTP Error: %s', 'pmpro-magic-levels'), $response->get_error_message()))));
			exit;
		}

		// Parse response.
		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$result = json_decode($body, true);

		// Check response status.
		if (200 !== $status_code) {
			$error_msg = isset($result['message']) ? $result['message'] : sprintf(__('HTTP %s', 'pmpro-magic-levels'), $status_code);
			wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&test_error=' . urlencode($error_msg)));
			exit;
		}

		// Check if level was created.
		if (isset($result['success']) && $result['success'] && isset($result['level_id'])) {
			// Redirect to edit the created level.
			wp_redirect(admin_url('admin.php?page=pmpro-membershiplevels&edit=' . $result['level_id'] . '&test_created=1'));
		} else {
			$error_msg = isset($result['error']) ? $result['error'] : __('Unknown error', 'pmpro-magic-levels');
			wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&test_error=' . urlencode($error_msg)));
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
	public static function render_admin_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		// Handle form submission.
		if (isset($_POST['pmpro_ml_save_settings'])) {
			// Verify user has permission.
			if (!current_user_can('manage_options')) {
				wp_die(esc_html__('You do not have permission to perform this action.', 'pmpro-magic-levels'));
			}

			// Verify nonce.
			check_admin_referer('pmpro_ml_settings');

			// Validate and save.
			$webhook_enabled = isset($_POST['pmpro_ml_webhook_enabled']) && '1' === $_POST['pmpro_ml_webhook_enabled'] ? '1' : '0';
			update_option('pmpro_ml_webhook_enabled', $webhook_enabled);

			echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'pmpro-magic-levels') . '</p></div>';
		}

		$webhook_enabled = get_option('pmpro_ml_webhook_enabled', '0');
		$webhook_key = get_option('pmpro_ml_webhook_key');
		$regenerated = isset($_GET['regenerated']) ? true : false;
		$test_error = isset($_GET['test_error']) ? sanitize_text_field(wp_unslash($_GET['test_error'])) : '';
		?>
		<div class="wrap pmpro_admin">
			<h1><?php esc_html_e('PMPro Magic Levels', 'pmpro-magic-levels'); ?></h1>
			<hr class="wp-header-end">

			<?php if ($regenerated): ?>
				<div class="notice notice-warning is-dismissible">
					<p><strong><?php esc_html_e('Webhook URL regenerated!', 'pmpro-magic-levels'); ?></strong>
						<?php esc_html_e('The previous URL will no longer work. Update any forms or integrations using the old URL.', 'pmpro-magic-levels'); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if (!empty($test_error)): ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e('Test failed:', 'pmpro-magic-levels'); ?></strong>
						<?php echo esc_html($test_error); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('1' === $webhook_enabled): ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e('Security Recommendation:', 'pmpro-magic-levels'); ?></strong>
						<?php esc_html_e('For production sites, we recommend implementing rate limiting at your CDN or proxy level (Cloudflare, BunnyCDN, etc.) for better performance and security. The plugin includes basic rate limiting, but external solutions provide superior protection.', 'pmpro-magic-levels'); ?>
						<a href="https://github.com/YOUR_REPO/pmpro-magic-levels/blob/master/docs/security.md" target="_blank">
							<?php esc_html_e('View security guide →', 'pmpro-magic-levels'); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field('pmpro_ml_settings'); ?>

				<!-- Webhook Endpoint Section -->
				<div class="pmpro_section" data-visibility="shown" data-activated="true">
					<div class="pmpro_section_toggle">
						<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
							<span class="dashicons dashicons-arrow-up-alt2"></span>
							<?php esc_html_e('Webhook Endpoint', 'pmpro-magic-levels'); ?>
						</button>
					</div>
					<div class="pmpro_section_inside">
						<p><?php esc_html_e('The Webhook Endpoint allows external services and forms to create membership levels via HTTP POST requests. All requests are secured with Bearer token authentication using a cryptographically secure 64-character key.', 'pmpro-magic-levels'); ?>
						</p>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e('Enable Webhook Endpoint', 'pmpro-magic-levels'); ?></th>
								<td>
									<label>
										<input type="checkbox" name="pmpro_ml_webhook_enabled" value="1" <?php checked($webhook_enabled, '1'); ?>>
										<?php esc_html_e('Enable webhook endpoint', 'pmpro-magic-levels'); ?>
									</label>
									<p class="description">
										<?php esc_html_e('When disabled, the webhook URL will return a 403 error.', 'pmpro-magic-levels'); ?>
									</p>
								</td>
							</tr>

							<?php if ('1' === $webhook_enabled): ?>
								<tr>
									<th scope="row"><?php esc_html_e('Webhook URL', 'pmpro-magic-levels'); ?></th>
									<td>
										<input type="text" readonly
											value="<?php echo esc_attr(rest_url('pmpro-magic-levels/v1/process')); ?>"
											class="large-text code" onclick="this.select();">
										<p class="description">
											<?php esc_html_e('Send POST requests to this endpoint with Bearer token authentication.', 'pmpro-magic-levels'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row"><?php esc_html_e('Bearer Token', 'pmpro-magic-levels'); ?></th>
									<td>
										<input type="text" readonly value="<?php echo esc_attr(self::get_webhook_key()); ?>"
											class="large-text code" onclick="this.select();" style="font-family: monospace;">
										<p class="description">
											<?php esc_html_e('Token only (for curl or code integrations)', 'pmpro-magic-levels'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row"><?php esc_html_e('Authorization Header Value', 'pmpro-magic-levels'); ?></th>
									<td>
										<input type="text" readonly value="Bearer <?php echo esc_attr(self::get_webhook_key()); ?>"
											class="large-text code" onclick="this.select();" style="font-family: monospace;">
										<p class="description">
											<?php esc_html_e('Full header value (for form plugins).', 'pmpro-magic-levels'); ?>
											<strong><?php esc_html_e('This is the same token as above, just formatted differently.', 'pmpro-magic-levels'); ?></strong>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row"><?php esc_html_e('Regenerate Security Key', 'pmpro-magic-levels'); ?></th>
									<td>
										<a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=pmpro_ml_regenerate_key'), 'pmpro_ml_regenerate_key'); ?>"
											class="button"
											onclick="return confirm('<?php echo esc_js(__('Are you sure? The current webhook URL will stop working and you will need to update all integrations.', 'pmpro-magic-levels')); ?>');"><?php esc_html_e('Regenerate Key', 'pmpro-magic-levels'); ?></a>
										<p class="description">
											<?php esc_html_e('Generate a new security key. This will invalidate the current webhook URL.', 'pmpro-magic-levels'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row"><?php esc_html_e('Test Webhook', 'pmpro-magic-levels'); ?></th>
									<td>
										<a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=pmpro_ml_test_webhook'), 'pmpro_ml_test_webhook'); ?>"
											class="button button-secondary"><?php esc_html_e('Create Test Level', 'pmpro-magic-levels'); ?></a>
										<p class="description">
											<?php esc_html_e('Creates a test membership level with random data. You\'ll be redirected to edit the created level.', 'pmpro-magic-levels'); ?>
										</p>
									</td>
								</tr>
							<?php endif; ?>
						</table>

						<p class="submit">
							<input type="submit" name="pmpro_ml_save_settings" class="button button-primary"
								value="<?php esc_attr_e('Save Settings', 'pmpro-magic-levels'); ?>">
						</p>
					</div>
				</div>

			</form>

			<!-- API Parameters Section -->
			<div class="pmpro_section" data-visibility="shown" data-activated="true">
				<div class="pmpro_section_toggle">
					<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
						<span class="dashicons dashicons-arrow-up-alt2"></span>
						<?php esc_html_e('API Parameters', 'pmpro-magic-levels'); ?>
					</button>
				</div>
				<div class="pmpro_section_inside">
					<p><?php esc_html_e('Send a POST request to the webhook URL with JSON data containing these parameters:', 'pmpro-magic-levels'); ?>
					</p>

					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width: 200px;"><?php esc_html_e('Parameter', 'pmpro-magic-levels'); ?></th>
								<th style="width: 100px;"><?php esc_html_e('Type', 'pmpro-magic-levels'); ?></th>
								<th style="width: 80px;"><?php esc_html_e('Required', 'pmpro-magic-levels'); ?></th>
								<th><?php esc_html_e('Description', 'pmpro-magic-levels'); ?></th>
							</tr>
						</thead>
						<tbody>
							<!-- General Information -->
							<tr>
								<td colspan="4" style="background: #f0f0f1; font-weight: bold; padding: 8px;">
									<?php esc_html_e('General Information', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="name" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 100px;">
								</td>
								<td>string</td>
								<td><strong style="color: #d63638;">Required</strong></td>
								<td><?php esc_html_e('Level name in format "GroupName - LevelName". Example: "Premium - Gold Monthly"', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="description" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 150px;">
								</td>
								<td>string</td>
								<td>Optional</td>
								<td><?php esc_html_e('Level description. Example: "Access to premium content and features"', 'pmpro-magic-levels'); ?></td>
							</tr>
							<tr>
								<td><input type="text" readonly value="confirmation" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 150px;">
								</td>
								<td>string</td>
								<td>Optional</td>
								<td><?php esc_html_e('Confirmation message shown after checkout. Example: "Thank you for joining!"', 'pmpro-magic-levels'); ?></td>
							</tr>
							<tr>
								<td><input type="text" readonly value="account_message" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 150px;">
								</td>
								<td>string</td>
								<td>Optional</td>
								<td><?php esc_html_e('Message shown on member account page. Example: "Your benefits: Free shipping, 20% discount"', 'pmpro-magic-levels'); ?>
								</td>
							</tr>

							<!-- Billing Details -->
							<tr>
								<td colspan="4" style="background: #f0f0f1; font-weight: bold; padding: 8px;">
									<?php esc_html_e('Billing Details', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="billing_amount" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 150px;">
								</td>
								<td>float</td>
								<td><strong style="color: #d63638;">Required</strong></td>
								<td><?php esc_html_e('Recurring billing amount. Example: 29.99', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="initial_payment" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 150px;">
								</td>
								<td>float</td>
								<td>Defaults to 0</td>
								<td><?php esc_html_e('One-time initial payment. Example: 49.99', 'pmpro-magic-levels'); ?></td>
							</tr>
							<tr>
								<td><input type="text" readonly value="cycle_number" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 130px;">
								</td>
								<td>integer</td>
								<td>Defaults to 0</td>
								<td><?php esc_html_e('Billing cycle number. Example: 1 (bill every 1 period), 3 (bill every 3 periods)', 'pmpro-magic-levels'); ?></td>
							</tr>
							<tr>
								<td><input type="text" readonly value="cycle_period" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 130px;">
								</td>
								<td>string</td>
								<td>Optional</td>
								<td><?php esc_html_e('Billing period. Example: "Month", "Year", "Week", "Day"', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="billing_limit" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 130px;">
								</td>
								<td>integer</td>
								<td>Defaults to 0</td>
								<td><?php esc_html_e('Number of billing cycles before expiration. Example: 12 (12 payments then cancel). 0 = unlimited', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="trial_amount" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 130px;">
								</td>
								<td>float</td>
								<td>Defaults to 0</td>
								<td><?php esc_html_e('Trial period amount. Example: 1.00 (charge $1 for trial)', 'pmpro-magic-levels'); ?></td>
							</tr>
							<tr>
								<td><input type="text" readonly value="trial_limit" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 120px;">
								</td>
								<td>integer</td>
								<td>Defaults to 0</td>
								<td><?php esc_html_e('Trial period duration in billing cycles. Example: 1 (1 month trial if cycle_period is Month)', 'pmpro-magic-levels'); ?></td>
							</tr>

							<!-- Expiration Settings -->
							<tr>
								<td colspan="4" style="background: #f0f0f1; font-weight: bold; padding: 8px;">
									<?php esc_html_e('Expiration Settings', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="expiration_number" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 170px;">
								</td>
								<td>integer</td>
								<td>Defaults to 0</td>
								<td><?php esc_html_e('Expiration duration. Example: 12 (expires after 12 periods). 0 = no expiration', 'pmpro-magic-levels'); ?></td>
							</tr>
							<tr>
								<td><input type="text" readonly value="expiration_period" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 170px;">
								</td>
								<td>string</td>
								<td>Optional</td>
								<td><?php esc_html_e('Expiration period. Example: "Month", "Year", "Week", "Day"', 'pmpro-magic-levels'); ?>
								</td>
							</tr>

							<!-- Content Settings -->
							<tr>
								<td colspan="4" style="background: #f0f0f1; font-weight: bold; padding: 8px;">
									<?php esc_html_e('Content Settings', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="protected_categories" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 200px;">
								</td>
								<td>array</td>
								<td>Optional</td>
								<td><?php esc_html_e('Array of category/tag IDs to protect. Example: [5, 12, 18]', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="protected_pages" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 170px;">
								</td>
								<td>array</td>
								<td>Optional</td>
								<td><?php esc_html_e('Array of page IDs to protect. Example: [42, 67, 89]', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="protected_posts" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 170px;">
								</td>
								<td>array</td>
								<td>Optional</td>
								<td><?php esc_html_e('Array of post IDs to protect. Example: [123, 456, 789]', 'pmpro-magic-levels'); ?>
								</td>
							</tr>

							<!-- Other Settings -->
							<tr>
								<td colspan="4" style="background: #f0f0f1; font-weight: bold; padding: 8px;">
									<?php esc_html_e('Other Settings', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="allow_signups" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 150px;">
								</td>
								<td>integer</td>
								<td>Defaults to 1</td>
								<td><?php esc_html_e('Allow signups for this level. Example: 1 (enabled), 0 (disabled)', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
						</tbody>
					</table>
					<p class="description">
						<strong><?php esc_html_e('Note:', 'pmpro-magic-levels'); ?></strong>
						<?php esc_html_e('Content protection is additive - if a page/post is already protected by other levels, this level will be added to the existing restrictions.', 'pmpro-magic-levels'); ?>
					</p>
				</div>
			</div>

			<!-- Validation Rules Section -->
			<div class="pmpro_section" data-visibility="shown" data-activated="true">
				<div class="pmpro_section_toggle">
					<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
						<span class="dashicons dashicons-arrow-up-alt2"></span>
						<?php esc_html_e('Validation Rules', 'pmpro-magic-levels'); ?>
					</button>
				</div>
				<div class="pmpro_section_inside">
					<p><?php esc_html_e('The following validation rules are applied to all level creation requests (Webhook Endpoint and custom integrations):', 'pmpro-magic-levels'); ?>
					</p>

					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width: 250px;"><?php esc_html_e('Rule', 'pmpro-magic-levels'); ?></th>
								<th style="width: 200px;"><?php esc_html_e('Default Value', 'pmpro-magic-levels'); ?></th>
								<th><?php esc_html_e('Filter', 'pmpro-magic-levels'); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php esc_html_e('Allowed Periods', 'pmpro-magic-levels'); ?></td>
								<td><?php esc_html_e('Day, Week, Month, Year', 'pmpro-magic-levels'); ?></td>
								<td><input type="text" readonly value="pmpro_magic_levels_allowed_periods"
										onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 300px;">
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e('Allowed Cycle Numbers', 'pmpro-magic-levels'); ?></td>
								<td>1, 2, 3, 6, 12</td>
								<td><input type="text" readonly value="pmpro_magic_levels_allowed_cycle_numbers"
										onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 350px;">
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e('Min Name Length', 'pmpro-magic-levels'); ?></td>
								<td>1</td>
								<td><input type="text" readonly value="pmpro_magic_levels_min_name_length"
										onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 300px;">
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e('Max Name Length', 'pmpro-magic-levels'); ?></td>
								<td>255</td>
								<td><input type="text" readonly value="pmpro_magic_levels_max_name_length"
										onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 300px;">
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e('Rate Limit (requests/hour)', 'pmpro-magic-levels'); ?></td>
								<td>100</td>
								<td><input type="text" readonly value="pmpro_magic_levels_rate_limit" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 250px;">
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e('Max Levels Per Day', 'pmpro-magic-levels'); ?></td>
								<td>1000</td>
								<td><input type="text" readonly value="pmpro_magic_levels_max_levels_per_day"
										onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: 100%; min-width: 300px;">
								</td>
							</tr>
						</tbody>
					</table>

					<p class="description">
						<?php esc_html_e('Use these filters in your theme or plugin to customize validation rules.', 'pmpro-magic-levels'); ?>
						<a href="https://github.com/YOUR_REPO/pmpro-magic-levels/blob/master/docs/README.md#advanced-validation"
							target="_blank"><?php esc_html_e('View advanced validation examples →', 'pmpro-magic-levels'); ?></a>
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
	public static function add_site_health_info($info)
	{
		$webhook_enabled = get_option('pmpro_ml_webhook_enabled', '0');
		$webhook_key = get_option('pmpro_ml_webhook_key');

		$info['pmpro-magic-levels'] = array(
			'label' => __('PMPro Magic Levels', 'pmpro-magic-levels'),
			'fields' => array(
				'webhook_status' => array(
					'label' => __('Webhook Endpoint', 'pmpro-magic-levels'),
					'value' => '1' === $webhook_enabled ? __('Enabled', 'pmpro-magic-levels') : __('Disabled', 'pmpro-magic-levels'),
				),
				'webhook_key_set' => array(
					'label' => __('Security Key', 'pmpro-magic-levels'),
					'value' => !empty($webhook_key) ? __('Configured', 'pmpro-magic-levels') : __('Not Set', 'pmpro-magic-levels'),
				),
				'rest_api_enabled' => array(
					'label' => __('REST API', 'pmpro-magic-levels'),
					'value' => get_option('permalink_structure') ? __('Available', 'pmpro-magic-levels') : __('Requires Pretty Permalinks', 'pmpro-magic-levels'),
				),
				'pmpro_version' => array(
					'label' => __('PMPro Version', 'pmpro-magic-levels'),
					'value' => defined('PMPRO_VERSION') ? PMPRO_VERSION : __('Not Detected', 'pmpro-magic-levels'),
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
	public static function add_site_health_tests($tests)
	{
		$tests['direct']['pmpro_magic_levels_rest_api'] = array(
			'label' => __('PMPro Magic Levels REST API', 'pmpro-magic-levels'),
			'test' => array(__CLASS__, 'test_rest_api'),
		);

		$tests['direct']['pmpro_magic_levels_webhook'] = array(
			'label' => __('PMPro Magic Levels Webhook', 'pmpro-magic-levels'),
			'test' => array(__CLASS__, 'test_webhook_config'),
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
	public static function test_rest_api()
	{
		$result = array(
			'label' => __('REST API is available', 'pmpro-magic-levels'),
			'status' => 'good',
			'badge' => array(
				'label' => __('PMPro Magic Levels', 'pmpro-magic-levels'),
				'color' => 'blue',
			),
			'description' => sprintf('<p>%s</p>', __('The WordPress REST API is properly configured and accessible.', 'pmpro-magic-levels')),
			'test' => 'pmpro_magic_levels_rest_api',
		);

		// Check if pretty permalinks are enabled.
		if (!get_option('permalink_structure')) {
			$result['status'] = 'critical';
			$result['label'] = __('REST API requires pretty permalinks', 'pmpro-magic-levels');
			$result['description'] = sprintf('<p>%s</p>', __('PMPro Magic Levels requires pretty permalinks to be enabled for the REST API to work. Go to Settings > Permalinks and select any option other than "Plain".', 'pmpro-magic-levels'));
			return $result;
		}

		// Test if REST API is accessible.
		$response = wp_remote_get(rest_url('pmpro-magic-levels/v1/process'));

		if (is_wp_error($response)) {
			$result['status'] = 'critical';
			$result['label'] = __('REST API is not accessible', 'pmpro-magic-levels');
			$result['description'] = sprintf('<p>%s %s</p>', __('The REST API endpoint could not be reached. Error:', 'pmpro-magic-levels'), esc_html($response->get_error_message()));
			return $result;
		}

		$status_code = wp_remote_retrieve_response_code($response);

		// 403 is expected if webhook is disabled or no key provided.
		if (403 === $status_code || 405 === $status_code) {
			$result['description'] = sprintf('<p>%s</p>', __('The REST API endpoint is accessible and properly secured.', 'pmpro-magic-levels'));
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
	public static function test_webhook_config()
	{
		$webhook_enabled = get_option('pmpro_ml_webhook_enabled', '0');
		$webhook_key = get_option('pmpro_ml_webhook_key');

		$result = array(
			'label' => __('Webhook Endpoint is configured', 'pmpro-magic-levels'),
			'status' => 'good',
			'badge' => array(
				'label' => __('PMPro Magic Levels', 'pmpro-magic-levels'),
				'color' => 'blue',
			),
			'description' => sprintf('<p>%s</p>', __('The Webhook Endpoint is properly configured and ready to use.', 'pmpro-magic-levels')),
			'test' => 'pmpro_magic_levels_webhook',
		);

		if ('1' !== $webhook_enabled) {
			$result['status'] = 'recommended';
			$result['label'] = __('Webhook Endpoint is disabled', 'pmpro-magic-levels');
			$result['description'] = sprintf('<p>%s <a href="' . admin_url('admin.php?page=pmpro-magic-levels') . '">%s</a></p>', __('The Webhook Endpoint is currently disabled. If you plan to use external forms or services, enable it in', 'pmpro-magic-levels'), __('PMPro > Magic Levels', 'pmpro-magic-levels'));
			return $result;
		}

		if (empty($webhook_key)) {
			$result['status'] = 'critical';
			$result['label'] = __('Webhook security key is missing', 'pmpro-magic-levels');
			$result['description'] = sprintf('<p>%s <a href="' . admin_url('admin.php?page=pmpro-magic-levels') . '">%s</a></p>', __('The webhook is enabled but no security key is configured. Visit', 'pmpro-magic-levels'), __('PMPro > Magic Levels to generate a key.', 'pmpro-magic-levels'));
			return $result;
		}

		return $result;
	}
}
