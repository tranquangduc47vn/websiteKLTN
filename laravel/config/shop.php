<?php

/*
|--------------------------------------------------------------------------
| B4 - Branding storefront (port từ CI3 config/branding.php)
|--------------------------------------------------------------------------
| Chứa cấu hình hiển thị công khai. KHÔNG chứa secret/key/credential.
| Nếu chưa cấu hình thì helper Shop::name() rơi về 'qD Design'.
*/

return [
    // Tên thương hiệu hiển thị trên site.
    'name' => env('SHOP_NAME', 'qD Design'),

    // Biến thể tên viết hoa (dùng cho header/footer).
    'name_upper' => env('SHOP_NAME_UPPER', ''),

    // Đường dẫn logo tương đối trong public/upload/.
    'logo' => env('SHOP_LOGO', 'logo.png'),

    // Ảnh sản phẩm CI3: {webshop}/upload/product (URL /upload/product/...).
    'product_upload_path' => env('SHOP_PRODUCT_UPLOAD_PATH', ''),

    // File địa giới hành chính (CI3 vn_address_helper.php).
    'vn_address_path' => env('SHOP_VN_ADDRESS_PATH', ''),

    // VietQR (CI3 Order::checkout_qr() đang hardcode — giữ nguyên giá trị mặc định).
    'vietqr' => [
        'bank_id' => env('SHOP_VIETQR_BANK', 'vietcombank'),
        'account_no' => env('SHOP_VIETQR_ACCOUNT', '1234567890'),
        'account_name' => env('SHOP_VIETQR_NAME', 'NGUYEN VAN A'),
    ],
];
