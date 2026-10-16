<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportTest extends AdminTestCase
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

    public function test_guest_and_fake_session_cannot_open_report(): void
    {
        $this->get('/admin/report')->assertRedirect('/admin/login');
        $this->withSession(['login' => ['id' => 1, 'name' => 'x', 'email' => 'x@example.test', 'level' => 0]])
            ->get('/admin/report')
            ->assertRedirect('/admin/login');
    }

    public function test_level_permissions_and_query_level_are_ignored(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/report')->assertRedirect('/admin');
        $this->get('/admin/report?level=0')->assertRedirect('/admin');

        Auth::guard('admin')->logout();
        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/report?level=0')
            ->assertOk()
            ->assertSee('Thống kê doanh thu')
            ->assertDontSee('Xuất báo cáo');
    }

    public function test_revenue_uses_completed_order_snapshot_and_inclusive_custom_range(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $today = date('Y-m-d');
        $before = $this->revenue($today, $today);

        $this->insertOrder(4, 999111, strtotime($today . ' 12:00:00'), 'B13-CANCEL');
        $this->insertOrder(0, 888111, strtotime($today . ' 12:00:00'), 'B13-PENDING');
        $this->assertSame($before, $this->revenue($today, $today));

        $this->insertOrder(3, 864201, strtotime($today . ' 23:59:00'), 'B13-DONE', 80000);
        $this->assertSame($before + 864201, $this->revenue($today, $today));

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->insertOrder(3, 111333, strtotime($yesterday . ' 23:59:00'), 'B13-YDAY');
        $this->assertSame($before + 864201, $this->revenue($today, $today));
        $typed = $this->get('/admin/report?type=day&date_from=' . $yesterday . '&date_to=' . $yesterday)->assertOk()->getContent();
        preg_match('/data-revenue="(\d+)"/', (string) $typed, $typedMatch);
        $this->assertSame($before + 864201, (int) $typedMatch[1]);
        $yday = $this->revenue($yesterday, $yesterday);
        $this->assertGreaterThanOrEqual(111333, $yday);

        $this->get('/admin/report?type=week')->assertOk()->assertSee('Tuần này');
        $this->get('/admin/report?type=month')->assertOk();
        $this->get('/admin/report?type=year')->assertOk();
        $this->get('/admin/report?type=nope')->assertOk()->assertSee('Đơn hàng thành công');
        $this->get('/admin/report?date_from=not-a-date')->assertOk();
        $this->get('/admin/report')->assertSee('Xuất báo cáo')->assertSee('Chi tiết bán ra');
    }

    public function test_report_page_does_not_query_products_one_by_one(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/admin/report?type=month')->assertOk();
        $log = DB::getQueryLog();
        $productSql = 0;
        $monthSql = 0;
        foreach ($log as $row) {
            $sql = (string) $row['query'];
            if (str_contains($sql, 'da_ban')) {
                $productSql++;
            }
            if (str_contains($sql, 'SUM(amount)') || str_contains($sql, 'sum(amount)')) {
                $monthSql++;
            }
        }
        $this->assertSame(1, $productSql);
        $this->assertLessThanOrEqual(13, $monthSql);
        $this->assertLessThan(40, count($log));
    }

    private function revenue(string $from, string $to): int
    {
        $html = $this->get('/admin/report?date_from=' . $from . '&date_to=' . $to)->assertOk()->getContent();
        $this->assertNotFalse($html);
        preg_match('/data-revenue="(\d+)"/', $html, $match);
        $this->assertArrayHasKey(1, $match);

        return (int) $match[1];
    }

    private function insertOrder(int $status, int $amount, int $created, string $name, int $lineAmount = 0): void
    {
        $productId = (int) DB::table('product')->orderBy('id')->value('id');
        $id = (int) DB::table('transaction')->insertGetId([
            'status' => $status,
            'user_id' => 0,
            'user_name' => $name,
            'user_email' => 'b13@example.test',
            'user_phone' => '0900131300',
            'user_address' => 'HN',
            'message' => '',
            'amount' => $amount,
            'voucher_code' => '',
            'discount_amount' => 0,
            'payment' => 'COD',
            'created' => $created,
        ]);
        $this->orderIds[] = $id;
        if ($lineAmount > 0) {
            DB::table('order')->insert([
                'transaction_id' => $id,
                'product_id' => $productId,
                'variant_id' => (int) DB::table('product_variants')->where('product_id', $productId)->value('id'),
                'size' => 'M',
                'color' => 'Den',
                'qty' => 1,
                'amount' => $lineAmount,
                'status' => 0,
                'cost_price' => 1000,
            ]);
        }
    }
}
