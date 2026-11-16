<?php

namespace Tests\Feature\Storefront;

use Tests\LegacyMysqlTestCase;

class LegacyPagesTest extends LegacyMysqlTestCase
{
    public function test_storefront_content_pages_are_served_by_laravel(): void
    {
        $this->get('/hethongcuahang')->assertOk()->assertSee('Hệ thống cửa hàng');
        $this->get('/hethongcuahang/khong-co')->assertNotFound();

        $this->get('/vanchuyen')->assertOk()->assertSee('Chính sách vận chuyển');
        $this->get('/VanChuyen')->assertOk();
        $this->get('/tich-diem')->assertOk()->assertSee('Chính sách tích điểm');
        $this->get('/TichDiem')->assertOk()->assertSee('Thành viên');

        $this->get('/ThongTinTuyenDung')->assertOk()->assertSee('Cơ hội nghề nghiệp');
        $this->get('/LienHeHopTacKinhDoanh')->assertOk()->assertSee('Hợp tác kinh doanh');
        $this->get('/vanchuyen/view/khong-co-trang')->assertRedirect(url('/'));
        $this->get('/tuyendung/view/khong-co-trang')->assertRedirect(url('/'));
        $this->get('/hoptac/view/khong-co-trang')->assertRedirect(url('/'));
    }
}
