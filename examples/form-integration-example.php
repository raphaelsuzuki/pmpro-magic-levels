<?php
/**
 * Generic Form Integration Examples
 * 
 * Copy these examples to your theme's functions.php or custom plugin
 */

// ============================================
// Example 1: WPForms Integration
// ============================================
add_action('wpforms_process_complete', function($fields, $entry, $form_data) {
    
    // Extract data from form fields
    $level_data = [
        'name' => $fields[1]['value'],              // Field ID 1 = Level Name
        'billing_amount' => $fields[2]['value'],    // Field ID 2 = Price
        'cycle_period' => $fields[3]['value'],      // Field ID 3 = Period
        'cycle_number' => 1,
    ];
    
    // Process level (find or create)
    $result = pmpro_magic_levels_process($level_data);
    
    if ($result['success']) {
        // Redirect to checkout
        wp_redirect($result['redirect_url']);
        exit;
    } else {
        // Handle error
        wp_die($result['error']);
    }
    
}, 10, 3);

// ============================================
// Example 2: Gravity Forms Integration
// ============================================
add_action('gform_after_submission', function($entry, $form) {
    
    // Extract data from entry
    $level_data = [
        'name' => rgar($entry, '1'),           // Field ID 1
        'billing_amount' => rgar($entry, '2'), // Field ID 2
        'cycle_period' => rgar($entry, '3'),   // Field ID 3
        'cycle_number' => 1,
    ];
    
    // Process level
    $result = pmpro_magic_levels_process($level_data);
    
    if ($result['success']) {
        wp_redirect($result['redirect_url']);
        exit;
    }
    
}, 10, 2);

// ============================================
// Example 3: Formidable Forms Integration
// ============================================
add_action('frm_after_create_entry', function($entry_id, $form_id) {
    
    // Get entry data
    $entry = FrmEntry::getOne($entry_id, true);
    
    $level_data = [
        'name' => $entry->metas['field_key_1'],
        'billing_amount' => $entry->metas['field_key_2'],
        'cycle_period' => $entry->metas['field_key_3'],
        'cycle_number' => 1,
    ];
    
    $result = pmpro_magic_levels_process($level_data);
    
    if ($result['success']) {
        wp_redirect($result['redirect_url']);
        exit;
    }
    
}, 30, 2);

// ============================================
// Example 4: Ninja Forms Integration
// ============================================
add_filter('ninja_forms_submit_data', function($form_data) {
    
    $fields = $form_data['fields'];
    
    $level_data = [
        'name' => $fields[1]['value'],
        'billing_amount' => $fields[2]['value'],
        'cycle_period' => $fields[3]['value'],
        'cycle_number' => 1,
    ];
    
    $result = pmpro_magic_levels_process($level_data);
    
    if ($result['success']) {
        $form_data['actions']['redirect'] = $result['redirect_url'];
    }
    
    return $form_data;
});

// ============================================
// Example 5: Contact Form 7 Integration
// ============================================
add_action('wpcf7_mail_sent', function($contact_form) {
    
    $submission = WPCF7_Submission::get_instance();
    $posted_data = $submission->get_posted_data();
    
    $level_data = [
        'name' => $posted_data['level-name'],
        'billing_amount' => $posted_data['price'],
        'cycle_period' => $posted_data['period'],
        'cycle_number' => 1,
    ];
    
    $result = pmpro_magic_levels_process($level_data);
    
    if ($result['success']) {
        wp_redirect($result['redirect_url']);
        exit;
    }
});

// ============================================
// Example 6: Generic POST Handler
// ============================================
add_action('init', function() {
    
    // Check if this is your form submission
    if (isset($_POST['create_membership_level']) && wp_verify_nonce($_POST['_wpnonce'], 'create_level')) {
        
        // Extract data from POST
        $level_data = [
            'name' => sanitize_text_field($_POST['level_name']),
            'billing_amount' => floatval($_POST['price']),
            'cycle_period' => sanitize_text_field($_POST['period']),
            'cycle_number' => intval($_POST['cycle']),
            'description' => sanitize_textarea_field($_POST['description']),
        ];
        
        // Process level
        $result = pmpro_magic_levels_process($level_data);
        
        if ($result['success']) {
            wp_redirect($result['redirect_url']);
            exit;
        } else {
            wp_die($result['error']);
        }
    }
});

// ============================================
// Example 7: JavaScript/AJAX Integration
// ============================================
?>
<script>
jQuery(document).ready(function($) {
    $('#my-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            name: $('#level-name').val(),
            billing_amount: $('#price').val(),
            cycle_period: $('#period').val(),
            cycle_number: 1
        };
        
        $.ajax({
            url: '/wp-json/pmpro-magic-levels/v1/process',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirect_url;
                } else {
                    alert(response.error);
                }
            },
            error: function(xhr) {
                alert('Error: ' + xhr.responseJSON.error);
            }
        });
    });
});
</script>
<?php

// ============================================
// Example 8: With Authentication
// ============================================
?>
<script>
jQuery(document).ready(function($) {
    $('#secure-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            auth_key: 'your-secret-key-here',  // Add your auth key
            name: $('#level-name').val(),
            billing_amount: $('#price').val(),
            cycle_period: $('#period').val(),
            cycle_number: 1
        };
        
        $.ajax({
            url: '/wp-json/pmpro-magic-levels/v1/process',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirect_url;
                } else {
                    alert(response.error);
                }
            }
        });
    });
});
</script>
