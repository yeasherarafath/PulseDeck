<?php

namespace App\Providers;

use App\Models\Status\StatusSetting;
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
        $this->applyAdminPrefix();
    }

    /**
     * The admin URL prefix is editable via settings (key: admin_prefix).
     * Guarded so artisan commands keep working on a fresh database
     * where the settings table does not exist yet.
     */
    private function applyAdminPrefix(): void
    {
        try {
            $prefix = trim((string) StatusSetting::get('admin_prefix', 'admin'), '/') ?: 'admin';
        } catch (\Throwable) {
            $prefix = 'admin';
        }

        config(['status.admin_prefix' => $prefix]);
        config(['fortify.home' => '/'.$prefix.'/status']);
    }
}
