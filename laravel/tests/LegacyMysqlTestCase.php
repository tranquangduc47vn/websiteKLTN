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

    /** @var list<string> */
    private const PROTECTED_DATABASES = [
        'webshop',
        'webshop_legacy_pre_v2',
        'webshop_v2_migration',
        'webshop_v2_cutover',
    ];

    public function createApplication()
    {
        $app = parent::createApplication();

        $database = (string) (getenv('WEBSHOP_TEST_DATABASE') ?: ($_ENV['WEBSHOP_TEST_DATABASE'] ?? ''));
        if ($database === '' || in_array($database, self::PROTECTED_DATABASES, true)) {
            throw new \RuntimeException('WEBSHOP_TEST_DATABASE phải là database kiểm thử, không phải database được bảo vệ.');
        }

        $env = Dotenv::parse((string) file_get_contents($app->basePath('.env')));
        $fileDatabase = (string) ($env['DB_DATABASE'] ?? '');
        if ($database === $fileDatabase) {
            throw new \RuntimeException('Database kiểm thử không được trùng DB_DATABASE trong .env.');
        }

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', array_merge(
            $app['config']->get('database.connections.mysql'),
            [
                'url' => null,
                'host' => $env['DB_HOST'] ?? '127.0.0.1',
                'port' => $env['DB_PORT'] ?? '3306',
                'database' => $database,
                'username' => $env['DB_USERNAME'] ?? 'root',
                'password' => $env['DB_PASSWORD'] ?? '',
            ]
        ));
        $app['db']->purge('mysql');
        $app['db']->reconnect('mysql');

        $connected = (string) $app['db']->selectOne('select database() as name')->name;
        if ($connected !== $database) {
            throw new \RuntimeException('Kết nối test không khớp database đã chỉ định.');
        }

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

        $connected = (string) DB::selectOne('select database() as name')->name;
        $expected = (string) config('database.connections.mysql.database');
        if ($connected !== $expected || in_array($connected, self::PROTECTED_DATABASES, true)) {
            $this->fail('Từ chối chạy test trên database ' . $connected);
        }
    }
}
