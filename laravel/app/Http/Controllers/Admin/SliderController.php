<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Support\Admin\UploadImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SliderController extends Controller
{
    public function __construct(private readonly UploadImage $images) {}

    public function index(): View
    {
        return view('admin.slider.index', [
            'list' => Slider::query()->orderBy('id')->get(),
        ]);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), fn ($part) => $part !== ''));
        $action = $parts[0] ?? '';
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            'add' => $this->form($request, null),
            'edit' => $this->form($request, $this->find($id), true),
            'del', 'delete' => $this->destroy($request, $id),
            default => abort(404),
        };
    }

    private function form(Request $request, ?Slider $slider, bool $editing = false): View|RedirectResponse
    {
        if ($editing && ! $slider) {
            return redirect('/admin/slider')->with('message_fail', 'Slider không tồn tại');
        }
        if (! $request->isMethod('post')) {
            return view('admin.slider.form', ['slider' => $slider]);
        }

        $name = trim((string) $request->input('name', ''));
        $link = trim((string) $request->input('link', ''));
        $sort = (int) $request->input('sort_order', 1);
        if ($name === '' || mb_strlen($name) > 255 || $link === '' || mb_strlen($link) > 255) {
            return back()->withInput()->with('message_fail', 'Tên và liên kết là bắt buộc, tối đa 255 ký tự.');
        }
        if (preg_match('/^\s*(javascript|data):/i', $link)) {
            return back()->withInput()->with('message_fail', 'Liên kết không hợp lệ.');
        }

        $data = ['name' => $name, 'link' => $link, 'sort_order' => $sort];
        $directory = UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . 'slider';
        if ($request->hasFile('image')) {
            try {
                $data['image_link'] = $this->images->store($request->file('image'), $directory, 's');
            } catch (RuntimeException $e) {
                return back()->withInput()->with('message_fail', $e->getMessage());
            }
            if ($slider && $slider->image_link && ! Slider::query()->where('image_link', $slider->image_link)->where('id', '!=', $slider->id)->exists()) {
                $this->images->remove($directory, (string) $slider->image_link);
            }
        } elseif (! $slider) {
            $data['image_link'] = 'default.jpg';
        }

        if ($slider) {
            $slider->fill($data)->save();
            $flash = 'Thay đổi slider thành công';
        } else {
            $data['created_at'] = now();
            Slider::query()->create($data);
            $flash = 'Thêm slider thành công';
        }

        return redirect('/admin/slider')->with('message_success', $flash);
    }

    private function destroy(Request $request, int $id): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        $slider = $this->find($id);
        if (! $slider) {
            return redirect('/admin/slider')->with('message_fail', 'Slider không tồn tại');
        }
        $image = (string) $slider->image_link;
        $slider->delete();
        if ($image !== '' && ! Slider::query()->where('image_link', $image)->exists()) {
            $this->images->remove(UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . 'slider', $image);
        }

        return redirect('/admin/slider')->with('message_success', 'Xóa Slider thành công');
    }

    private function find(int $id): ?Slider
    {
        return $id > 0 ? Slider::query()->find($id) : null;
    }
}
