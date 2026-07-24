<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$site_logo = 'logo.png';

$local = APPPATH . 'config/branding.local.php';
if (is_file($local)) {
	include $local;
}

$config['branding'] = array(
	'site_logo' => isset($site_logo) ? (string) $site_logo : 'logo.png',
);
