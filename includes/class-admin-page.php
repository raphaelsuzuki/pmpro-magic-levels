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
		add_action('admin_post_pmpro_ml_test_webhook', array(__CLASS__, 'test_webhook'));
		add_action('admin_post_pmpro_ml_create_token', array(__CLASS__, 'create_token'));
		add_action('admin_post_pmpro_ml_revoke_token', array(__CLASS__, 'revoke_token'));
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
	}




	/**
	 * Create a new additional token.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function create_token()
	{
		check_admin_referer('pmpro_ml_create_token');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'pmpro-magic-levels'));
		}

		$name = isset($_POST['token_name']) ? sanitize_text_field($_POST['token_name']) : '';

		if (empty($name)) {
			wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&token_error=' . urlencode(__('Token name is required', 'pmpro-magic-levels'))));
			exit;
		}

		if (class_exists('PMPRO_Magic_Levels_Token_Manager')) {
			$result = PMPRO_Magic_Levels_Token_Manager::create_token($name);

			if (is_wp_error($result)) {
				wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&token_error=' . urlencode($result->get_error_message())));
			} else {
				// Redirect with the fresh token to show once.
				set_transient('pmpro_ml_new_token_' . get_current_user_id(), $result, 60);
				wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&token_created=1'));
			}
		} else {
			wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&token_error=' . urlencode(__('Token Manager not loaded', 'pmpro-magic-levels'))));
		}
		exit;
	}

	/**
	 * Revoke an additional token.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function revoke_token()
	{
		check_admin_referer('pmpro_ml_revoke_token');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'pmpro-magic-levels'));
		}

		$token_id = isset($_POST['token_id']) ? sanitize_text_field($_POST['token_id']) : '';

		if (class_exists('PMPRO_Magic_Levels_Token_Manager')) {
			if (PMPRO_Magic_Levels_Token_Manager::revoke_token($token_id)) {
				wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&token_revoked=1'));
			} else {
				wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&token_error=' . urlencode(__('Failed to revoke token', 'pmpro-magic-levels'))));
			}
		}
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

		// Generate temporary token for testing.
		$token_data = PMPRO_Magic_Levels_Token_Manager::create_token('System Self-Test - ' . current_time('mysql'));

		if (is_wp_error($token_data)) {
			wp_redirect(admin_url('admin.php?page=pmpro-magic-levels&test_error=' . urlencode($token_data->get_error_message())));
			exit;
		}

		$webhook_token = $token_data['token'];
		$webhook_token_id = $token_data['id'];

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
			rest_url('pmpro-magic-levels/v1/create-level'),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $webhook_token,
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
			// Revoke the test token.
			PMPRO_Magic_Levels_Token_Manager::revoke_token($webhook_token_id);
			wp_redirect(admin_url('admin.php?page=pmpro-membershiplevels&edit=' . $result['level_id'] . '&test_created=1'));
		} else {
			// Revoke the test token.
			PMPRO_Magic_Levels_Token_Manager::revoke_token($webhook_token_id);
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
		// Removed legacy key fetch.
		$test_error = isset($_GET['test_error']) ? sanitize_text_field(wp_unslash($_GET['test_error'])) : '';
		?>
		<div class="wrap pmpro_admin">
			<h1><?php esc_html_e('PMPro Magic Levels', 'pmpro-magic-levels'); ?></h1>
			<hr class="wp-header-end">


			<?php if (isset($_GET['test_success'])): ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e('Webhook Test Successful!', 'pmpro-magic-levels'); ?></strong>
						<?php esc_html_e('A test membership level was created. You can safely delete it.', 'pmpro-magic-levels'); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if (!empty($test_error)): ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e('Test failed:', 'pmpro-magic-levels'); ?></strong>
						<?php echo esc_html($test_error); ?></p>
				</div>
			<?php endif; ?>

			<?php
			if (isset($_GET['token_created']) && $_GET['token_created']) {
				$new_token_data = get_transient('pmpro_ml_new_token_' . get_current_user_id());
				if ($new_token_data) {
					delete_transient('pmpro_ml_new_token_' . get_current_user_id());
					?>
					<div class="notice notice-success is-dismissible">
						<p><strong><?php esc_html_e('New Token Created!', 'pmpro-magic-levels'); ?></strong></p>
						<p>
							<?php esc_html_e('Make sure to copy your personal access token now. You won\'t be able to see it again!', 'pmpro-magic-levels'); ?>
						</p>
						<p>
							<input type="text" readonly value="<?php echo esc_attr($new_token_data['token']); ?>"
								class="large-text code" onclick="this.select();" style="font-weight: bold; color: #007cba;">
						</p>
					</div>
					<?php
				}
			}

			if (isset($_GET['token_revoked'])) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Token revoked successfully.', 'pmpro-magic-levels'); ?></p>
				</div>
				<?php
			}

			if (isset($_GET['token_error'])) {
				?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html(urldecode($_GET['token_error'])); ?></p>
				</div>
				<?php
			}
			?>


			<?php if (class_exists('PMPRO_Magic_Levels_Token_Manager')): ?>
				<!-- Token Manager Section -->
				<div class="pmpro_section" data-visibility="shown" data-activated="true">
					<div class="pmpro_section_toggle">
						<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
							<span class="dashicons dashicons-arrow-up-alt2"></span>
							<?php esc_html_e('Access Tokens', 'pmpro-magic-levels'); ?>
						</button>
					</div>
					<div class="pmpro_section_inside">
						<p>
							<?php esc_html_e('The Webhook Endpoint allows external services and forms to create membership levels via HTTP POST requests. All requests are secured with Bearer token authentication using a cryptographically secure 64-character key.', 'pmpro-magic-levels'); ?>
						</p>

						<div class="notice notice-info inline">
							<p>
								<strong><?php esc_html_e('Webhook Endpoint URL:', 'pmpro-magic-levels'); ?></strong><br>
								<input type="text" readonly
									value="<?php echo esc_url(rest_url('pmpro-magic-levels/v1/create-level')); ?>"
									class="large-text code" onclick="this.select();">
							</p>
						</div>
						<p><?php esc_html_e('Create specific tokens for different integrations (e.g., Zapier, Custom Forms). You can revoke these individually.', 'pmpro-magic-levels'); ?>
						</p>

						<?php
						$tokens = PMPRO_Magic_Levels_Token_Manager::get_tokens();
						?>

						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e('Name', 'pmpro-magic-levels'); ?></th>
									<th><?php esc_html_e('Created', 'pmpro-magic-levels'); ?></th>
									<th><?php esc_html_e('Last Used', 'pmpro-magic-levels'); ?></th>
									<th><?php esc_html_e('Actions', 'pmpro-magic-levels'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($tokens)): ?>
									<tr>
										<td colspan="4"><?php esc_html_e('No additional tokens found.', 'pmpro-magic-levels'); ?></td>
									</tr>
								<?php else: ?>
									<?php foreach ($tokens as $id => $token): ?>
										<tr>
											<td><strong><?php echo esc_html($token['name']); ?></strong></td>
											<td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($token['created']))); ?>
											</td>
											<td>
												<?php
												if (!empty($token['last_used'])) {
													echo esc_html(human_time_diff(strtotime($token['last_used']), current_time('timestamp')) . ' ago');
												} else {
													echo esc_html__('Never', 'pmpro-magic-levels');
												}
												?>
											</td>
											<td>
												<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
													style="display:inline;">
													<input type="hidden" name="action" value="pmpro_ml_revoke_token">
													<input type="hidden" name="token_id" value="<?php echo esc_attr($id); ?>">
													<?php wp_nonce_field('pmpro_ml_revoke_token'); ?>
													<button type="submit" class="button-link button-link-delete"
														style="color: #a00; text-decoration: none;"
														onclick="return confirm('<?php echo esc_js(__('Are you sure you want to revoke this token?', 'pmpro-magic-levels')); ?>');">
														<?php esc_html_e('Revoke', 'pmpro-magic-levels'); ?>
													</button>
												</form>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
						<p class="description">
							<strong><?php esc_html_e('Note:', 'pmpro-magic-levels'); ?></strong>
							<?php esc_html_e('Authentication header required: ', 'pmpro-magic-levels'); ?>
							<code>Authorization: Bearer &lt;token&gt;</code>
						</p>

						<h4><?php esc_html_e('Create New Token', 'pmpro-magic-levels'); ?></h4>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pmpro_form">
							<input type="hidden" name="action" value="pmpro_ml_create_token">
							<?php wp_nonce_field('pmpro_ml_create_token'); ?>
							<input type="text" name="token_name"
								placeholder="<?php esc_attr_e('e.g. Zapier Integration', 'pmpro-magic-levels'); ?>" required
								class="regular-text">
							<button type="submit"
								class="button button-secondary"><?php esc_html_e('Generate Token', 'pmpro-magic-levels'); ?></button>
						</form>

						<h4><?php esc_html_e('Test Webhook', 'pmpro-magic-levels'); ?></h4>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
							<input type="hidden" name="action" value="pmpro_ml_test_webhook">
							<?php wp_nonce_field('pmpro_ml_test_webhook'); ?>
							<p class="description">
								<?php esc_html_e('Performs a genuine HTTP request to verify API reachability and Authorization headers by creating a test level with all parameters using a temporary token.', 'pmpro-magic-levels'); ?>
							</p>

							<button type="submit"
								class="button button-secondary"><?php esc_html_e('Run System Self-Test', 'pmpro-magic-levels'); ?></button>
						</form>

					</div>
				</div>
			<?php endif; ?>

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
								<td><?php esc_html_e('Level description. Example: "Access to premium content and features"', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="confirmation" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 150px;">
								</td>
								<td>string</td>
								<td>Optional</td>
								<td><?php esc_html_e('Confirmation message shown after checkout. Example: "Thank you for joining!"', 'pmpro-magic-levels'); ?>
								</td>
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
								<td><?php esc_html_e('Billing cycle number. Example: 1 (bill every 1 period), 3 (bill every 3 periods)', 'pmpro-magic-levels'); ?>
								</td>
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
								<td><?php esc_html_e('Trial period amount. Example: 1.00 (charge $1 for trial)', 'pmpro-magic-levels'); ?>
								</td>
							</tr>
							<tr>
								<td><input type="text" readonly value="trial_limit" onclick="this.select();"
										style="border: none; background: transparent; font-family: monospace; width: auto; min-width: 120px;">
								</td>
								<td>integer</td>
								<td>Defaults to 0</td>
								<td><?php esc_html_e('Trial period duration in billing cycles. Example: 1 (1 month trial if cycle_period is Month)', 'pmpro-magic-levels'); ?>
								</td>
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
								<td><?php esc_html_e('Expiration duration. Example: 12 (expires after 12 periods). 0 = no expiration', 'pmpro-magic-levels'); ?>
								</td>
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
		$info['pmpro-magic-levels'] = array(
			'label' => __('PMPro Magic Levels', 'pmpro-magic-levels'),
			'fields' => array(
				'webhook_key_set' => array(
					'label' => __('Active Tokens', 'pmpro-magic-levels'),
					'value' => !empty(PMPRO_Magic_Levels_Token_Manager::get_tokens()) ? count(PMPRO_Magic_Levels_Token_Manager::get_tokens()) . ' ' . __('configured', 'pmpro-magic-levels') : __('No tokens', 'pmpro-magic-levels'),
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
		$response = wp_remote_get(rest_url('pmpro-magic-levels/v1/create-level'));

		if (is_wp_error($response)) {
			$result['status'] = 'critical';
			$result['label'] = __('REST API is not accessible', 'pmpro-magic-levels');
			$result['description'] = sprintf('<p>%s %s</p>', __('The REST API endpoint could not be reached. Error:', 'pmpro-magic-levels'), esc_html($response->get_error_message()));
			return $result;
		}

		$status_code = wp_remote_retrieve_response_code($response);

		// 405 is expected (Method Not Allowed) since we are sending GET but route expects POST.
		// 401 is expected if the route requires authentication even for GET (some security plugins).
		if (401 === $status_code || 403 === $status_code || 405 === $status_code) {
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
		$result = array(
			'label' => __('Webhook Endpoint is configured', 'pmpro-magic-levels'),
			'status' => 'good',
			'badge' => array(
				'label' => __('PMPro Magic Levels', 'pmpro-magic-levels'),
				'color' => 'blue',
			),
			'description' => sprintf('<p>%s</p>', __('The Webhook Endpoint is properly configured with access tokens.', 'pmpro-magic-levels')),
			'test' => 'pmpro_magic_levels_webhook',
		);

		if (empty(PMPRO_Magic_Levels_Token_Manager::get_tokens())) {
			$result['status'] = 'recommended';
			$result['label'] = __('No access tokens configured', 'pmpro-magic-levels');
			$result['description'] = sprintf('<p>%s <a href="' . admin_url('admin.php?page=pmpro-magic-levels') . '">%s</a></p>', __('The Webhook Endpoint is active but no access tokens are configured. Visit', 'pmpro-magic-levels'), __('PMPro > Magic Levels to generate a token.', 'pmpro-magic-levels'));
			return $result;
		}

		return $result;
	}
}
