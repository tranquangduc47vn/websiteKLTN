<?php

/*
| Cổng SePay — chỉ đọc từ môi trường. Không đặt secret thật trong file này.
*/

return [
    'enabled' => filter_var(env('SEPAY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'webhook_secret' => (string) env('SEPAY_WEBHOOK_SECRET', ''),
    'account_number' => (string) env('SEPAY_ACCOUNT_NUMBER', ''),
    'bank_code' => (string) env('SEPAY_BANK_CODE', ''),
    'account_name' => (string) env('SEPAY_ACCOUNT_NAME', ''),
];
