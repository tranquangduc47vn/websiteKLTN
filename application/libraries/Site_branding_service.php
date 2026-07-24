<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Site_branding_service {

	protected $CI;
	protected $upload_dir;
	protected $local_config;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->upload_dir = FCPATH . 'upload/branding/';
		$this->local_config = APPPATH . 'config/branding.local.php';
		$this->CI->load->library('upload');
		$this->CI->load->library('upload_library');
	}

	public function get_logo_relative_path()
	{
		$this->CI->config->load('branding', true);
		$path = $this->CI->config->item('site_logo', 'branding');
		return $path ? (string) $path : 'logo.png';
	}

	public function get_logo_absolute_path()
	{
		return FCPATH . 'upload/' . ltrim(str_replace('\\', '/', $this->get_logo_relative_path()), '/');
	}

	public function logo_exists()
	{
		$path = $this->get_logo_absolute_path();
		return is_file($path) && filesize($path) > 0;
	}

	public function save_logo_upload($field = 'logo')
	{
		if (empty($_FILES[$field]['name'])) {
			return array('ok' => false, 'message' => 'Vui lòng chọn file logo.');
		}

		if (!is_dir($this->upload_dir)) {
			if (!@mkdir($this->upload_dir, 0755, true) && !is_dir($this->upload_dir)) {
				return array('ok' => false, 'message' => 'Không tạo được thư mục upload/branding/.');
			}
		}

		$config = $this->CI->upload_library->config($this->upload_dir);
		$config['file_name'] = 'site-logo';
		$config['overwrite'] = true;
		$this->CI->upload->initialize($config);

		if (!$this->CI->upload->do_upload($field)) {
			$error = strip_tags($this->CI->upload->display_errors('', ''));
			return array(
				'ok' => false,
				'message' => $error !== '' ? $error : 'Không thể tải logo lên.',
			);
		}

		$data = $this->CI->upload->data();
		$new_relative = 'branding/' . $data['file_name'];
		$old_relative = $this->get_logo_relative_path();

		if (!$this->write_local_config($new_relative)) {
			return array('ok' => false, 'message' => 'Đã upload ảnh nhưng không ghi được cấu hình. Kiểm tra quyền ghi application/config/.');
		}

		$this->remove_old_logo_file($old_relative, $new_relative);
		$this->sync_legacy_logo_copy($new_relative);

		return array(
			'ok' => true,
			'message' => 'Cập nhật logo thành công.',
			'path' => $new_relative,
		);
	}

	protected function write_local_config($relative_path)
	{
		$content = "<?php\n"
			. "defined('BASEPATH') OR exit('No direct script access allowed');\n\n"
			. '$site_logo = ' . var_export((string) $relative_path, true) . ";\n";

		if (@file_put_contents($this->local_config, $content, LOCK_EX) === false) {
			return false;
		}

		if (function_exists('opcache_invalidate')) {
			@opcache_invalidate($this->local_config, true);
		}

		$this->CI->config->load('branding', true);
		return true;
	}

	protected function remove_old_logo_file($old_relative, $new_relative)
	{
		$old_relative = ltrim(str_replace('\\', '/', (string) $old_relative), '/');
		$new_relative = ltrim(str_replace('\\', '/', (string) $new_relative), '/');
		if ($old_relative === '' || $old_relative === $new_relative) {
			return;
		}
		if (strpos($old_relative, '..') !== false) {
			return;
		}

		$old_abs = FCPATH . 'upload/' . $old_relative;
		if (is_file($old_abs) && strpos($old_relative, 'branding/') === 0) {
			@unlink($old_abs);
		}
	}

	protected function sync_legacy_logo_copy($relative_path)
	{
		$src = FCPATH . 'upload/' . ltrim(str_replace('\\', '/', $relative_path), '/');
		$dest = FCPATH . 'upload/logo.png';
		if (!is_file($src)) {
			return;
		}
		@copy($src, $dest);
	}
}
