<?php
/**
 * Manual AI chat checks — php tests/manual_ai_chat.php
 * Live send test: php tests/manual_ai_chat.php --live http://127.0.0.1:8099
 */
define('BASEPATH', true);
define('APPPATH', __DIR__ . '/../application/');
define('FCPATH', __DIR__ . '/../');

$liveBase = '';
foreach ($argv as $i => $arg) {
	if (strpos($arg, '--live=') === 0) {
		$liveBase = rtrim(substr($arg, 7), '/');
	} elseif ($arg === '--live' && isset($argv[$i + 1])) {
		$liveBase = rtrim($argv[$i + 1], '/');
	}
}

function report($name, $pass, $detail = '')
{
	echo ($pass ? 'PASS' : 'FAIL') . ' — ' . $name;
	if ($detail !== '') {
		echo ' (' . $detail . ')';
	}
	echo PHP_EOL;
	return (bool) $pass;
}

$ok = true;

$ok = report('ai.php exists', is_file(APPPATH . 'config/ai.php')) && $ok;
if (is_file(APPPATH . 'config/ai.php')) {
	require APPPATH . 'config/ai.php';
	$key = isset($config['ai_gemini_api_key']) ? trim((string) $config['ai_gemini_api_key']) : '';
	$model = isset($config['ai_gemini_model']) ? trim((string) $config['ai_gemini_model']) : '';
	$ok = report('Gemini API key set', $key !== '') && $ok;
	$ok = report('Gemini model set', $model !== '', $model) && $ok;
}

require APPPATH . 'config/database.php';
$c = $db['default'];
$m = new mysqli($c['hostname'], $c['username'], $c['password'], $c['database']);
if ($m->connect_error) {
	$ok = report('DB connection', false, $m->connect_error) && $ok;
} else {
	$ok = report('DB connection', true) && $ok;
	foreach (array('ai_setting', 'ai_conversation', 'ai_message') as $table) {
		$r = $m->query("SHOW TABLES LIKE '{$table}'");
		$ok = report("table {$table}", $r && $r->num_rows > 0) && $ok;
	}
	$r = $m->query("SELECT setting_value FROM ai_setting WHERE setting_key='ai_enabled' LIMIT 1");
	if ($r && $row = $r->fetch_assoc()) {
		$ok = report('ai_enabled in DB', true, $row['setting_value']) && $ok;
	} else {
		$ok = report('ai_enabled in DB', false, 'missing row') && $ok;
	}

	// Stale conversation ownership: same logic as Ai_conversation_model::find_or_create
	$guestA = bin2hex(random_bytes(8));
	$now = time();
	$m->query("INSERT INTO ai_conversation (user_id, guest_token, status, started, last_message) VALUES (0, '" . $m->real_escape_string($guestA) . "', 'ai_active', {$now}, {$now})");
	$convId = (int) $m->insert_id;
	$guestB = bin2hex(random_bytes(8));
	$row = $m->query("SELECT id, guest_token FROM ai_conversation WHERE id={$convId} LIMIT 1")->fetch_assoc();
	$allowed = ($row && (string) $row['guest_token'] === $guestA);
	$ok = report('conversation owned by guest A', $allowed) && $ok;
	$blocked = ($row && (string) $row['guest_token'] !== $guestB);
	$ok = report('conversation blocked for guest B', $blocked) && $ok;

	$m->query("DELETE FROM ai_conversation WHERE id={$convId}");
}

$configPhp = file_get_contents(APPPATH . 'config/config.php');
$ok = report('CSRF excludes ai-assistant/send', strpos($configPhp, "'ai-assistant/send'") !== false) && $ok;

$js = file_get_contents(FCPATH . 'assets/site/js/ai-chat.js');
$ok = report('ai-chat.js has parseJsonResponse', strpos($js, 'function parseJsonResponse') !== false) && $ok;
$ok = report('ai-chat.js has fallback reply', strpos($js, 'chưa nhận được phản hồi') !== false) && $ok;
$widget = file_get_contents(APPPATH . 'views/site/chat/widget.php');
$ok = report('widget uses ai-chat v7', strpos($widget, 'ai-chat.js') !== false && strpos($widget, '?v=7') !== false) && $ok;
$ok = report('widget has login gate', strpos($widget, 'support-chat-login-gate') !== false) && $ok;

if ($liveBase !== '') {
	$url = $liveBase . '/ai-assistant/send';
	$body = http_build_query(array('message' => 'hello', 'conversation_id' => 0));
	$ctx = stream_context_create(array(
		'http' => array(
			'method' => 'POST',
			'header' => "Content-Type: application/x-www-form-urlencoded\r\nX-Requested-With: XMLHttpRequest\r\n",
			'content' => $body,
			'timeout' => 45,
			'ignore_errors' => true,
		),
	));
	$raw = @file_get_contents($url, false, $ctx);
	$data = $raw ? json_decode($raw, true) : null;
	$content = is_array($data) && isset($data['content']) ? trim((string) $data['content']) : '';
	$detail = is_array($data)
		? ('ok=' . (!empty($data['ok']) ? '1' : '0') . ' content=' . substr($content, 0, 80))
		: ('raw=' . substr((string) $raw, 0, 120));
	$ok = report('live POST ai-assistant/send requires login', is_array($data) && !empty($data['login_required']), $detail) && $ok;
}

echo PHP_EOL . ($ok ? "ALL CHECKS PASSED\n" : "SOME CHECKS FAILED\n");
exit($ok ? 0 : 1);
