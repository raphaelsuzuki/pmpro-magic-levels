<?php
/**
 * Generic Form Integration Examples.
 *
 * Copy these examples to your theme's functions.php or custom plugin.
 *
 * NOTE: The API returns level_id, not redirect_url. Build your own redirect:
 * $checkout_url = pmpro_url('checkout', '?level=' . $result['level_id']);
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// ============================================
// Example 1: WPForms Integration
// ============================================

/**
 * Process WPForms submission and create/find membership level.
 *
 * @param array $fields    Form fields.
 * @param array $entry     Entry data.
 * @param array $form_data Form data.
 * @return void
 */
function pmpro_magic_levels_wpforms_handler( $fields, $entry, $form_data ) {

	// Extract data from form fields.
	$level_data = array(
		'name'           => $fields[1]['value'],    // Field ID 1 = Level Name.
		'billing_amount' => $fields[2]['value'],    // Field ID 2 = Price.
		'cycle_period'   => $fields[3]['value'],    // Field ID 3 = Period.
		'cycle_number'   => 1,
	);

	// Process level (find or create).
	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		// Build checkout URL and redirect.
		$checkout_url = pmpro_url( 'checkout', '?level=' . $result['level_id'] );
		wp_safe_redirect( $checkout_url );
		exit;
	} else {
		// Handle error.
		wp_die( esc_html( $result['error'] ) );
	}
}
add_action( 'wpforms_process_complete', 'pmpro_magic_levels_wpforms_handler', 10, 3 );

// ============================================
// Example 2: Gravity Forms Integration
// ============================================

/**
 * Process Gravity Forms submission and create/find membership level.
 *
 * @param array $entry Entry data.
 * @param array $form  Form data.
 * @return void
 */
function pmpro_magic_levels_gforms_handler( $entry, $form ) {

	// Extract data from entry.
	$level_data = array(
		'name'           => rgar( $entry, '1' ),    // Field ID 1.
		'billing_amount' => rgar( $entry, '2' ),    // Field ID 2.
		'cycle_period'   => rgar( $entry, '3' ),    // Field ID 3.
		'cycle_number'   => 1,
	);

	// Process level.
	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'gform_after_submission', 'pmpro_magic_levels_gforms_handler', 10, 2 );

// ============================================
// Example 3: Formidable Forms Integration
// ============================================

/**
 * Process Formidable Forms submission and create/find membership level.
 *
 * @param int $entry_id Entry ID.
 * @param int $form_id  Form ID.
 * @return void
 */
function pmpro_magic_levels_formidable_handler( $entry_id, $form_id ) {

	// Get entry data.
	$entry = FrmEntry::getOne( $entry_id, true );

	$level_data = array(
		'name'           => $entry->metas['field_key_1'],
		'billing_amount' => $entry->metas['field_key_2'],
		'cycle_period'   => $entry->metas['field_key_3'],
		'cycle_number'   => 1,
	);

	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'frm_after_create_entry', 'pmpro_magic_levels_formidable_handler', 30, 2 );

// ============================================
// Example 4: Ninja Forms Integration
// ============================================

/**
 * Process Ninja Forms submission and create/find membership level.
 *
 * @param array $form_data Form data.
 * @return array Modified form data.
 */
function pmpro_magic_levels_ninja_forms_handler( $form_data ) {

	$fields = $form_data['fields'];

	$level_data = array(
		'name'           => $fields[1]['value'],
		'billing_amount' => $fields[2]['value'],
		'cycle_period'   => $fields[3]['value'],
		'cycle_number'   => 1,
	);

	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		$checkout_url = pmpro_url( 'checkout', '?level=' . $result['level_id'] );
		$form_data['actions']['redirect'] = $checkout_url;
	}

	return $form_data;
}
add_filter( 'ninja_forms_submit_data', 'pmpro_magic_levels_ninja_forms_handler' );

// ============================================
// Example 5: Contact Form 7 Integration
// ============================================

/**
 * Process Contact Form 7 submission and create/find membership level.
 *
 * @param WPCF7_ContactForm $contact_form Contact form object.
 * @return void
 */
function pmpro_magic_levels_cf7_handler( $contact_form ) {

	$submission  = WPCF7_Submission::get_instance();
	$posted_data = $submission->get_posted_data();

	$level_data = array(
		'name'           => $posted_data['level-name'],
		'billing_amount' => $posted_data['price'],
		'cycle_period'   => $posted_data['period'],
		'cycle_number'   => 1,
	);

	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'wpcf7_mail_sent', 'pmpro_magic_levels_cf7_handler' );

// ============================================
// Example 6: Generic POST Handler
// ============================================

/**
 * Handle generic POST form submission.
 *
 * @return void
 */
function pmpro_magic_levels_generic_post_handler() {

	// Check if this is your form submission.
	if ( isset( $_POST['create_membership_level'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'create_level' ) ) {

		// Extract data from POST.
		$level_data = array(
			'name'           => isset( $_POST['level_name'] ) ? sanitize_text_field( wp_unslash( $_POST['level_name'] ) ) : '',
			'billing_amount' => isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0,
			'cycle_period'   => isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : '',
			'cycle_number'   => isset( $_POST['cycle'] ) ? intval( $_POST['cycle'] ) : 1,
			'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
		);

		// Process level.
		$result = pmpro_magic_levels_process( $level_data );

		if ( $result['success'] ) {
			wp_safe_redirect( $result['redirect_url'] );
			exit;
		} else {
			wp_die( esc_html( $result['error'] ) );
		}
	}
}
add_action( 'init', 'pmpro_magic_levels_generic_post_handler' );

// ============================================
// Example 7: JavaScript/AJAX Integration
// ============================================

/**
 * Enqueue JavaScript for AJAX form handling.
 *
 * @return void
 */
function pmpro_magic_levels_enqueue_ajax_script() {
	?>
	<script type="text/javascript">
	jQuery( document ).ready( function( $ ) {
		$( '#my-form' ).on( 'submit', function( e ) {
			e.preventDefault();

			var formData = {
				name: $( '#level-name' ).val(),
				billing_amount: $( '#price' ).val(),
				cycle_period: $( '#period' ).val(),
				cycle_number: 1
			};

			$.ajax( {
				url: '/wp-json/pmpro-magic-levels/v1/process',
				method: 'POST',
				data: JSON.stringify( formData ),
				contentType: 'application/json',
				success: function( response ) {
					if ( response.success ) {
						window.location.href = response.redirect_url;
					} else {
						alert( response.error );
					}
				},
				error: function( xhr ) {
					alert( 'Error: ' + xhr.responseJSON.error );
				}
			} );
		} );
	} );
	</script>
	<?php
}
add_action( 'wp_footer', 'pmpro_magic_levels_enqueue_ajax_script' );

// ============================================
// Example 8: With Authentication
// ============================================

/**
 * Enqueue JavaScript for secure AJAX form handling.
 *
 * @return void
 */
function pmpro_magic_levels_enqueue_secure_ajax_script() {
	?>
	<script type="text/javascript">
	jQuery( document ).ready( function( $ ) {
		$( '#secure-form' ).on( 'submit', function( e ) {
			e.preventDefault();

			var formData = {
				auth_key: 'your-secret-key-here',  // Add your auth key.
				name: $( '#level-name' ).val(),
				billing_amount: $( '#price' ).val(),
				cycle_period: $( '#period' ).val(),
				cycle_number: 1
			};

			$.ajax( {
				url: '/wp-json/pmpro-magic-levels/v1/process',
				method: 'POST',
				data: JSON.stringify( formData ),
				contentType: 'application/json',
				success: function( response ) {
					if ( response.success ) {
						window.location.href = response.redirect_url;
					} else {
						alert( response.error );
					}
				}
			} );
		} );
	} );
	</script>
	<?php
}
add_action( 'wp_footer', 'pmpro_magic_levels_enqueue_secure_ajax_script' );
