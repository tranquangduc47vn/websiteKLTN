<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Support\Admin\UploadImage;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContentModulesTest extends AdminTestCase
{
    /** @var list<int> */
    private array $pageIds = [];

    /** @var list<int> */
    private array $sliderIds = [];

    /** @var list<int> */
    private array $bannerIds = [];

    /** @var list<int> */
    private array $storeIds = [];

    private ?string $logoBackup = null;

    private ?bool $logoExisted = null;

    private ?string $brandingConfigBackup = null;

    private ?bool $brandingConfigExisted = null;

    protected function tearDown(): void
    {
        if ($this->pageIds !== []) {
            $images = DB::table('content_pages')->whereIn('id', $this->pageIds)->pluck('image');
            DB::table('content_pages')->whereIn('id', $this->pageIds)->delete();
            foreach ($images as $image) {
                $name = basename((string) $image);
                $path = UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . $name;
                if (preg_match('/^c\d{14}[a-f0-9]{8}\./', $name) && is_file($path)) {
                    unlink($path);
                }
            }
        }
        DB::table('content_pages')->where('title', 'like', 'B501-%')->delete();

        if ($this->sliderIds !== []) {
            $images = DB::table('sliders')->whereIn('id', $this->sliderIds)->pluck('image_link');
            DB::table('sliders')->whereIn('id', $this->sliderIds)->delete();
            foreach ($images as $image) {
                $name = basename((string) $image);
                $path = UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . 'slider' . DIRECTORY_SEPARATOR . $name;
                if (preg_match('/^s\d{14}[a-f0-9]{8}\./', $name) && is_file($path)) {
                    unlink($path);
                }
            }
        }
        DB::table('sliders')->where('name', 'like', 'B501-%')->delete();

        if ($this->bannerIds !== []) {
            DB::table('banners')->whereIn('id', $this->bannerIds)->delete();
        }
        if ($this->storeIds !== []) {
            DB::table('stores')->whereIn('id', $this->storeIds)->delete();
        }
        DB::table('stores')->where('name', 'like', 'B501-%')->delete();

        $logo = UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . 'logo.png';
        if ($this->logoExisted === true && $this->logoBackup !== null) {
            file_put_contents($logo, $this->logoBackup);
        } elseif ($this->logoExisted === false && is_file($logo)) {
            unlink($logo);
        }
        $config = dirname(base_path()) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'branding.local.php';
        if ($this->brandingConfigExisted === true && $this->brandingConfigBackup !== null) {
            file_put_contents($config, $this->brandingConfigBackup);
        } elseif ($this->brandingConfigExisted === false && is_file($config)) {
            unlink($config);
        }
        if ($this->brandingConfigExisted !== null) {
            $keep = '';
            if (is_string($this->brandingConfigBackup) && preg_match('/branding\/(site-logo-[^\'"]+)/', $this->brandingConfigBackup, $kept)) {
                $keep = basename($kept[1]);
            }
            foreach (glob(UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . 'branding' . DIRECTORY_SEPARATOR . 'site-logo-*') ?: [] as $file) {
                if (is_file($file) && basename($file) !== $keep) {
                    unlink($file);
                }
            }
        }

        parent::tearDown();
    }

    public function test_guest_and_permission_gates(): void
    {
        foreach (['/admin/banner', '/admin/slider', '/admin/pages', '/admin/store', '/admin/tuyendung', '/admin/hoptac', '/admin/vanchuyen', '/admin/branding'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
            $this->withSession(['login' => ['id' => 1, 'name' => 'Fake', 'level' => 0]])
                ->get($url)->assertRedirect('/admin/login');
        }

        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/banner')->assertRedirect('/admin');
        $this->get('/admin/branding')->assertRedirect('/admin');
        $this->get('/admin/slider')->assertOk()->assertSee('Thêm slider');
        $this->get('/admin/pages')->assertOk()->assertSee('Trang tĩnh');
        Auth::guard('admin')->logout();

        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/banner')->assertOk()->assertSee('Banner danh mục');
        $this->get('/admin/branding')->assertOk()->assertSee('Logo website');
    }

    public function test_content_modules_crud_validation_and_csrf(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');

        $this->post('/admin/tuyendung/add', ['title' => ''])->assertSessionHas('message_fail');
        $this->post('/admin/tuyendung/add', [
            'title' => 'B501-tuyen dung',
            'intro' => 'mo ta',
            'content' => '<p>B501-noi-dung</p><script>alert(1)</script>',
            'image' => UploadedFile::fake()->image('job.jpg'),
        ])->assertRedirect('/admin/tuyendung')->assertSessionHas('message_success');

        $row = DB::table('content_pages')->where('title', 'B501-tuyen dung')->first();
        $this->assertNotNull($row);
        $this->pageIds[] = (int) $row->id;
        $this->assertSame('tuyendung', $row->type);
        $this->assertSame((int) $row->id, (int) $row->legacy_id);
        $this->assertStringNotContainsString('<script', (string) $row->content);
        $this->assertFileExists(UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . $row->image);

        $this->post('/admin/tuyendung/add', [
            'title' => 'B501-bad-file',
            'content' => 'x',
            'image' => UploadedFile::fake()->create('note.php', 20, 'text/plain'),
        ])->assertSessionHas('message_fail');
        $this->assertNull(DB::table('content_pages')->where('title', 'B501-bad-file')->first());

        $this->get('/admin/tuyendung/delete/' . $row->id)->assertNotFound();
        $this->post('/admin/tuyendung/edit/99999999', ['title' => 'B501-khong'])->assertRedirect('/admin/tuyendung');
        $this->post('/admin/hoptac/khong-co')->assertNotFound();
        $this->get('/admin/vanchuyen/delete/1')->assertNotFound();

        $this->post('/admin/pages/add', [
            'title' => 'B501-trang',
            'slug' => 'b501-trang-tinh',
            'content' => '<p>B501-page</p>',
        ])->assertRedirect('/admin/pages');
        $page = DB::table('content_pages')->where('slug', 'b501-trang-tinh')->first();
        $this->assertNotNull($page);
        $this->pageIds[] = (int) $page->id;
        $this->assertSame('page', $page->type);
        $this->post('/admin/pages/edit/' . $page->id, [
            'title' => 'B501-trang-sua',
            'content' => '<p>B501-page</p>',
            'slug' => 'doi-slug',
        ])->assertRedirect('/admin/pages');
        $this->assertSame('b501-trang-tinh', DB::table('content_pages')->where('id', $page->id)->value('slug'));

        $this->post('/admin/store/add', [
            'name' => 'B501-cua-hang',
            'city' => 'Hà Nội',
            'address' => '140 Cầu Giấy',
            'phone' => '0900000000',
            'map_link' => 'javascript:alert(1)',
        ])->assertSessionHas('message_fail');
        $this->post('/admin/store/add', [
            'name' => 'B501-cua-hang',
            'city' => 'Hà Nội',
            'address' => '140 Cầu Giấy',
            'phone' => '0900000000',
            'map_link' => 'https://www.google.com/maps/embed?pb=b501',
        ])->assertRedirect('/admin/store')->assertSessionHas('message_success');
        $storeId = (int) DB::table('stores')->where('name', 'B501-cua-hang')->value('id');
        $this->storeIds[] = $storeId;
        $this->get('/admin/store/delete/' . $storeId)->assertNotFound();
        $this->assertNotNull(DB::table('stores')->where('id', $storeId)->first());
        $this->post('/admin/store/delete/' . $storeId)->assertRedirect('/admin/store');
        $this->assertNull(DB::table('stores')->where('id', $storeId)->first());

        $bannerId = (int) DB::table('banners')->insertGetId([
            'name' => 'B501-banner',
            'image_link' => 'default.jpg',
            'link' => '/san-pham',
            'sort_order' => 99,
        ]);
        $this->bannerIds[] = $bannerId;
        $this->post('/admin/banner/edit/' . $bannerId, [
            'name' => 'B501-banner-sua',
            'link' => 'javascript:alert(1)',
        ])->assertSessionHas('message_fail');
        $this->post('/admin/banner/edit/' . $bannerId, [
            'name' => 'B501-banner-sua',
            'link' => '/danh-muc',
        ])->assertRedirect('/admin/banner');
        $this->assertSame('B501-banner-sua', DB::table('banners')->where('id', $bannerId)->value('name'));

        $this->post('/admin/slider/add', [
            'name' => 'B501-slider',
            'link' => '/khuyen-mai',
            'sort_order' => 2,
            'image' => UploadedFile::fake()->image('slide.png'),
        ])->assertRedirect('/admin/slider')->assertSessionHas('message_success', 'Thêm slider thành công');
        $slider = DB::table('sliders')->where('name', 'B501-slider')->first();
        $this->assertNotNull($slider);
        $this->sliderIds[] = (int) $slider->id;
        $this->assertSame(2, (int) $slider->sort_order);
        $this->get('/admin/slider/del/' . $slider->id)->assertNotFound();
        $image = (string) $slider->image_link;
        $this->post('/admin/slider/del/' . $slider->id)->assertRedirect('/admin/slider');
        $this->assertNull(DB::table('sliders')->where('id', $slider->id)->first());
        $this->assertFileDoesNotExist(UploadImage::uploadRoot() . '/slider/' . $image);
    }

    public function test_logo_upload_and_csrf(): void
    {
        $logo = UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . 'logo.png';
        $config = dirname(base_path()) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'branding.local.php';
        $this->logoExisted = is_file($logo);
        $this->logoBackup = $this->logoExisted ? (string) file_get_contents($logo) : null;
        $this->brandingConfigExisted = is_file($config);
        $this->brandingConfigBackup = $this->brandingConfigExisted ? (string) file_get_contents($config) : null;

        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/branding', [])->assertSessionHas('message_fail');
        $this->post('/admin/branding', [
            'logo' => UploadedFile::fake()->image('logo.png', 40, 40),
        ])->assertRedirect('/admin/branding')->assertSessionHas('message_success', 'Cập nhật logo thành công.');
        $this->assertFileExists($logo);
        $this->assertStringContainsString('branding/site-logo-', (string) file_get_contents($config));

        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post('/admin/slider/add', ['name' => 'B501-csrf', 'link' => '/x'])->assertStatus(419);
        $this->post('/admin/store/delete/1')->assertStatus(419);
    }
}
