<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('csrf_field')) {
	function csrf_field()
	{
		$CI =& get_instance();
		if ($CI->config->item('csrf_protection') !== TRUE) {
			return '';
		}
		return form_hidden(
			$CI->security->get_csrf_token_name(),
			$CI->security->get_csrf_hash()
		);
	}
}

if (!function_exists('csrf_json_meta')) {
	function csrf_json_meta()
	{
		$CI =& get_instance();
		if ($CI->config->item('csrf_protection') !== TRUE) {
			return array();
		}
		return array(
			'csrf_name' => $CI->security->get_csrf_token_name(),
			'csrf_hash' => $CI->security->get_csrf_hash(),
		);
	}
}

if (!function_exists('csrf_bootstrap_script')) {
	function csrf_bootstrap_script()
	{
		$CI =& get_instance();
		if ($CI->config->item('csrf_protection') !== TRUE) {
			return '';
		}
		$name = $CI->security->get_csrf_token_name();
		$hash = $CI->security->get_csrf_hash();
		$payload = json_encode(array('name' => $name, 'hash' => $hash), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

		return '<script>window.CI_CSRF=' . $payload . ';'
			. 'window.appendCsrf=function(d){d=d||{};if(window.CI_CSRF){d[window.CI_CSRF.name]=window.CI_CSRF.hash;}return d;};'
			. 'window.applyCsrfResponse=function(r){if(r&&r.csrf_hash&&window.CI_CSRF){window.CI_CSRF.hash=r.csrf_hash;document.querySelectorAll(\'input[name="\'+window.CI_CSRF.name+\'"]\').forEach(function(el){el.value=r.csrf_hash;});}};</script>';
	}
}

if (!function_exists('csrf_form_inject_script')) {
	function csrf_form_inject_script()
	{
		$CI =& get_instance();
		if ($CI->config->item('csrf_protection') !== TRUE) {
			return '';
		}

		return '<script>(function(){function inject(){if(!window.CI_CSRF)return;document.querySelectorAll(\'form[method="post"],form[method="POST"]\').forEach(function(form){if(form.querySelector(\'input[name="\'+window.CI_CSRF.name+\'"]\'))return;var input=document.createElement("input");input.type="hidden";input.name=window.CI_CSRF.name;input.value=window.CI_CSRF.hash;form.appendChild(input);});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",inject);}else{inject();}})();</script>';
	}
}
