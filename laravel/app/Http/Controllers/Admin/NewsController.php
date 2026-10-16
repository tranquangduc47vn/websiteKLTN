<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Support\Admin\NewsImageStore;
use App\Support\HtmlSanitizer;
use App\Support\Storefront\Slug;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * CMS tin tức. CI3 không kiểm tra admin_can, chỉ cần đăng nhập admin.
 */
class NewsController extends Controller
{
    public function __construct(private readonly NewsImageStore $images) {}

    public function index()
    {
        return view('admin.news.index', [
            'list' => ContentPage::query()->where('type', ContentPage::TYPE_NEWS)->orderByDesc('id')->get(),
        ]);
    }

    public function handlePath(Request $request, string $path = '')
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), fn ($part) => $part !== ''));
        $action = $parts[0] ?? '';
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            'add' => $this->form($request, null),
            'edit' => $this->form($request, $this->find($id)),
            'del' => $this->destroy($request, $id),
            default => abort(404),
        };
    }

    private function form(Request $request, ?ContentPage $post)
    {
        if ($request->isMethod('post') && $request->route('path') && str_starts_with($request->route('path'), 'edit') && ! $post) {
            return redirect('/admin/posts')->with('message_fail', 'Bài viết không tồn tại');
        }
        if (! $request->isMethod('post')) {
            if ($request->route('path') && str_starts_with((string) $request->route('path'), 'edit') && ! $post) {
                return redirect('/admin/posts')->with('message_fail', 'Bài viết không tồn tại');
            }

            return view('admin.news.form', ['post' => $post]);
        }

        $title = trim((string) $request->input('title', ''));
        $content = HtmlSanitizer::clean((string) $request->input('content', ''));
        if ($title === '' || mb_strlen($title) > 255) {
            return back()->withInput()->with('message_fail', $title === '' ? 'Tiêu đề tin tức không được để trống.' : 'Tiêu đề tối đa 255 ký tự.');
        }
        if (trim($content) === '') {
            return back()->withInput()->with('message_fail', 'Nội dung chi tiết không được để trống.');
        }

        $image = $post->image ?? 'default.jpg';
        if ($request->hasFile('image')) {
            try {
                $stored = $this->images->store($request->file('image'));
            } catch (RuntimeException $e) {
                return back()->withInput()->with('message_fail', $e->getMessage());
            }
            if ($post && $post->image) {
                $this->images->delete((string) $post->image);
            }
            $image = $stored;
        }

        $payload = [
            'title' => $title,
            'intro' => (string) $request->input('intro', ''),
            'content' => $content,
            'image' => $image,
            'slug' => Slug::make($title) ?: 'noi-dung',
        ];

        if ($post) {
            $post->fill($payload)->save();
            $flash = 'Cập nhật tin tức thành công';
        } else {
            $payload['type'] = ContentPage::TYPE_NEWS;
            $payload['legacy_id'] = 0;
            $payload['created_at'] = now();
            $created = ContentPage::query()->create($payload);
            $created->legacy_id = $created->id;
            $created->save();
            $flash = 'Thêm tin tức thành công';
        }

        return redirect('/admin/posts')->with('message_success', $flash);
    }

    private function destroy(Request $request, int $id)
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        $post = $this->find($id);
        if (! $post) {
            return redirect('/admin/posts')->with('message_fail', 'Bài viết không tồn tại');
        }
        $image = (string) $post->image;
        $post->delete();
        $this->images->delete($image);

        return redirect('/admin/posts')->with('message_success', 'Xóa bài viết thành công');
    }

    private function find(int $id): ?ContentPage
    {
        if ($id <= 0) {
            return null;
        }

        return ContentPage::query()
            ->where('type', ContentPage::TYPE_NEWS)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('legacy_id', $id);
            })
            ->first();
    }
}
