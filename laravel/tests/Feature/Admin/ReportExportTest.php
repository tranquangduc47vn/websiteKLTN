<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReportExportTest extends AdminTestCase
{
    /** @var list<int> */
    private array $orderIds = [];

    protected function tearDown(): void
    {
        if ($this->orderIds !== []) {
            DB::table('order')->whereIn('transaction_id', $this->orderIds)->delete();
            DB::table('transaction')->whereIn('id', $this->orderIds)->delete();
        }
        parent::tearDown();
    }

    public function test_export_requires_admin_and_rejects_unknown_module(): void
    {
        $this->get('/admin/export/excel/revenue')->assertRedirect('/admin/login');
        $this->withSession(['login' => ['id' => 1, 'level' => 0]])
            ->get('/admin/export/excel/orders')
            ->assertRedirect('/admin/login');

        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/export/excel/orders?level=0')->assertForbidden();
        $this->get('/admin/export/pdf/orders')->assertForbidden();

        Auth::guard('admin')->logout();
        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/export/preview/voucher')->assertForbidden();

        Auth::guard('admin')->logout();
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->get('/admin/export/excel/not-a-report')->assertNotFound();
        $this->get('/admin/export/pdf/../../.env')->assertNotFound();
        $this->get('/admin/export/excel/revenue?file=../../.env')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_excel_and_pdf_match_the_revenue_report_filter(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $today = date('Y-m-d');
        $this->insertOrder(3, 864201, strtotime($today . ' 10:00:00'), 'B13-EXPORT');
        $html = $this->get('/admin/report?type=day')->assertOk()->getContent();
        preg_match('/data-revenue="(\d+)"/', (string) $html, $match);
        $revenue = (int) $match[1];

        $excel = $this->get('/admin/export/excel/revenue?type=day&scope=all');
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $excel->headers->get('content-type'));
        $this->assertStringContainsString('doanh-thu-', (string) $excel->headers->get('content-disposition'));
        $sheet = $this->sheet($excel->getContent());
        $found = false;
        foreach ($sheet->toArray() as $row) {
            foreach ($row as $cell) {
                if ((float) $cell === (float) $revenue) {
                    $found = true;
                }
            }
        }
        $this->assertTrue($found, 'Excel revenue cell does not match the report total '.$revenue);
        $this->assertSame('Tháng', $this->header($sheet, 'Tháng'));

        $pdf = $this->get('/admin/export/pdf/revenue?type=day');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $bytes = $pdf->getContent();
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertGreaterThan(800, strlen($bytes));

        $this->get('/admin/export/preview/revenue?type=day')
            ->assertOk()
            ->assertSee('Báo cáo doanh thu')
            ->assertSee(number_format($revenue, 0, ',', '.'), false);
    }

    public function test_order_export_applies_status_and_page_scope(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->insertOrder(4, 100, time(), 'B13-HIDE-CANCEL');
        $done = $this->insertOrder(3, 200, time(), 'B13-SHOW-DONE');
        $this->insertOrder(3, 300, time(), 'B13-SHOW-NEWER');

        $preview = $this->get('/admin/export/preview/orders?status=3')->assertOk();
        $preview->assertSee('B13-SHOW-DONE')->assertSee('B13-SHOW-NEWER')->assertDontSee('B13-HIDE-CANCEL');

        $page = $this->get('/admin/export/preview/orders?status=3&scope=page&per_page=1&offset=0')->assertOk();
        $page->assertSee('B13-SHOW-NEWER')->assertDontSee('B13-SHOW-DONE');
        $this->assertGreaterThan(0, $done);
    }

    public function test_inventory_voucher_and_dashboard_exports_are_read_only(): void
    {
        $jm10 = (int) DB::table('voucher')->where('code', 'JM10')->value('used_count');
        $jm20 = (int) DB::table('voucher')->where('code', 'JM20')->value('used_count');
        $stock = (int) DB::table('product_variants')->sum('stock');
        $products = (int) DB::table('product')->count();

        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->get('/admin/export/preview/voucher')->assertOk()->assertSee('JM10')->assertSee('JM20')->assertSee('Báo cáo voucher');
        $this->get('/admin/export/preview/inventory')->assertOk()->assertSee('Báo cáo tồn kho')->assertSee('Số biến thể');
        $this->get('/admin/export/excel/dashboard?period=today')->assertOk();
        $this->get('/admin/export/preview/customers')->assertOk()->assertSee('Báo cáo khách hàng');
        $this->get('/admin/export/preview/products')->assertOk()->assertSee('Báo cáo sản phẩm');
        $this->get('/admin/export/preview/top_products?type=year')->assertOk()->assertSee('Báo cáo sản phẩm bán chạy');

        $this->assertSame($jm10, (int) DB::table('voucher')->where('code', 'JM10')->value('used_count'));
        $this->assertSame($jm20, (int) DB::table('voucher')->where('code', 'JM20')->value('used_count'));
        $this->assertSame($stock, (int) DB::table('product_variants')->sum('stock'));
        $this->assertSame($products, (int) DB::table('product')->count());
    }

    public function test_post_export_without_csrf_is_rejected(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/export/excel/revenue', ['type' => 'day'])->assertStatus(419);
    }

    private function insertOrder(int $status, int $amount, int $created, string $name): int
    {
        $id = (int) DB::table('transaction')->insertGetId([
            'status' => $status,
            'user_id' => 0,
            'user_name' => $name,
            'user_email' => 'b13export@example.test',
            'user_phone' => '0900131301',
            'user_address' => 'HN',
            'message' => '',
            'amount' => $amount,
            'voucher_code' => '',
            'discount_amount' => 0,
            'payment' => 'COD',
            'created' => $created,
        ]);
        $this->orderIds[] = $id;

        return $id;
    }

    private function sheet(string $bytes): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $this->assertNotSame('', $bytes);
        $path = storage_path('framework/testing/b13-export.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $bytes);
        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            @unlink($path);
        }
    }

    private function header(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $label): string
    {
        foreach ($sheet->toArray() as $row) {
            foreach ($row as $cell) {
                if ((string) $cell === $label) {
                    return $label;
                }
            }
        }
        $this->fail('Missing header ' . $label);
    }
}
