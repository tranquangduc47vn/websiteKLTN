<?php

use App\Auth\LegacyPasswordUserProvider;
use App\Models\Admin;
use App\Models\User;

return [
    "defaults" => [
        "guard" => env("AUTH_GUARD", "web"),
        "passwords" => env("AUTH_PASSWORD_BROKER", "users"),
    ],
    "guards" => [
        "web" => [
            "driver" => "session",
            "provider" => "users",
        ],
        "admin" => [
            "driver" => "session",
            "provider" => "admins",
        ],
    ],
    "providers" => [
        "users" => [
            "driver" => "legacy_eloquent",
            "model" => User::class,
        ],
        "admins" => [
            "driver" => "legacy_eloquent",
            "model" => Admin::class,
        ],
    ],
    "passwords" => [
        "users" => [
            "provider" => "users",
            "table" => "password_resets",
            "expire" => 15,
            "throttle" => 60,
        ],
    ],
    "password_timeout" => env("AUTH_PASSWORD_TIMEOUT", 10800),
];
