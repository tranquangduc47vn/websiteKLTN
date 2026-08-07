<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller quản lý đánh giá sản phẩm phía admin (duyệt, ẩn, xóa nhận xét của khách).
 */
class Product_review extends MY_Admin_Controller {

	protected $currentUser;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('permission');
		$this->currentUser = $this->session->userdata('login');
		if (!admin_can('product.manage', $this->currentUser)) {
			admin_flash_fail_home('Bạn không có quyền quản lý đánh giá sản phẩm.');
			redirect(admin_url('home'));
		}
		$this->load->model('product_review_model');
	}

	public function index()
	{
		if (!$this->db->table_exists('product_review')) {
			admin_flash_fail('Chưa có bảng product_review. Chạy run_migrations.php.');
			redirect(admin_url('home'));
		}

		$status = trim((string) $this->input->get('status'));
		if ($status === '') {
			$status = 'pending';
		}

		$this->data['filter_status'] = $status;
		$this->data['keyword'] = trim((string) $this->input->get('q'));
		$this->data['list'] = $this->product_review_model->list_admin(array(
			'status' => $status,
			'q' => $this->data['keyword'],
			'limit' => 100,
		));
		$this->data['counts'] = array(
			'pending' => $this->product_review_model->count_by_status(Product_review_model::STATUS_PENDING),
			'approved' => $this->product_review_model->count_by_status(Product_review_model::STATUS_APPROVED),
			'rejected' => $this->product_review_model->count_by_status(Product_review_model::STATUS_REJECTED),
		);

		$this->render_admin('admin/product_review/index');
	}

	public function approve($id = 0)
	{
		admin_require_post();
		$this->_moderate((int) $id, Product_review_model::STATUS_APPROVED, 'Đã duyệt đánh giá.');
	}

	public function reject($id = 0)
	{
		admin_require_post();
		$this->_moderate((int) $id, Product_review_model::STATUS_REJECTED, 'Đã từ chối đánh giá.');
	}

	public function delete($id = 0)
	{
		admin_require_post();
		$id = (int) $id;
		$review = $this->product_review_model->get_info($id);
		if (!$review) {
			admin_flash_fail('Đánh giá không tồn tại.');
			redirect(admin_url('product-reviews'));
		}

		$product_id = (int) $review->product_id;
		if ($this->product_review_model->delete($id)) {
			$this->product_review_model->sync_product_stats($product_id);
			admin_flash_info('Đã xóa đánh giá.');
		} else {
			admin_flash_fail('Không xóa được đánh giá.');
		}
		redirect(admin_url('product-reviews'));
	}

	protected function _moderate($id, $status, $success_message)
	{
		$review = $this->product_review_model->get_info($id);
		if (!$review) {
			admin_flash_fail('Đánh giá không tồn tại.');
			redirect(admin_url('product-reviews'));
		}

		$admin_id = isset($this->currentUser->id) ? (int) $this->currentUser->id : 0;
		if ($this->product_review_model->set_status($id, $status, $admin_id)) {
			$this->product_review_model->sync_product_stats((int) $review->product_id);
			admin_flash_info($success_message);
		} else {
			admin_flash_fail('Không cập nhật được trạng thái đánh giá.');
		}
		redirect(admin_url('product-reviews'));
	}
}
