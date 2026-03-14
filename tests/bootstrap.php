<?php

defined('ABSPATH') || define('ABSPATH', __DIR__ . '/');

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value)
	{
		$value = is_scalar($value) ? (string) $value : '';
		$value = strip_tags($value);
		$value = preg_replace('/[\r\n\t ]+/', ' ', $value);
		return trim($value);
	}
}

if (!function_exists('sanitize_textarea_field')) {
	function sanitize_textarea_field($value)
	{
		$value = is_scalar($value) ? (string) $value : '';
		$value = strip_tags($value);
		$value = preg_replace('/\r\n|\r/', "\n", $value);
		return trim($value);
	}
}

if (!function_exists('wp_json_encode')) {
	function wp_json_encode($value)
	{
		return json_encode($value);
	}
}

require_once dirname(__DIR__) . '/includes/class-cache.php';