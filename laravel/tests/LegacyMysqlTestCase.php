<?php

namespace Tests;

use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Feature test chạy trên MySQL thật của dự án (DB dùng chung với CI3).
 *
 * phpunit.xml ép sqlite :memory: nhưng schema legacy chỉ tồn tại trên MySQL, nên lớp này
 * đọc lại cấu hình DB từ .env. Mỗi test chạy trong transaction và rollback khi kết thúc:
 * KHÔNG dùng RefreshDatabase / migrate trên DB này.
 */
abstract class LegacyMysqlTestCase extends TestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        $app = parent::createApplication();

        $env = Dotenv::parse((string) file_get_contents($app->basePath('.env')));

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', array_merge(
            $app['config']->get('database.connections.mysql'),
            [
                'url' => null,
                'host' => $env['DB_HOST'] ?? '127.0.0.1',
                'port' => $env['DB_PORT'] ?? '3306',
                'database' => $env['DB_DATABASE'] ?? 'webshop',
                'username' => $env['DB_USERNAME'] ?? 'root',
                'password' => $env['DB_PASSWORD'] ?? '',
            ]
        ));
        $app['db']->purge('mysql');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL không kết nối được: ' . $e->getMessage());
        }
    }
}
