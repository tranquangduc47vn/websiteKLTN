<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('rate_limit_storage_key')) {
	function rate_limit_storage_key($email)
	{
		$CI =& get_instance();
		$ip = (string) $CI->input->ip_address();
		$email = strtolower(trim((string) $email));
		return 'rl_' . md5($ip . '|' . $email);
	}
}

if (!function_exists('rate_limit_check')) {
	function rate_limit_check($email, $max_attempts = 5, $window_seconds = 900)
	{
		$CI =& get_instance();
		$CI->load->driver('cache', array('adapter' => 'file', 'backup' => 'file'));

		$key = rate_limit_storage_key($email);
		$record = $CI->cache->get($key);
		if (!is_array($record)) {
			return array(
				'locked' => FALSE,
				'attempts' => 0,
				'retry_after' => 0,
			);
		}

		$attempts = isset($record['attempts']) ? (int) $record['attempts'] : 0;
		$first_at = isset($record['first_at']) ? (int) $record['first_at'] : 0;
		$now = time();

		if ($first_at <= 0 || ($now - $first_at) >= (int) $window_seconds) {
			$CI->cache->delete($key);
			return array(
				'locked' => FALSE,
				'attempts' => 0,
				'retry_after' => 0,
			);
		}

		if ($attempts >= (int) $max_attempts) {
			return array(
				'locked' => TRUE,
				'attempts' => $attempts,
				'retry_after' => max(1, (int) $window_seconds - ($now - $first_at)),
			);
		}

		return array(
			'locked' => FALSE,
			'attempts' => $attempts,
			'retry_after' => 0,
		);
	}
}

if (!function_exists('rate_limit_hit')) {
	function rate_limit_hit($email, $max_attempts = 5, $window_seconds = 900)
	{
		$CI =& get_instance();
		$CI->load->driver('cache', array('adapter' => 'file', 'backup' => 'file'));

		$key = rate_limit_storage_key($email);
		$record = $CI->cache->get($key);
		$now = time();

		if (!is_array($record) || !isset($record['first_at']) || ($now - (int) $record['first_at']) >= (int) $window_seconds) {
			$record = array(
				'attempts' => 1,
				'first_at' => $now,
			);
		} else {
			$record['attempts'] = isset($record['attempts']) ? ((int) $record['attempts'] + 1) : 1;
		}

		$CI->cache->save($key, $record, (int) $window_seconds);
		return rate_limit_check($email, $max_attempts, $window_seconds);
	}
}

if (!function_exists('rate_limit_clear')) {
	function rate_limit_clear($email)
	{
		$CI =& get_instance();
		$CI->load->driver('cache', array('adapter' => 'file', 'backup' => 'file'));
		$CI->cache->delete(rate_limit_storage_key($email));
	}
}

if (!function_exists('rate_limit_message')) {
	function rate_limit_message($retry_after_seconds)
	{
		$minutes = max(1, (int) ceil((int) $retry_after_seconds / 60));
		return 'Quá nhiều lần đăng nhập sai. Vui lòng thử lại sau ' . $minutes . ' phút.';
	}
}
