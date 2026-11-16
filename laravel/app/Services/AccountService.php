<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserPhone;
use App\Services\Account\AccountResult;
use App\Support\LegacyPassword;
use App\Support\Storefront\VnAddress;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Port User_address_model / User_phone_model / phần tài khoản của User.php.
 */
class AccountService
{
    public const ORDERS_PER_PAGE = 5;

    public function ensureLegacyBooks(User $user): void
    {
        $this->ensureLegacyAddress((int) $user->id, (string) $user->address);
        $this->ensureLegacyPhone((int) $user->id, (string) $user->phone);
    }

    public function phones(int $userId): Collection
    {
        return UserPhone::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    public function addresses(int $userId): Collection
    {
        return UserAddress::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    public function updateName(User $user, string $name): AccountResult
    {
        $name = trim($name);
        $user->name = $name;
        $user->save();

        return new AccountResult(true, 'Đã cập nhật họ tên.', '#jm-account-profile');
    }

    public function changePassword(User $user, string $oldPassword, string $newPassword): AccountResult
    {
        $verified = LegacyPassword::verify($oldPassword, (string) $user->password_hash);
        if (! $verified['verified']) {
            return new AccountResult(false, 'Mật khẩu hiện tại không đúng.', '#jm-account-profile');
        }

        $user->password_hash = LegacyPassword::hash($newPassword);
        $user->save();

        return new AccountResult(true, 'Đã đổi mật khẩu thành công.', '#jm-account-profile');
    }

    public function addPhone(User $user, string $number, string $label, bool $makeDefault): AccountResult
    {
        $userId = (int) $user->id;
        $count = UserPhone::query()->where('user_id', $userId)->count();
        if ($count >= UserPhone::MAX_PER_USER) {
            return new AccountResult(false, 'Bạn chỉ được lưu tối đa ' . UserPhone::MAX_PER_USER . ' số điện thoại.', '#phones');
        }

        $number = $this->normalizePhone($number);
        if ($this->findPhoneByNumber($userId, $number)) {
            return new AccountResult(false, 'Số điện thoại này đã có trong sổ của bạn.', '#phones');
        }

        if ($count === 0) {
            $makeDefault = true;
        }
        if ($makeDefault) {
            UserPhone::query()->where('user_id', $userId)->update(['is_default' => 0]);
        }

        UserPhone::query()->create([
            'user_id' => $userId,
            'phone_label' => trim($label),
            'phone_number' => $number,
            'is_default' => $makeDefault ? 1 : 0,
        ]);

        $this->syncPrimaryPhone($userId);
        $user->refresh();

        return new AccountResult(true, 'Đã thêm số điện thoại.', '#phones');
    }

    public function deletePhone(User $user, int $phoneId): AccountResult
    {
        $userId = (int) $user->id;
        if ($phoneId <= 0) {
            return new AccountResult(false, '', '');
        }

        $row = $this->ownedPhone($phoneId, $userId);
        if (! $row) {
            return new AccountResult(false, 'Không tìm thấy số điện thoại hoặc bạn không có quyền xóa.', '#phones');
        }

        $wasDefault = (int) $row->is_default === 1;
        $row->delete();

        if ($wasDefault) {
            $remaining = $this->phones($userId);
            if ($remaining->isNotEmpty()) {
                $this->setDefaultPhone($userId, (int) $remaining->first()->id);
            }
        }

        $this->syncPrimaryPhone($userId);
        $user->refresh();

        return new AccountResult(true, 'Đã xóa số điện thoại.', '#phones');
    }

    public function defaultPhone(User $user, int $phoneId): AccountResult
    {
        $userId = (int) $user->id;
        if ($phoneId <= 0 || ! $this->setDefaultPhone($userId, $phoneId)) {
            return new AccountResult(false, 'Không thể đặt số mặc định.', '#phones');
        }

        $this->syncPrimaryPhone($userId);
        $user->refresh();

        return new AccountResult(true, 'Đã cập nhật số điện thoại mặc định.', '#phones');
    }

    public function addAddress(
        User $user,
        string $provinceId,
        string $districtId,
        string $wardId,
        string $note,
        bool $makeDefault
    ): AccountResult {
        $userId = (int) $user->id;
        $count = UserAddress::query()->where('user_id', $userId)->count();
        if ($count >= UserAddress::MAX_PER_USER) {
            return new AccountResult(false, 'Bạn chỉ được lưu tối đa ' . UserAddress::MAX_PER_USER . ' địa chỉ giao hàng.', '');
        }

        $addressLine = VnAddress::formatLine($provinceId, $districtId, $wardId, $note);
        if ($addressLine === '') {
            return new AccountResult(false, 'Địa chỉ không hợp lệ. Vui lòng chọn lại tỉnh, quận, phường.', '#addresses');
        }

        if ($count === 0) {
            $makeDefault = true;
        }
        if ($makeDefault) {
            UserAddress::query()->where('user_id', $userId)->update(['is_default' => 0]);
        }

        UserAddress::query()->create([
            'user_id' => $userId,
            'address_note' => trim($note),
            'province_id' => trim($provinceId),
            'district_id' => trim($districtId),
            'ward_id' => trim($wardId),
            'address_line' => $addressLine,
            'is_default' => $makeDefault ? 1 : 0,
        ]);

        $this->syncPrimaryAddress($userId);
        $user->refresh();

        return new AccountResult(true, 'Đã thêm địa chỉ giao hàng.', '#addresses');
    }

    public function deleteAddress(User $user, int $addressId): AccountResult
    {
        $userId = (int) $user->id;
        if ($addressId <= 0) {
            return new AccountResult(false, '', '');
        }

        $row = $this->ownedAddress($addressId, $userId);
        if (! $row) {
            return new AccountResult(false, 'Không tìm thấy địa chỉ hoặc bạn không có quyền xóa.', '#addresses');
        }

        $wasDefault = (int) $row->is_default === 1;
        $row->delete();

        if ($wasDefault) {
            $remaining = $this->addresses($userId);
            if ($remaining->isNotEmpty()) {
                $this->setDefaultAddress($userId, (int) $remaining->first()->id);
            }
        }

        $this->syncPrimaryAddress($userId);
        $user->refresh();

        return new AccountResult(true, 'Đã xóa địa chỉ.', '#addresses');
    }

    public function defaultAddress(User $user, int $addressId): AccountResult
    {
        $userId = (int) $user->id;
        if ($addressId <= 0 || ! $this->setDefaultAddress($userId, $addressId)) {
            return new AccountResult(false, 'Không thể đặt địa chỉ mặc định.', '#addresses');
        }

        $this->syncPrimaryAddress($userId);
        $user->refresh();

        return new AccountResult(true, 'Đã cập nhật địa chỉ mặc định.', '#addresses');
    }

    /**
     * Đơn đã có user_id chỉ thuộc đúng tài khoản đó.
     * Email nhập lúc đặt hàng không phải bằng chứng sở hữu.
     * Đơn lịch sử user_id NULL không được gán theo email. Chỉ session
     * checkout (order_access_ids) của trình duyệt vừa đặt đơn mới mở được,
     * và không ghi user_id vào dòng lịch sử.
     *
     * @param  array<int|string, mixed>|null  $sessionAccess
     */
    public function ownedTransaction(User $user, int $transactionId, ?array $sessionAccess = null): ?Transaction
    {
        if ($transactionId <= 0) {
            return null;
        }

        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction) {
            return null;
        }

        $ownerId = (int) $transaction->user_id;
        if ($ownerId > 0) {
            return $ownerId === (int) $user->id ? $transaction : null;
        }

        if ($this->sessionOwnsUnassignedOrder($sessionAccess, $transactionId)) {
            return $transaction;
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>|null  $sessionAccess
     */
    private function sessionOwnsUnassignedOrder(?array $sessionAccess, int $transactionId): bool
    {
        if (! is_array($sessionAccess)) {
            return false;
        }

        return array_key_exists($transactionId, $sessionAccess)
            || array_key_exists((string) $transactionId, $sessionAccess);
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function paginateOrders(User $user, string $status, int $page): LengthAwarePaginator
    {
        $query = Transaction::query()->where('user_id', (int) $user->id);
        $status = self::orderStatus($status);
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $maxPage = max(1, (int) ceil($total / self::ORDERS_PER_PAGE));
        $page = max(1, $page);
        if ($page > $maxPage) {
            $page = $maxPage;
        }

        return $query
            ->orderByDesc('id')
            ->paginate(self::ORDERS_PER_PAGE, ['*'], 'order_page', $page)
            ->withQueryString()
            ->setPath(url('user'));
    }

    /**
     * @return list<object{order_id: int, product_id: int, name: string, image_link: string, qty: int, amount: float, size: mixed, color: mixed}>
     */
    public function orderLines(int $transactionId): array
    {
        $lines = OrderItem::query()
            ->with('product')
            ->where('order_id', $transactionId)
            ->get();

        $items = [];
        foreach ($lines as $line) {
            $product = $line->product;
            $items[] = (object) [
                'order_id' => (int) $line->id,
                'product_id' => (int) $line->product_id,
                'product' => $product,
                'name' => $product ? (string) $product->name : ((string) ($line->product_name !== '' ? $line->product_name : ('Sản phẩm #' . (int) $line->product_id))),
                'image_link' => $product ? (string) $product->image_link : '',
                'qty' => (int) $line->qty,
                'amount' => (float) $line->amount,
                'size' => $line->size,
                'color' => $line->color,
            ];
        }

        return $items;
    }

    public function refreshSessionUser(Session $session, User $user): void
    {
        $user->refresh();
        if (! $session->has('user')) {
            return;
        }

        $session->put('user', [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
        ]);
    }

    public function normalizePhone(mixed $phone): string
    {
        return preg_replace('/\s+/', '', trim((string) $phone)) ?? '';
    }

    private function ensureLegacyAddress(int $userId, string $legacyLine): void
    {
        if (UserAddress::query()->where('user_id', $userId)->exists()) {
            return;
        }
        $line = trim($legacyLine);
        if ($line === '') {
            return;
        }

        UserAddress::query()->create([
            'user_id' => $userId,
            'address_note' => '',
            'province_id' => '',
            'district_id' => '',
            'ward_id' => '',
            'address_line' => $line,
            'is_default' => 1,
        ]);
    }

    private function ensureLegacyPhone(int $userId, string $legacyPhone): void
    {
        if (UserPhone::query()->where('user_id', $userId)->exists()) {
            return;
        }
        $phone = trim($legacyPhone);
        if ($phone === '') {
            return;
        }

        UserPhone::query()->create([
            'user_id' => $userId,
            'phone_label' => '',
            'phone_number' => $phone,
            'is_default' => 1,
        ]);
    }

    private function ownedPhone(int $phoneId, int $userId): ?UserPhone
    {
        return UserPhone::query()->where('id', $phoneId)->where('user_id', $userId)->first();
    }

    private function ownedAddress(int $addressId, int $userId): ?UserAddress
    {
        return UserAddress::query()->where('id', $addressId)->where('user_id', $userId)->first();
    }

    private function findPhoneByNumber(int $userId, string $number): ?UserPhone
    {
        return UserPhone::query()->where('user_id', $userId)->where('phone_number', $number)->first();
    }

    private function setDefaultPhone(int $userId, int $phoneId): bool
    {
        if (! $this->ownedPhone($phoneId, $userId)) {
            return false;
        }
        UserPhone::query()->where('user_id', $userId)->update(['is_default' => 0]);
        UserPhone::query()->where('id', $phoneId)->update(['is_default' => 1]);

        return true;
    }

    private function setDefaultAddress(int $userId, int $addressId): bool
    {
        if (! $this->ownedAddress($addressId, $userId)) {
            return false;
        }
        UserAddress::query()->where('user_id', $userId)->update(['is_default' => 0]);
        UserAddress::query()->where('id', $addressId)->update(['is_default' => 1]);

        return true;
    }

    private function syncPrimaryPhone(int $userId): string
    {
        $list = $this->phones($userId);
        $phone = '';
        if ($list->isNotEmpty()) {
            $default = $list->first(fn (UserPhone $row) => (int) $row->is_default === 1);
            if ($default) {
                $phone = (string) $default->phone_number;
            } else {
                $phone = (string) $list->first()->phone_number;
                UserPhone::query()->where('user_id', $userId)->update(['is_default' => 0]);
                UserPhone::query()->where('id', $list->first()->id)->update(['is_default' => 1]);
            }
        }
        return $phone;
    }

    private function syncPrimaryAddress(int $userId): string
    {
        $list = $this->addresses($userId);
        $line = '';
        if ($list->isNotEmpty()) {
            $default = $list->first(fn (UserAddress $row) => (int) $row->is_default === 1);
            if ($default) {
                $line = (string) $default->address_line;
            } else {
                $line = (string) $list->first()->address_line;
                UserAddress::query()->where('user_id', $userId)->update(['is_default' => 0]);
                UserAddress::query()->where('id', $list->first()->id)->update(['is_default' => 1]);
            }
        }
        return $line;
    }

    private static function orderStatus(string $status): string
    {
        return match ($status) {
            '0', 'pending' => Transaction::STATUS_NEW,
            '1', 'confirmed' => Transaction::STATUS_CONFIRMED,
            '2', 'packed' => Transaction::STATUS_PACKED,
            '3', 'completed' => Transaction::STATUS_COMPLETED,
            '4', 'cancelled' => Transaction::STATUS_CANCELLED,
            default => '',
        };
    }
}
