<?php

namespace App\Providers;

use App\Models\SeriesCatalogValue;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Observers\SeriesCatalogValueObserver;
use App\Observers\StampCatalogEntryObserver;
use App\Observers\StampCatalogValueObserver;
use App\Services\CatalogChangeHistoryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CatalogChangeHistoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        StampCatalogEntry::observe(StampCatalogEntryObserver::class);
        StampCatalogValue::observe(StampCatalogValueObserver::class);
        SeriesCatalogValue::observe(SeriesCatalogValueObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
