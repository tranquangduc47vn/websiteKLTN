<?php

namespace Tests\Feature\Storefront;

use App\Models\ContentPage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\LegacyMysqlTestCase;

class NewsTest extends LegacyMysqlTestCase
{
    /** @var list<int> */
    private array $ids = [];

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        $images = DB::table('content_pages')->whereIn('id', $this->ids)->pluck('image');
        DB::table('content_pages')->whereIn('id', $this->ids)->delete();
        DB::table('content_pages')->where('title', 'like', 'B17-%')->delete();
        $dir = dirname(base_path()) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'news' . DIRECTORY_SEPARATOR;
        foreach ($images as $image) {
            $name = basename((string) $image);
            if (preg_match('/^n\d{14}[a-f0-9]{8}\./', $name) && is_file($dir . $name)) {
                unlink($dir . $name);
            }
        }
        foreach ($this->files as $name) {
            if (is_file($dir . $name)) {
                unlink($dir . $name);
            }
        }
        parent::tearDown();
    }

    public function test_list_detail_legacy_urls_and_invalid_id(): void
    {
        $id = $this->insert('B17-bai viet', '<b>B17-noi-dung</b>');
        $this->get('/tin-tuc')->assertOk()->assertSee('B17-bai viet')->assertSee('Tin tức');
        $this->get('/tin-tuc?sort=price_asc')->assertOk()->assertSee('B17-bai viet');
        $this->get('/tin-tuc/bai-viet-n' . $id)->assertOk()->assertSee('B17-bai viet')->assertSee('B17-noi-dung', false);
        $this->get('/tin-tuc/khong-dung-n99999999')->assertRedirect('/');
        $this->get('/tintuc')->assertOk()->assertSee('B17-bai viet');
        $this->get('/posts')->assertRedirect('/tin-tuc');
        $this->get('/posts/view/' . $id)->assertRedirect();
        $this->get('/news')->assertRedirect('/tin-tuc');
        $this->get('/tin-tuc/8000')->assertOk()->assertSee('Hiện chưa có tin tức');
    }

    public function test_title_is_escaped_on_the_list(): void
    {
        $this->insert('B17-<script>alert(1)</script>');
        $this->get('/tin-tuc')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    private function insert(string $title, string $content = 'noi dung'): int
    {
        $id = (int) DB::table('content_pages')->insertGetId([
            'type' => ContentPage::TYPE_NEWS,
            'legacy_id' => 0,
            'title' => $title,
            'slug' => 'b17',
            'intro' => 'B17 intro',
            'content' => $content,
            'image' => 'default.jpg',
            'created_at' => now(),
        ]);
        DB::table('content_pages')->where('id', $id)->update(['legacy_id' => $id]);
        $this->ids[] = $id;

        return $id;
    }
}
