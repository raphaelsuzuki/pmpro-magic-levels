<?php
/**
 * Level Matcher - Find or create membership levels.
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * PMPRO_Magic_Levels_Level_Matcher class.
 *
 * Handles finding existing levels or creating new ones based on parameters.
 *
 * @since 1.0.0
 */
class PMPRO_Magic_Levels_Level_Matcher
{

	/**
	 * Find or create a level based on parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Level parameters.
	 * @return array Result with success, level_id, and other metadata.
	 */
	public function find_or_create($params)
	{
		// Generate cache key.
		$cache_key = PMPRO_Magic_Levels_Cache::generate_key($params);

		// Check cache.
		$cached_level_id = PMPRO_Magic_Levels_Cache::get($cache_key);

		if (false !== $cached_level_id) {
			return array(
				'success' => true,
				'level_id' => $cached_level_id,
				'level_created' => false,
				'cached' => true,
			);
		}

		// Search database for matching level.
		$level_id = $this->find_matching_level($params);

		if ($level_id) {
			// Cache the found level.
			PMPRO_Magic_Levels_Cache::set($cache_key, $level_id);

			return array(
				'success' => true,
				'level_id' => $level_id,
				'level_created' => false,
				'cached' => false,
			);
		}

		// Create new level.
		$new_level_id = $this->create_level($params);

		if (!$new_level_id) {
			return array(
				'success' => false,
				'error' => 'Failed to create level',
				'code' => 'level_creation_failed',
			);
		}

		// Cache the new level.
		PMPRO_Magic_Levels_Cache::set($cache_key, $new_level_id);

		// Increment daily counter.
		$validator = new PMPRO_Magic_Levels_Validator();
		$validator->increment_daily_counter();

		return array(
			'success' => true,
			'level_id' => $new_level_id,
			'level_created' => true,
			'cached' => false,
		);
	}

	/**
	 * Find matching level in database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Level parameters.
	 * @return int|null Level ID if found, null otherwise.
	 */
	private function find_matching_level($params)
	{
		global $wpdb;

		// Build WHERE clause for exact match.
		$where_conditions = array('1=1');
		$where_values = array();

		// Name (required).
		$where_conditions[] = 'name = %s';
		$where_values[] = $params['name'];

		// Billing amount.
		$billing_amount = isset($params['billing_amount']) ? floatval($params['billing_amount']) : 0;
		$where_conditions[] = 'billing_amount = %f';
		$where_values[] = $billing_amount;

		// Cycle number.
		$cycle_number = isset($params['cycle_number']) ? intval($params['cycle_number']) : 0;
		$where_conditions[] = 'cycle_number = %d';
		$where_values[] = $cycle_number;

		// Cycle period.
		$cycle_period = isset($params['cycle_period']) ? $params['cycle_period'] : '';
		$where_conditions[] = 'cycle_period = %s';
		$where_values[] = $cycle_period;

		// Initial payment.
		$initial_payment = isset($params['initial_payment']) ? floatval($params['initial_payment']) : 0;
		$where_conditions[] = 'initial_payment = %f';
		$where_values[] = $initial_payment;

		// Trial amount.
		$trial_amount = isset($params['trial_amount']) ? floatval($params['trial_amount']) : 0;
		$where_conditions[] = 'trial_amount = %f';
		$where_values[] = $trial_amount;

		// Trial limit.
		$trial_limit = isset($params['trial_limit']) ? intval($params['trial_limit']) : 0;
		$where_conditions[] = 'trial_limit = %d';
		$where_values[] = $trial_limit;

		// Billing limit.
		$billing_limit = isset($params['billing_limit']) ? intval($params['billing_limit']) : 0;
		$where_conditions[] = 'billing_limit = %d';
		$where_values[] = $billing_limit;

		// Expiration number.
		$expiration_number = isset($params['expiration_number']) ? intval($params['expiration_number']) : 0;
		$where_conditions[] = 'expiration_number = %d';
		$where_values[] = $expiration_number;

		// Expiration period.
		$expiration_period = isset($params['expiration_period']) ? $params['expiration_period'] : '';
		$where_conditions[] = 'expiration_period = %s';
		$where_values[] = $expiration_period;

		// Build query.
		$where_clause = implode(' AND ', $where_conditions);
		$query = "SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE {$where_clause} LIMIT 1";

		// Execute query.
		$level_id = $wpdb->get_var($wpdb->prepare($query, $where_values)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $level_id ? intval($level_id) : null;
	}

	/**
	 * Create new level.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Level parameters.
	 * @return int|null Level ID if created, null otherwise.
	 */
	private function create_level($params)
	{
		// Create level object.
		$level = new PMPro_Membership_Level();

		// Set required fields.
		$level->name = sanitize_text_field($params['name']);

		// Set optional fields.
		if (isset($params['description'])) {
			$level->description = sanitize_textarea_field($params['description']);
		}

		if (isset($params['confirmation'])) {
			$level->confirmation = sanitize_textarea_field($params['confirmation']);
		}

		$level->initial_payment = isset($params['initial_payment']) ? floatval($params['initial_payment']) : 0;
		$level->billing_amount = isset($params['billing_amount']) ? floatval($params['billing_amount']) : 0;
		$level->cycle_number = isset($params['cycle_number']) ? intval($params['cycle_number']) : 0;
		$level->cycle_period = isset($params['cycle_period']) ? sanitize_text_field($params['cycle_period']) : '';
		$level->billing_limit = isset($params['billing_limit']) ? intval($params['billing_limit']) : 0;
		$level->trial_amount = isset($params['trial_amount']) ? floatval($params['trial_amount']) : 0;
		$level->trial_limit = isset($params['trial_limit']) ? intval($params['trial_limit']) : 0;
		$level->expiration_number = isset($params['expiration_number']) ? intval($params['expiration_number']) : 0;
		$level->expiration_period = isset($params['expiration_period']) ? sanitize_text_field($params['expiration_period']) : '';
		$level->allow_signups = isset($params['allow_signups']) ? intval($params['allow_signups']) : 1;

		// Save level.
		$level->save();

		// Assign to level group (if applicable).
		if ($level->id) {
			$this->assign_level_group($level->id, $params);

			// Save account message (PMPro 3.0+).
			if (!empty($params['account_message']) && function_exists('update_pmpro_membership_level_meta')) {
				update_pmpro_membership_level_meta($level->id, 'membership_account_message', wp_kses_post($params['account_message']));
			}

			// Assign content protection.
			$this->assign_content_protection($level->id, $params);
		}

		// Return level ID.
		return $level->id ? intval($level->id) : null;
	}

	/**
	 * Assign content protection to a level.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $level_id Level ID.
	 * @param array $params   Level parameters.
	 * @return void
	 */
	private function assign_content_protection($level_id, $params)
	{
		// Assign protected categories.
		if (!empty($params['protected_categories']) && is_array($params['protected_categories'])) {
			$this->assign_protected_categories($level_id, $params['protected_categories']);
		}

		// Assign protected pages.
		if (!empty($params['protected_pages']) && is_array($params['protected_pages'])) {
			$this->assign_protected_pages($level_id, $params['protected_pages']);
		}

		// Assign protected posts.
		if (!empty($params['protected_posts']) && is_array($params['protected_posts'])) {
			$this->assign_protected_posts($level_id, $params['protected_posts']);
		}
	}

	/**
	 * Assign protected categories to a level.
	 *
	 * Uses PMPro's built-in function to assign categories.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $level_id     Level ID.
	 * @param array $category_ids Array of category IDs.
	 * @return void
	 */
	private function assign_protected_categories($level_id, $category_ids)
	{
		// Sanitize category IDs.
		$category_ids = array_map('intval', $category_ids);
		$category_ids = array_filter($category_ids, function ($id) {
			return $id > 0;
		});

		if (empty($category_ids)) {
			return;
		}

		// Use PMPro's built-in function.
		if (function_exists('pmpro_updateMembershipCategories')) {
			pmpro_updateMembershipCategories($level_id, $category_ids);
		}
	}

	/**
	 * Assign protected pages to a level.
	 *
	 * Adds this level to the page's existing restrictions (doesn't replace).
	 *
	 * @since 1.0.0
	 *
	 * @param int   $level_id Level ID.
	 * @param array $page_ids Array of page IDs.
	 * @return void
	 */
	private function assign_protected_pages($level_id, $page_ids)
	{
		global $wpdb;

		// Sanitize page IDs.
		$page_ids = array_map('intval', $page_ids);
		$page_ids = array_filter($page_ids, function ($id) {
			return $id > 0;
		});

		if (empty($page_ids)) {
			return;
		}

		foreach ($page_ids as $page_id) {
			// Get existing level restrictions for this page.
			$existing_levels = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT membership_id FROM {$wpdb->pmpro_memberships_pages} WHERE page_id = %d",
					$page_id
				)
			);

			// Add this level if not already present.
			if (!in_array($level_id, $existing_levels, true)) {
				$existing_levels[] = $level_id;
			}

			// Use PMPro's function if available (PMPro 3.1+).
			if (function_exists('pmpro_update_post_level_restrictions')) {
				pmpro_update_post_level_restrictions($page_id, $existing_levels);
			} else {
				// Fallback: Insert directly.
				$wpdb->insert(
					$wpdb->pmpro_memberships_pages,
					array(
						'membership_id' => $level_id,
						'page_id'       => $page_id,
					),
					array('%d', '%d')
				);
			}
		}
	}

	/**
	 * Assign protected posts to a level.
	 *
	 * Adds this level to the post's existing restrictions (doesn't replace).
	 *
	 * @since 1.0.0
	 *
	 * @param int   $level_id Level ID.
	 * @param array $post_ids Array of post IDs.
	 * @return void
	 */
	private function assign_protected_posts($level_id, $post_ids)
	{
		// Posts and pages use the same table, so we can reuse the logic.
		$this->assign_protected_pages($level_id, $post_ids);
	}

	/**
	 * Assign level to a group based on name prefix.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $level_id Level ID.
	 * @param array $params   Level parameters.
	 * @return void
	 */
	private function assign_level_group($level_id, $params)
	{
		global $wpdb;

		// Check if level groups tables exist (PMPro 3.0+).
		$groups_table = $wpdb->prefix . 'pmpro_groups';
		$groups_rel_table = $wpdb->prefix . 'pmpro_membership_levels_groups';

		// Check if tables exist.
		$groups_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $groups_table)) === $groups_table;
		$rel_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $groups_rel_table)) === $groups_rel_table;

		if (!$groups_exists || !$rel_exists) {
			return; // Tables don't exist, skip.
		}

		// Extract group name from level name.
		$group_name = $this->extract_group_name($params['name']);

		// Allow filtering of group name.
		$group_name = apply_filters('pmpro_magic_levels_group_name', $group_name, $params);

		// Skip if no group name or disabled.
		if (empty($group_name)) {
			return;
		}

		// Find or create group.
		$group_id = $this->find_or_create_group($group_name);

		if (!$group_id) {
			return;
		}

		// Check if relationship already exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$groups_rel_table} WHERE `group` = %d AND `level` = %d",
				$group_id,
				$level_id
			)
		);

		if ($exists) {
			return; // Already assigned.
		}

		// Assign level to group.
		$wpdb->insert(
			$groups_rel_table,
			array(
				'group' => $group_id,
				'level' => $level_id,
			),
			array('%d', '%d')
		);
	}

	/**
	 * Extract group name from level name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $level_name Level name.
	 * @return string Group name or empty string.
	 */
	private function extract_group_name($level_name)
	{
		// Look for " - " separator.
		if (false !== strpos($level_name, ' - ')) {
			$parts = explode(' - ', $level_name, 2);
			return trim($parts[0]);
		}

		return ''; // No group.
	}

	/**
	 * Find or create a level group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group_name Group name.
	 * @return int|null Group ID if found/created, null otherwise.
	 */
	private function find_or_create_group($group_name)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'pmpro_groups';

		// Try to find existing group.
		$group_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE name = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$group_name
			)
		);

		if ($group_id) {
			return intval($group_id);
		}

		// Create new group.
		$wpdb->insert(
			$table_name,
			array('name' => $group_name),
			array('%s')
		);

		return $wpdb->insert_id ? intval($wpdb->insert_id) : null;
	}
}
