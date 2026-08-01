<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Unified CMS storage (content_pages). Subclasses set $content_type.
 */
class Content_page_model extends MY_Model {

	var $table = 'content_pages';
	protected $content_type = '';
	protected $legacy_table = '';

	protected function uses_unified_table()
	{
		return $this->content_type !== '' && $this->db->table_exists('content_pages');
	}

	protected function active_table()
	{
		return $this->uses_unified_table() ? 'content_pages' : $this->legacy_table;
	}

	public function create($data = array())
	{
		$this->table = $this->active_table();
		if ($this->uses_unified_table()) {
			$data = $this->normalize_write($data);
			$data['type'] = $this->content_type;
			if (!isset($data['legacy_id'])) {
				$data['legacy_id'] = 0;
			}
		}
		$ok = parent::create($data);
		if ($ok && $this->uses_unified_table()) {
			$new_id = (int) $this->db->insert_id();
			if ($new_id > 0 && (int) $data['legacy_id'] === 0) {
				$this->db->where('id', $new_id)->update('content_pages', array('legacy_id' => $new_id));
			}
		}
		return $ok;
	}

	public function update($id, $data)
	{
		$this->table = $this->active_table();
		if ($this->uses_unified_table()) {
			$data = $this->normalize_write($data);
			unset($data['type'], $data['legacy_id']);
			$where = $this->resolve_id_where($id);
			if (!$where) {
				return false;
			}
			return $this->update_rule($where, $data);
		}
		return parent::update($id, $data);
	}

	public function delete($id)
	{
		$this->table = $this->active_table();
		if ($this->uses_unified_table()) {
			$where = $this->resolve_id_where($id);
			if (!$where) {
				return false;
			}
			return $this->del_rule($where);
		}
		return parent::delete($id);
	}

	public function get_info($id, $field = '')
	{
		if (!$id) {
			return false;
		}
		$this->table = $this->active_table();
		if ($this->uses_unified_table()) {
			$row = $this->get_info_rule(array('id' => (int) $id, 'type' => $this->content_type), $field);
			if (!$row) {
				$row = $this->get_info_rule(array('legacy_id' => (int) $id, 'type' => $this->content_type), $field);
			}
			return $this->normalize_row($row);
		}
		return parent::get_info($id, $field);
	}

	public function get_list($input = array())
	{
		$this->table = $this->active_table();
		$list = parent::get_list($input);
		if (!$this->uses_unified_table()) {
			return $list;
		}
		foreach ($list as $i => $row) {
			$list[$i] = $this->normalize_row($row);
		}
		return $list;
	}

	public function get_total($input = array())
	{
		$this->table = $this->active_table();
		return parent::get_total($input);
	}

	public function get_row($input = array())
	{
		$this->table = $this->active_table();
		$row = parent::get_row($input);
		return $this->uses_unified_table() ? $this->normalize_row($row) : $row;
	}

	protected function get_list_set_input($input = array())
	{
		$this->table = $this->active_table();
		if ($this->uses_unified_table()) {
			if (!isset($input['where']) || !is_array($input['where'])) {
				$input['where'] = array();
			}
			$input['where']['type'] = $this->content_type;
		}
		parent::get_list_set_input($input);
	}

	protected function resolve_id_where($id)
	{
		$id = (int) $id;
		if ($id <= 0) {
			return null;
		}
		$row = $this->db->select('id')
			->from('content_pages')
			->where('type', $this->content_type)
			->group_start()
				->where('id', $id)
				->or_where('legacy_id', $id)
			->group_end()
			->limit(1)
			->get()
			->row();
		if (!$row) {
			return null;
		}
		return array('id' => (int) $row->id, 'type' => $this->content_type);
	}

	protected function normalize_write($data)
	{
		if (isset($data['image_link'])) {
			$data['image'] = $data['image_link'];
			unset($data['image_link']);
		}
		if (isset($data['created'])) {
			$data['created_at'] = $data['created'];
			unset($data['created']);
		}
		if ($this->content_type === 'news' && empty($data['slug']) && !empty($data['title'])) {
			$data['slug'] = $this->slugify(isset($data['title']) ? $data['title'] : '');
		}
		return $data;
	}

	protected function normalize_row($row)
	{
		if (!$row) {
			return $row;
		}
		if ($this->content_type === 'news') {
			if (isset($row->image)) {
				$row->image_link = $row->image;
			}
			if (isset($row->created_at)) {
				$row->created = $row->created_at;
			}
		}
		return $row;
	}

	protected function slugify($text)
	{
		$text = trim((string) $text);
		if ($text === '') {
			return 'noi-dung';
		}
		$text = function_exists('url_title') ? url_title($text, '-', true) : preg_replace('/[^a-z0-9]+/i', '-', strtolower($text));
		return $text !== '' ? $text : 'noi-dung';
	}
}
