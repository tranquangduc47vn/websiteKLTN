<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreController extends Controller
{
    private const CITIES = ['Hà Nội', 'TP. Hồ Chí Minh'];

    public function index(): View
    {
        return view('admin.stores.index', [
            'list' => Store::query()->orderBy('id')->get(),
        ]);
    }

    public function add(Request $request): View|RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return view('admin.stores.form', ['store' => null, 'cities' => self::CITIES]);
        }
        $data = $this->validated($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        Store::query()->create($data);

        return redirect('/admin/store')->with('message_success', 'Thêm mới cửa hàng thành công');
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        $store = Store::query()->find($id);
        if (! $store) {
            return redirect('/admin/store')->with('message_fail', 'Không tồn tại cửa hàng này');
        }
        if (! $request->isMethod('post')) {
            $cities = self::CITIES;
            if ($store->city && ! in_array($store->city, $cities, true)) {
                $cities[] = (string) $store->city;
            }

            return view('admin.stores.form', ['store' => $store, 'cities' => $cities]);
        }
        $data = $this->validated($request, (string) $store->city);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $store->fill($data)->save();

        return redirect('/admin/store')->with('message_success', 'Cập nhật thông tin thành công');
    }

    public function delete(Request $request, int $id): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        $store = Store::query()->find($id);
        if ($store) {
            $store->delete();

            return redirect('/admin/store')->with('message_success', 'Xóa cửa hàng thành công');
        }

        return redirect('/admin/store');
    }

    /**
     * @return array<string, string>|RedirectResponse
     */
    private function validated(Request $request, string $currentCity = ''): array|RedirectResponse
    {
        $name = trim((string) $request->input('name', ''));
        $address = trim((string) $request->input('address', ''));
        $phone = trim((string) $request->input('phone', ''));
        $city = trim((string) $request->input('city', ''));
        $map = trim((string) $request->input('map_link', ''));
        $allowed = self::CITIES;
        if ($currentCity !== '' && ! in_array($currentCity, $allowed, true)) {
            $allowed[] = $currentCity;
        }
        if ($name === '' || mb_strlen($name) > 255 || $address === '' || mb_strlen($address) > 500 || ! in_array($city, $allowed, true)) {
            return back()->withInput()->with('message_fail', 'Tên, địa chỉ và tỉnh thành là bắt buộc.');
        }
        if (mb_strlen($phone) > 30) {
            return back()->withInput()->with('message_fail', 'Số điện thoại quá dài.');
        }
        if ($map !== '' && ! preg_match('#^https://#i', $map)) {
            return back()->withInput()->with('message_fail', 'Link bản đồ phải là URL https.');
        }

        return [
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'city' => $city,
            'map_link' => $map,
        ];
    }
}
