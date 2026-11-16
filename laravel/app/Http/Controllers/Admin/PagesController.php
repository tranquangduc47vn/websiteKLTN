<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Support\HtmlSanitizer;
use App\Support\Storefront\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PagesController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', [
            'list' => ContentPage::query()->where('type', ContentPage::TYPE_PAGE)->orderBy('id')->get(),
        ]);
    }

    public function add(Request $request): View|RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return view('admin.pages.form', ['page' => null]);
        }

        $title = trim((string) $request->input('title', ''));
        $slug = Slug::make((string) $request->input('slug', '')) ?: Slug::make($title);
        $content = HtmlSanitizer::clean((string) $request->input('content', ''));
        if ($title === '' || mb_strlen($title) > 255 || $slug === '' || trim(strip_tags($content)) === '') {
            return back()->withInput()->with('message_fail', 'Tiêu đề, slug và nội dung là bắt buộc.');
        }

        $created = ContentPage::query()->create([
            'type' => ContentPage::TYPE_PAGE,
            'legacy_id' => 0,
            'title' => $title,
            'slug' => $slug,
            'intro' => '',
            'content' => $content,
            'image' => '',
            'created_at' => now(),
        ]);
        $created->legacy_id = $created->id;
        $created->save();

        return redirect('/admin/pages')->with('message_success', 'Thêm trang mới thành công');
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        $page = $this->find($id);
        if (! $page) {
            return redirect('/admin/pages')->with('message_fail', 'Không tồn tại trang này');
        }
        if (! $request->isMethod('post')) {
            return view('admin.pages.form', ['page' => $page]);
        }

        $title = trim((string) $request->input('title', ''));
        $content = HtmlSanitizer::clean((string) $request->input('content', ''));
        if ($title === '' || mb_strlen($title) > 255 || trim(strip_tags($content)) === '') {
            return back()->withInput()->with('message_fail', 'Tiêu đề và nội dung là bắt buộc.');
        }
        $page->fill(['title' => $title, 'content' => $content])->save();

        return redirect('/admin/pages')->with('message_success', 'Cập nhật trang thành công');
    }

    private function find(int $id): ?ContentPage
    {
        if ($id <= 0) {
            return null;
        }

        return ContentPage::query()
            ->where('type', ContentPage::TYPE_PAGE)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('legacy_id', $id);
            })
            ->first();
    }
}
