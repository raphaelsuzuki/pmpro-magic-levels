<?php

use PHPUnit\Framework\TestCase;

class CacheKeyNormalizationTest extends TestCase {

	public function test_generate_key_normalizes_string_fields() {
		$raw_params = array(
			'name'              => '  <b>Group - Gold</b>  ',
			'description'       => "  <em>Premium tier</em>\n",
			'cycle_period'      => " Month\t",
			'expiration_period' => ' Year ',
			'billing_amount'    => '29.99',
			'cycle_number'      => 1,
			'initial_payment'   => 0,
			'trial_amount'      => 0,
			'trial_limit'       => 0,
			'billing_limit'     => 0,
			'expiration_number' => 0,
		);

		$normalized_params = array(
			'name'              => sanitize_text_field( $raw_params['name'] ),
			'description'       => sanitize_textarea_field( $raw_params['description'] ),
			'cycle_period'      => sanitize_text_field( $raw_params['cycle_period'] ),
			'expiration_period' => sanitize_text_field( $raw_params['expiration_period'] ),
			'billing_amount'    => '29.99',
			'cycle_number'      => 1,
			'initial_payment'   => 0,
			'trial_amount'      => 0,
			'trial_limit'       => 0,
			'billing_limit'     => 0,
			'expiration_number' => 0,
		);

		$this->assertSame(
			PMPRO_Magic_Levels_Cache::generate_key( $normalized_params ),
			PMPRO_Magic_Levels_Cache::generate_key( $raw_params )
		);
	}

	public function test_generate_key_changes_when_description_changes() {
		$base = array(
			'name'              => 'Group - Gold',
			'description'       => 'Description A',
			'billing_amount'    => 29.99,
			'cycle_number'      => 1,
			'cycle_period'      => 'Month',
			'initial_payment'   => 0,
			'trial_amount'      => 0,
			'trial_limit'       => 0,
			'billing_limit'     => 0,
			'expiration_number' => 0,
			'expiration_period' => '',
		);

		$changed                = $base;
		$changed['description'] = 'Description B';

		$this->assertNotSame(
			PMPRO_Magic_Levels_Cache::generate_key( $base ),
			PMPRO_Magic_Levels_Cache::generate_key( $changed )
		);
	}
}
