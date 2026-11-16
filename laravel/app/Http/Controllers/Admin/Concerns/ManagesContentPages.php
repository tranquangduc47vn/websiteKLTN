<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ContentPage;
use App\Support\Admin\UploadImage;
use App\Support\HtmlSanitizer;
use App\Support\Storefront\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

trait ManagesContentPages
{
    abstract protected function contentType(): string;

    abstract protected function contentBase(): string;

    abstract protected function contentHeading(): string;

    public function index(): View
    {
        return view('admin.content.index', [
            'heading' => $this->contentHeading(),
            'base' => $this->contentBase(),
            'list' => ContentPage::query()
                ->where('type', $this->contentType())
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function add(Request $request): View|RedirectResponse
    {
        return $this->save($request, null);
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        return $this->save($request, $this->findContent($id), true);
    }

    public function delete(Request $request, int $id): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        $page = $this->findContent($id);
        if (! $page) {
            return redirect($this->contentBase())->with('message_fail', 'Bản ghi không tồn tại');
        }
        $image = (string) $page->image;
        $page->delete();
        if ($image !== '' && ! ContentPage::query()->where('image', $image)->exists()) {
            app(UploadImage::class)->remove(UploadImage::uploadRoot(), $image);
        }

        return redirect($this->contentBase())->with('message_success', 'Xóa thành công');
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), fn ($part) => $part !== ''));
        $action = $parts[0] ?? '';
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            'add' => $this->add($request),
            'edit' => $this->edit($request, $id),
            'del', 'delete' => $this->delete($request, $id),
            default => abort(404),
        };
    }

    private function save(Request $request, ?ContentPage $page, bool $editing = false): View|RedirectResponse
    {
        if ($editing && ! $page) {
            return redirect($this->contentBase())->with('message_fail', 'Bản ghi không tồn tại');
        }
        if (! $request->isMethod('post')) {
            return view('admin.content.form', [
                'heading' => $this->contentHeading(),
                'base' => $this->contentBase(),
                'page' => $page,
            ]);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '' || mb_strlen($title) > 255) {
            return back()->withInput()->with('message_fail', 'Tiêu đề không được để trống và tối đa 255 ký tự.');
        }

        $image = (string) ($page->image ?? '');
        if ($request->hasFile('image')) {
            try {
                $stored = app(UploadImage::class)->store($request->file('image'), UploadImage::uploadRoot(), 'c', 2 * 1024 * 1024);
            } catch (RuntimeException $e) {
                return back()->withInput()->with('message_fail', $e->getMessage());
            }
            if ($image !== '' && ! ContentPage::query()->where('image', $image)->where('id', '!=', $page->id ?? 0)->exists()) {
                app(UploadImage::class)->remove(UploadImage::uploadRoot(), $image);
            }
            $image = $stored;
        }

        $payload = [
            'title' => $title,
            'slug' => Slug::make($title) ?: 'noi-dung',
            'intro' => (string) $request->input('intro', ''),
            'content' => HtmlSanitizer::clean((string) $request->input('content', '')),
            'image' => $image,
        ];

        if ($page) {
            $page->fill($payload)->save();
            $flash = 'Cập nhật thành công';
        } else {
            $created = ContentPage::query()->create($payload + [
                'type' => $this->contentType(),
                'legacy_id' => 0,
                'created_at' => now(),
            ]);
            $created->legacy_id = $created->id;
            $created->save();
            $flash = 'Thêm thành công';
        }

        return redirect($this->contentBase())->with('message_success', $flash);
    }

    private function findContent(int $id): ?ContentPage
    {
        if ($id <= 0) {
            return null;
        }

        return ContentPage::query()
            ->where('type', $this->contentType())
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('legacy_id', $id);
            })
            ->first();
    }
}
