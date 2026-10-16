<?php

namespace App\Providers;

use App\View\Composers\AdminComposer;
use App\View\Composers\StorefrontComposer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
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
        // CI3 dung pagination Bootstrap 3 <ul class="pagination"><li>.
        Paginator::useBootstrapThree();

        // Du lieu dung chung cho toan bo trang storefront (port MY_Controller::__construct()).
        View::composer(StorefrontComposer::$views, StorefrontComposer::class);
        View::composer(AdminComposer::$views, AdminComposer::class);

        // Blade directive: {{ number_format }} tieng Viet (dau cham phan cach nghin).
        Blade::directive("money", function ($expression) {
            return "<?php echo number_format((float) ($expression), 0, ',', '.'); ?>";
        });
    }
}
