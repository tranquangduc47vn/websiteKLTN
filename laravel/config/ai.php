<?php

return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'gemini_api_key' => env('GEMINI_API_KEY', ''),
    'gemini_model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
    'gemini_endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
    'timeout' => (int) env('GEMINI_TIMEOUT', 10),
    'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 1024),
    'temperature' => (float) env('GEMINI_TEMPERATURE', 0.4),
    'rate_limit_max' => 8,
    'rate_limit_window' => 30,
    'history_size' => 6,
    'faq_threshold' => 0.45,
    'message_max' => 500,
    'poll_interval_ms' => 1500,
    'quick_replies' => [
        'Tìm sản phẩm',
        'Tư vấn size',
        'Tra cứu đơn hàng',
        'Khuyến mãi',
        'Gặp nhân viên',
    ],
    'welcome_intro' => "Mình có thể hỗ trợ:\n• Tìm sản phẩm\n• Tư vấn size\n• Gợi ý theo ngân sách\n• Kiểm tra đơn hàng\n• Chính sách đổi trả\n• Voucher\n\nNếu cần mình sẽ kết nối bạn với nhân viên.",

    'product_description' => [
        'rate_limit_max' => 5,
        'rate_limit_window' => 60,
        'max_images' => (int) env('GEMINI_PRODUCT_DESC_MAX_IMAGES', 2),
        'max_image_bytes' => 5 * 1024 * 1024,
        'max_image_edge' => 1280,
        'optimize_target_bytes' => 450_000,
        'timeout' => (int) env('GEMINI_PRODUCT_DESC_TIMEOUT', 120),
        'max_output_tokens' => 2048,
        'temperature' => 0.45,
    ],
];
