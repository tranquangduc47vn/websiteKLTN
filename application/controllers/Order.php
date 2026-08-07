<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller xử lý đặt hàng và thanh toán phía storefront.
 * Quản lý: trang checkout, áp voucher (AJAX), trang QR chuyển khoản, hủy đơn từ phía khách.
 * Giỏ hàng đọc từ session custom_cart; tồn kho được kiểm tra lại ngay trước khi tạo đơn.
 */
class Order extends MY_Frontend_Controller {
    public function __construct()
    {
        parent::__construct();
        
        $this->load->library('form_validation');
        $this->load->helper('form');
        $this->load->model('product_model');
        $this->load->library('product_service');
        $this->load->library('inventory_service');
    }

    // Trang checkout: hiển thị form đặt hàng; xử lý POST tạo đơn (kiểm tra tồn kho, trừ kho, ghi nhận voucher).
    public function index()
    {
        $carts = $this->session->userdata('custom_cart');
        if (!is_array($carts)) {
            $carts = array();
        }

        $sanitize = $this->_sanitize_cart($carts);
        $carts = $sanitize['cart'];
        if (!empty($sanitize['removed']) || !empty($sanitize['changed'])) {
            $this->load->helper('cart');
            cart_persist_session($carts);
            if (!empty($sanitize['removed'])) {
                set_store_flash(
                    'fail',
                    'Một số sản phẩm không còn bán (' . implode(', ', $sanitize['removed']) . ') và đã được xóa khỏi giỏ hàng.'
                );
            }
            if (empty($carts)) {
                redirect(build_cart_url());
            }
        }

        $total_amount = 0;
        foreach ($carts as $value) {
            $total_amount = $total_amount + $value['subtotal'];
        }
        $this->data['total_amount'] = $total_amount;

        $user_id = 0;
        if ($this->session->userdata('user')) {
            $user = $this->session->userdata('user');
            $user_id = $user->id;
        }
        $this->_refresh_checkout_voucher($user_id, (int) $total_amount);

        if ($this->input->post()) {
            if (empty($carts)) {
                set_store_flash('fail', 'Giỏ hàng trống, không thể đặt hàng.');
                redirect(build_cart_url());
            }

            $this->form_validation->set_rules('name','Họ tên','required');
            $this->form_validation->set_rules('email', 'Email đăng nhập', 'required|valid_email');
            $this->form_validation->set_rules('address','Địa chỉ','required');
            $this->form_validation->set_rules('message','Ghi chú','trim');
            $this->form_validation->set_rules('phone','Điện thoại','required');
            
            if ($this->form_validation->run()) {
                $this->load->helper('loyalty');
                $this->load->model('voucher_model');

                $sanitize = $this->_sanitize_cart($carts);
                $carts = $sanitize['cart'];
                if (!empty($sanitize['removed'])) {
                    $this->load->helper('cart');
                    cart_persist_session($carts);
                    set_store_flash(
                        'fail',
                        'Không thể đặt hàng: một số sản phẩm không đủ tồn kho hoặc không còn bán (' . implode(', ', $sanitize['removed']) . '). Vui lòng kiểm tra lại giỏ hàng.'
                    );
                    redirect(build_cart_url());
                }
                if (!empty($sanitize['changed'])) {
                    $this->load->helper('cart');
                    cart_persist_session($carts);
                }
                if (empty($carts)) {
                    set_store_flash('fail', 'Giỏ hàng trống, không thể đặt hàng.');
                    redirect(build_cart_url());
                }

                $total_amount = 0;
                foreach ($carts as $value) {
                    $total_amount = $total_amount + $value['subtotal'];
                }

                $payment_method = $this->input->post('payment');
                if (empty($payment_method)) {
                    $payment_method = 'COD';
                }

                $cart_subtotal = (int) $total_amount;
                $discount_amount = 0;
                $voucher_code = loyalty_normalize_voucher_code($this->input->post('voucher_code'));
                $applied_voucher = null;

                if ($voucher_code !== '') {
                    $check = $this->voucher_model->validate_for_checkout($voucher_code, $user_id, $cart_subtotal);
                    if (!$check['ok']) {
                        $this->session->unset_userdata('checkout_voucher');
                        set_store_flash('fail', $check['message']);
                        redirect(build_checkout_url());
                    }
                    $discount_amount = (int) $check['discount'];
                    $applied_voucher = $check['voucher'];
                }

                $final_amount = max(0, $cart_subtotal - $discount_amount);

                $this->load->model('transaction_model');
                $this->load->model('order_model');

                $order_lines = array();
                foreach ($carts as $items) {
                    $product_info = $this->product_model->get_info($items['id']);
                    if (empty($product_info)) {
                        set_store_flash(
                            'fail',
                            'Không thể đặt hàng vì sản phẩm không còn tồn tại. Vui lòng cập nhật giỏ hàng.'
                        );
                        redirect(build_cart_url());
                    }

                    $size = isset($items['options']['size']) ? $items['options']['size'] : '';
                    $color = isset($items['options']['color']) ? $items['options']['color'] : '';
                    $variant_id = !empty($items['variant_id']) ? (int) $items['variant_id'] : 0;
                    if ($variant_id <= 0) {
                        $variant_id = $this->product_service->resolve_variant_id($items['id'], $color, $size, false);
                    }
                    if ($variant_id <= 0) {
                        set_store_flash(
                            'fail',
                            'Không thể đặt hàng: biến thể sản phẩm "' . $product_info->name . '" không còn khả dụng.'
                        );
                        redirect(build_cart_url());
                    }

                    $qty = max(1, (int) $items['qty']);
                    $check = $this->product_service->validate_cart_quantity($items['id'], $color, $size, $qty, 0, false);
                    if (!$check['ok']) {
                        set_store_flash('fail', $check['message']);
                        redirect(build_checkout_url());
                    }

                    $order_lines[] = array(
                        'items' => $items,
                        'product_info' => $product_info,
                        'variant_id' => (int) $check['variant_id'],
                        'size' => $check['size'],
                        'color' => $check['color'],
                        'qty' => $qty,
                    );
                }

                $deduct_by_variant = array();
                foreach ($order_lines as $line) {
                    $vid = (int) $line['variant_id'];
                    if (!isset($deduct_by_variant[$vid])) {
                        $deduct_by_variant[$vid] = 0;
                    }
                    $deduct_by_variant[$vid] += (int) $line['qty'];
                }

                $this->db->trans_start();

                $data = array(
                    'user_id'      => $user_id,
                    'user_name'    => $this->input->post('name'),
                    'user_email'   => $this->input->post('email'),
                    'user_address' => $this->input->post('address'),
                    'user_phone'   => $this->input->post('phone'),
                    'message'      => trim((string) $this->input->post('message')),
                    'amount'       => $final_amount,
                    'voucher_code' => $voucher_code,
                    'discount_amount' => $discount_amount,
                    'payment'      => $payment_method,
                    'status'       => '0',
                    'created'      => time()
                );
                
                $this->transaction_model->create($data);
                $transaction_id = $this->db->insert_id();

                if ($applied_voucher) {
                    $this->voucher_model->record_use(
                        (int) $applied_voucher->id,
                        $user_id,
                        $transaction_id,
                        $discount_amount
                    );
                }

                $sync_product_ids = array();
                foreach ($deduct_by_variant as $variant_id => $deduct_qty) {
                    $deduct = $this->inventory_service->deduct_for_order(
                        (int) $variant_id,
                        (int) $deduct_qty,
                        (int) $transaction_id,
                        $user_id,
                        'Xuất kho đơn #' . (int) $transaction_id,
                        false,
                        false
                    );
                    if (empty($deduct['ok'])) {
                        $this->db->trans_rollback();
                        set_store_flash(
                            'fail',
                            !empty($deduct['message'])
                                ? $deduct['message']
                                : 'Không đủ tồn kho để đặt hàng. Vui lòng giảm số lượng hoặc thử lại sau.'
                        );
                        redirect(build_checkout_url());
                    }
                    if (!empty($deduct['product_id'])) {
                        $sync_product_ids[(int) $deduct['product_id']] = true;
                    }
                }

                foreach ($order_lines as $line) {
                    $items = $line['items'];
                    $variant_id = (int) $line['variant_id'];
                    $size = (string) $line['size'];
                    $color = (string) $line['color'];

                    $order_data = array(
                        'transaction_id' => $transaction_id,
                        'product_id'     => $items['id'],
                        'variant_id'     => $variant_id,
                        'size'           => $size !== '' ? $size : 'Mặc định',
                        'color'          => $color !== '' ? $color : 'Mặc định',
                        'qty'            => $line['qty'],
                        'amount'         => $items['subtotal']
                    );
                    if ($this->db->field_exists('cost_price', 'order') && $variant_id > 0 && $this->db->table_exists('product_variants')) {
                        $vrow = $this->db->select('cost_price')->where('id', $variant_id)->get('product_variants')->row();
                        $order_data['cost_price'] = $vrow ? max(0, (float) $vrow->cost_price) : 0;
                    }
                    $this->order_model->create($order_data);
                }

                $this->db->trans_complete();
                if ($this->db->trans_status() === FALSE) {
                    set_store_flash('fail', 'Đặt hàng thất bại. Vui lòng thử lại sau.');
                    redirect(build_checkout_url());
                }

                foreach (array_keys($sync_product_ids) as $product_id) {
                    $this->product_service->sync_product_quantity_cache((int) $product_id);
                }

                $this->_remember_order_access($transaction_id);
                $this->session->unset_userdata('checkout_voucher');

                if ($payment_method == 'Chuyển khoản') {
                    redirect(build_checkout_qr_url($transaction_id));
                } else {
                    $this->load->helper('cart');
                    cart_clear_session();
                    set_store_flash('success', 'Đặt hàng thành công! Đơn hàng của bạn sẽ được thanh toán bằng hình thức COD (Tiền mặt).');
                    redirect(build_cart_url());
                }
            }
        }

        if ($this->session->userdata('user')) {
            $this->data['user'] = $this->session->userdata('user');
        }

        $this->data['checkout_voucher'] = $this->session->userdata('checkout_voucher');
        $this->data['checkout_apply_url'] = site_url('thanh-toan/apply_voucher');

        load_store_flash_to($this->data);

        $this->render_frontend_sub('site/order/index');
    }

    // AJAX áp dụng / bỏ voucher; cập nhật session checkout_voucher và trả về JSON với số tiền giảm.
    public function apply_voucher()
    {
        $this->output->set_content_type('application/json', 'utf-8');

        $subtotal = $this->_cart_subtotal();
        if ($subtotal <= 0) {
            echo json_encode(array_merge(array('ok' => false, 'message' => 'Giỏ hàng trống.'), csrf_json_meta()));
            return;
        }

        $user_id = 0;
        if ($this->session->userdata('user')) {
            $user_id = (int) $this->session->userdata('user')->id;
        }
        if ($user_id <= 0) {
            echo json_encode(array_merge(array('ok' => false, 'message' => 'Vui lòng đăng nhập để dùng voucher.'), csrf_json_meta()));
            return;
        }

        $this->load->helper('loyalty');
        $this->load->model('voucher_model');
        $code = loyalty_normalize_voucher_code($this->input->post('voucher_code'));

        if ($code === '') {
            $this->session->unset_userdata('checkout_voucher');
            echo json_encode(array_merge(array(
                'ok' => true,
                'cleared' => true,
                'message' => 'Đã bỏ mã voucher.',
                'subtotal' => $subtotal,
                'discount' => 0,
                'final' => $subtotal,
                'subtotal_fmt' => $this->_money_fmt($subtotal),
                'final_fmt' => $this->_money_fmt($subtotal),
            ), csrf_json_meta()));
            return;
        }

        $check = $this->voucher_model->validate_for_checkout($code, $user_id, $subtotal);
        if (!$check['ok']) {
            $this->session->unset_userdata('checkout_voucher');
            echo json_encode(array_merge(array('ok' => false, 'message' => $check['message']), csrf_json_meta()));
            return;
        }

        $discount = (int) $check['discount'];
        $final = max(0, $subtotal - $discount);
        $payload = array(
            'code' => $code,
            'discount' => $discount,
            'cart_subtotal' => $subtotal,
            'final' => $final,
        );
        $this->session->set_userdata('checkout_voucher', $payload);

        $msg = 'Áp dụng mã thành công';
        if (!empty($check['voucher']->name)) {
            $msg .= ': ' . $check['voucher']->name;
        }
        $msg .= '.';

        echo json_encode(array_merge(array(
            'ok' => true,
            'message' => $msg,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'final' => $final,
            'subtotal_fmt' => $this->_money_fmt($subtotal),
            'discount_fmt' => $this->_money_fmt($discount),
            'final_fmt' => $this->_money_fmt($final),
        ), csrf_json_meta()));
    }

    // Trang QR chuyển khoản: hiển thị mã VietQR sau khi đặt hàng thành công bằng phương thức chuyển khoản.
    public function checkout_qr()
    {
        $id = $this->uri->segment(3);
        if (empty($id)) {
            redirect(base_url());
        }

        $this->load->model('transaction_model');
        $transaction = $this->transaction_model->get_info($id);

        if (empty($transaction)) {
            redirect(base_url());
        }

        if ($transaction->payment !== 'Chuyển khoản' || (string) $transaction->status !== '0') {
            set_store_flash('fail', 'Không thể xem mã QR cho đơn hàng này.');
            redirect(build_cart_url());
        }

        if (!$this->_can_access_order($transaction)) {
            set_store_flash('fail', 'Bạn không có quyền xem thông tin thanh toán đơn hàng này.');
            redirect(base_url());
        }

        $BANK_ID = "vietcombank";        
        $ACCOUNT_NO = "1234567890";      
        $ACCOUNT_NAME = "NGUYEN VAN A";   
        
        $AMOUNT = $transaction->amount;   
        $DESCRIPTION = "DH" . $transaction->id; 

        $qr_image_url = "https://img.vietqr.io/image/{$BANK_ID}-{$ACCOUNT_NO}-compact2.png?amount={$AMOUNT}&addInfo=" . urlencode($DESCRIPTION) . "&accountName=" . urlencode($ACCOUNT_NAME);

        $this->data['qr_image_url'] = $qr_image_url;
        $this->data['transaction'] = $transaction;

        // Xóa giỏ sau khi hiển thị QR (chuyển khoản)
        $this->load->helper('cart');
        cart_clear_session();
        
        $this->render_frontend_sub('site/order/checkout_qr');
    }
    // Khách hủy đơn (POST): chỉ cho phép hủy status 0 hoặc 1; hoàn kho và điểm tích lũy nếu cần.
    public function cancel()
    {
        if ($this->input->method() !== 'post') {
            show_404();
            return;
        }

        $transaction_id = $this->input->post('transaction_id');
        if (empty($transaction_id)) {
            $transaction_id = $this->uri->segment(3);
        }
        if (empty($transaction_id)) {
            set_store_flash('fail', 'Không tìm thấy mã đơn hàng!');
            redirect(base_url());
        }

        $this->load->model('transaction_model');
        $transaction = $this->transaction_model->get_info($transaction_id);

        if (empty($transaction)) {
            set_store_flash('fail', 'Đơn hàng không tồn tại trên hệ thống!');
            redirect(base_url());
        }

        // Chủ đơn hoặc phiên order_access_ids
        if (!$this->_can_access_order($transaction)) {
            set_store_flash('fail', 'Bạn không có quyền xử lý đơn hàng này!');
            redirect(base_url());
        }

        if (!in_array((string) $transaction->status, array('0', '1'), true)) {
            $this->session->set_flashdata('message_fail', 'Đơn hàng không thể hủy ở trạng thái hiện tại.');
            redirect(base_url('user'));
        }

        $reason = $this->input->post('reason');
        $reason = !empty($reason) ? trim((string) $reason) : 'Khách hàng tự hủy đơn';

        // Hoàn kho + loyalty; status 4 = đã hủy
        if ($transaction->status != '4') {
            $reverse_buyed = ((string) $transaction->status === '3');
            $this->transaction_model->release_stock_for_transaction($transaction_id, $reverse_buyed);

            $this->load->model('loyalty_model');
            $this->loyalty_model->on_order_cancelled($transaction, $reverse_buyed);

            $this->transaction_model->update($transaction_id, array(
                'status' => '4',
                'reason' => $reason
            ));

            $this->session->set_flashdata('message_success', 'Hủy đơn hàng thành công! Sản phẩm đã được hoàn lại kho hàng.');
        } else {
            $this->session->set_flashdata('message_fail', 'Đơn hàng này đã được hủy từ trước.');
        }

        redirect(base_url('user')); 
    }

    // Khách không đăng nhập vẫn hủy/xem QR qua order_access_ids
    private function _remember_order_access($transaction_id)
    {
        $ids = $this->session->userdata('order_access_ids');
        if (!is_array($ids)) {
            $ids = array();
        }
        $ids[(int) $transaction_id] = time();
        $this->session->set_userdata('order_access_ids', $ids);
    }

    private function _can_access_order($transaction)
    {
        if (empty($transaction)) {
            return false;
        }

        $order_id = (int) $transaction->id;

        if ($this->session->userdata('user')) {
            $user = $this->session->userdata('user');
            if ((int) $transaction->user_id > 0) {
                return (int) $transaction->user_id === (int) $user->id;
            }
        }

        $ids = $this->session->userdata('order_access_ids');
        return is_array($ids) && !empty($ids[$order_id]);
    }

    private function _cart_subtotal()
    {
        $carts = $this->session->userdata('custom_cart');
        if (!is_array($carts)) {
            return 0;
        }
        $sanitize = $this->_sanitize_cart($carts);
        if (!empty($sanitize['changed']) || !empty($sanitize['removed'])) {
            $this->load->helper('cart');
            cart_persist_session($sanitize['cart']);
        }
        $total = 0;
        foreach ($sanitize['cart'] as $value) {
            $total += (int) $value['subtotal'];
        }
        return $total;
    }

    private function _refresh_checkout_voucher($user_id, $cart_subtotal)
    {
        $applied = $this->session->userdata('checkout_voucher');
        if (!is_array($applied) || empty($applied['code'])) {
            $this->session->unset_userdata('checkout_voucher');
            return;
        }
        if ((int) $applied['cart_subtotal'] !== (int) $cart_subtotal) {
            $this->session->unset_userdata('checkout_voucher');
            return;
        }
        if ((int) $user_id <= 0) {
            $this->session->unset_userdata('checkout_voucher');
            return;
        }

        $this->load->helper('loyalty');
        $this->load->model('voucher_model');
        $code = loyalty_normalize_voucher_code($applied['code']);
        $check = $this->voucher_model->validate_for_checkout($code, (int) $user_id, (int) $cart_subtotal);
        if (!$check['ok']) {
            $this->session->unset_userdata('checkout_voucher');
            return;
        }

        $discount = (int) $check['discount'];
        $this->session->set_userdata('checkout_voucher', array(
            'code' => $code,
            'discount' => $discount,
            'cart_subtotal' => (int) $cart_subtotal,
            'final' => max(0, (int) $cart_subtotal - $discount),
        ));
    }

    private function _money_fmt($amount)
    {
        return number_format((int) $amount, 0, ',', '.') . ' ₫';
    }

    // Kiểm tra toàn bộ giỏ hàng: xóa sản phẩm không còn bán/hết tồn, cập nhật giá và subtotal theo DB hiện tại.
    private function _sanitize_cart(array $carts)
    {
        $clean = array();
        $removed = array();
        $changed = FALSE;

        foreach ($carts as $rowid => $item) {
            $product_id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($product_id <= 0) {
                $changed = TRUE;
                continue;
            }

            $product = $this->product_model->get_info($product_id);
            if (empty($product)) {
                $removed[] = !empty($item['name']) ? $item['name'] : ('#' . $product_id);
                $changed = TRUE;
                continue;
            }

            $size = isset($item['options']['size']) ? (string) $item['options']['size'] : '';
            $color = isset($item['options']['color']) ? (string) $item['options']['color'] : '';
            $qty = max(1, (int) (isset($item['qty']) ? $item['qty'] : 1));

            $check = $this->product_service->validate_cart_quantity($product_id, $color, $size, $qty, 0, FALSE);
            if (!$check['ok']) {
                $removed[] = (string) $product->name;
                $changed = TRUE;
                continue;
            }

            $price = floatval($product->price);
            if ($product->discount > 0) {
                $price = floatval($product->price - $product->discount);
            }
            if ($price <= 0) {
                $price = 1000;
            }

            $subtotal = $price * $qty;
            if (!isset($item['options']) || !is_array($item['options'])) {
                $item['options'] = array();
            }

            $item['price'] = $price;
            $item['qty'] = $qty;
            $item['subtotal'] = $subtotal;
            $item['variant_id'] = (int) $check['variant_id'];
            $item['name'] = $product->name;
            $item['options']['size'] = $check['size'];
            $item['options']['color'] = $check['color'];

            if (!isset($carts[$rowid])
                || (float) $carts[$rowid]['price'] !== $price
                || (float) $carts[$rowid]['subtotal'] !== $subtotal
                || (int) (isset($carts[$rowid]['variant_id']) ? $carts[$rowid]['variant_id'] : 0) !== (int) $check['variant_id']) {
                $changed = TRUE;
            }

            $clean[$rowid] = $item;
        }

        if (count($clean) !== count($carts)) {
            $changed = TRUE;
        }

        return array(
            'cart' => $clean,
            'removed' => $removed,
            'changed' => $changed,
        );
    }
}