<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ContentPage;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NewsAdminTest extends AdminTestCase
{
    /** @var list<int> */
    private array $ids = [];

    protected function tearDown(): void
    {
        $images = DB::table('content_pages')->where('title', 'like', 'B17-%')->pluck('image');
        DB::table('content_pages')->whereIn('id', $this->ids)->delete();
        DB::table('content_pages')->where('title', 'like', 'B17-%')->delete();
        $dir = dirname(base_path()) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'news' . DIRECTORY_SEPARATOR;
        foreach ($images as $image) {
            $name = basename((string) $image);
            if (preg_match('/^n\d{14}[a-f0-9]{8}\./', $name) && is_file($dir . $name)) {
                unlink($dir . $name);
            }
        }
        parent::tearDown();
    }

    public function test_guest_fake_session_and_levels(): void
    {
        $this->get('/admin/posts')->assertRedirect('/admin/login');
        $this->withSession(['login' => ['id' => 1, 'name' => 'Fake', 'email' => 'x@example.test', 'level' => 0]])
            ->get('/admin/posts')->assertRedirect('/admin/login');

        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/posts?level=0')->assertOk()->assertSee('Thêm mới');

        Auth::guard('admin')->logout();
        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/posts')->assertOk();
        $this->get('/admin/news')->assertRedirect('/admin/posts');
        $this->get('/admin/posts/del/1')->assertNotFound();
    }

    public function test_admin_creates_edits_deletes_and_rejects_bad_upload(): void
    {
        $before = (int) DB::table('content_pages')->where('type', 'news')->count();
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/posts/add', [
            'title' => '',
            'content' => 'x',
        ])->assertSessionHas('message_fail');

        $this->post('/admin/posts/add', [
            'title' => 'B17-bai admin',
            'intro' => 'mo ta',
            'content' => '<p>B17-html</p>',
            'image' => UploadedFile::fake()->image('news.jpg'),
        ])->assertRedirect('/admin/posts')->assertSessionHas('message_success', 'Thêm tin tức thành công');

        $row = DB::table('content_pages')->where('title', 'B17-bai admin')->first();
        $this->assertNotNull($row);
        $this->ids[] = (int) $row->id;
        $this->assertSame('news', $row->type);
        $this->assertSame((int) $row->id, (int) $row->legacy_id);
        $this->assertMatchesRegularExpression('/^n\d{14}[a-f0-9]{8}\.jpg$/', (string) $row->image);
        $this->assertFileExists(dirname(base_path()) . '/upload/news/' . $row->image);

        $this->post('/admin/posts/add', [
            'title' => 'B17-bad',
            'content' => 'x',
            'image' => UploadedFile::fake()->create('note.php', 20, 'text/plain'),
        ])->assertSessionHas('message_fail');
        $this->assertNull(DB::table('content_pages')->where('title', 'B17-bad')->first());

        $this->post('/admin/posts/edit/' . $row->id, [
            'title' => 'B17-da sua',
            'intro' => 'mo ta',
            'content' => '<p>B17-html</p>',
        ])->assertRedirect('/admin/posts');
        $this->assertSame('B17-da sua', DB::table('content_pages')->where('id', $row->id)->value('title'));

        $this->post('/admin/posts/edit/99999999', ['title' => 'B17-khong', 'content' => 'x'])
            ->assertRedirect('/admin/posts')
            ->assertSessionHas('message_fail', 'Bài viết không tồn tại');

        $image = (string) $row->image;
        $this->post('/admin/posts/del/' . $row->id)->assertRedirect('/admin/posts')
            ->assertSessionHas('message_success', 'Xóa bài viết thành công');
        $this->assertNull(DB::table('content_pages')->where('id', $row->id)->first());
        $this->assertFileDoesNotExist(dirname(base_path()) . '/upload/news/' . $image);
        $this->assertSame($before, (int) DB::table('content_pages')->where('type', 'news')->count());
    }

    public function test_news_post_without_csrf_is_rejected(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/posts/add', ['title' => 'B17-csrf', 'content' => 'x'])->assertStatus(419);
        $this->post('/admin/posts/del/1')->assertStatus(419);
    }
}
