<?php

namespace App\Providers;

use App\Database\MigrationBlockedException;
use App\Database\MigrationSafetyGuard;
use App\View\Composers\AdminComposer;
use App\View\Composers\StorefrontComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (CommandStarting $event) {
            $reason = app(MigrationSafetyGuard::class)->blockReason($event->command);
            if ($reason !== null) {
                throw new MigrationBlockedException($reason);
            }
        });

        // CI3 dung pagination Bootstrap 3 <ul class="pagination"><li>.
        Paginator::useBootstrapThree();

        RateLimiter::for('auth-public', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(5)->by($request->ip() . '|' . $email);
        });

        RateLimiter::for('ai-send', function (Request $request) {
            $actor = (string) ($request->user('web')?->id ?? $request->ip());

            return Limit::perMinute(30)->by($actor);
        });

        // Du lieu dung chung cho toan bo trang storefront (port MY_Controller::__construct()).
        View::composer(StorefrontComposer::$views, StorefrontComposer::class);
        View::composer(AdminComposer::$views, AdminComposer::class);

        // Blade directive: {{ number_format }} tieng Viet (dau cham phan cach nghin).
        Blade::directive("money", function ($expression) {
            return "<?php echo number_format((float) ($expression), 0, ',', '.'); ?>";
        });
    }
}
