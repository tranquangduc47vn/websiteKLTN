<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ExportFilter
{
	/** @var array */
	protected $params = array();

	/** @var string */
	protected $scope = 'all';

	/** @var int */
	protected $page = 1;

	/** @var int */
	protected $per_page = 0;

	/** @var int */
	protected $offset = 0;

	public static function fromInput(array $input = array())
	{
		$filter = new self();
		$filter->params = $input;

		$scope = isset($input['scope']) ? (string) $input['scope'] : 'all';
		$filter->scope = ($scope === 'page') ? 'page' : 'all';

		$filter->page = max(1, (int) (isset($input['page']) ? $input['page'] : 1));
		$filter->per_page = max(0, (int) (isset($input['per_page']) ? $input['per_page'] : 0));
		$filter->offset = max(0, (int) (isset($input['offset']) ? $input['offset'] : 0));

		return $filter;
	}

	public function get($key, $default = '')
	{
		return array_key_exists($key, $this->params) ? $this->params[$key] : $default;
	}

	public function all()
	{
		return $this->params;
	}

	public function scope()
	{
		return $this->scope;
	}

	public function isPageScope()
	{
		return $this->scope === 'page';
	}

	public function page()
	{
		return $this->page;
	}

	public function perPage()
	{
		return $this->per_page;
	}

	public function offset()
	{
		return $this->offset;
	}

	public function setPagination($per_page, $offset)
	{
		$this->per_page = max(0, (int) $per_page);
		$this->offset = max(0, (int) $offset);
		if ($this->per_page > 0) {
			$this->page = (int) floor($this->offset / $this->per_page) + 1;
		}
	}
}
