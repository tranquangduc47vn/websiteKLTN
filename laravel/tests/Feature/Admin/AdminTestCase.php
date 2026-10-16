<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Support\LegacyPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\LegacyMysqlTestCase;

abstract class AdminTestCase extends LegacyMysqlTestCase
{
    protected const PASSWORD = 'B8-Admin-Secret#1';

    /** @var list<int> */
    private array $adminIds = [];

    protected function makeAdmin(int $level = Admin::ROLE_ADMIN): Admin
    {
        $admin = Admin::query()->create([
            'name' => 'B8 Admin ' . $level,
            'email' => 'b8admin.' . Str::lower(Str::random(8)) . '@example.test',
            'password' => LegacyPassword::hash(self::PASSWORD),
            'level' => $level,
            'created' => time(),
        ]);
        $this->adminIds[] = (int) $admin->id;

        return $admin;
    }

    protected function tearDown(): void
    {
        if ($this->adminIds !== []) {
            DB::table('admin')->whereIn('id', $this->adminIds)->delete();
        }
        parent::tearDown();
    }
}
