<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\Admin\UploadImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BannerController extends Controller
{
    public function __construct(private readonly UploadImage $images) {}

    public function index(): View
    {
        return view('admin.banner.index', [
            'list' => Banner::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        $banner = Banner::query()->find($id);
        if (! $banner) {
            return redirect('/admin/banner')->with('message_fail', 'Không tồn tại banner này');
        }
        if (! $request->isMethod('post')) {
            return view('admin.banner.edit', ['banner' => $banner]);
        }

        $name = trim((string) $request->input('name', ''));
        $link = trim((string) $request->input('link', ''));
        if ($name === '' || mb_strlen($name) > 255 || $link === '' || mb_strlen($link) > 255) {
            return back()->withInput()->with('message_fail', 'Tên và liên kết là bắt buộc, tối đa 255 ký tự.');
        }
        if (preg_match('/^\s*(javascript|data):/i', $link)) {
            return back()->withInput()->with('message_fail', 'Liên kết không hợp lệ.');
        }

        $data = ['name' => $name, 'link' => $link];
        if ($request->hasFile('image')) {
            try {
                $data['image_link'] = $this->images->store(
                    $request->file('image'),
                    UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . 'slider',
                    'b'
                );
            } catch (RuntimeException $e) {
                return back()->withInput()->with('message_fail', $e->getMessage());
            }
        }
        $banner->fill($data)->save();

        return redirect('/admin/banner')->with('message_success', 'Cập nhật dữ liệu thành công');
    }
}
