<?php

namespace App\Providers;

use App\Contracts\Hcm\ThrDisbursementGatewayInterface;
use App\Support\WebsiteSettings;
use App\Services\Hcm\StubThrDisbursementGateway;
use App\Services\Media\AvatarStorageService;
use App\Services\Media\ImageProcessor;
use App\Services\Media\MediaFileDeleter;
use App\Services\Media\PolicyAttachmentStorageService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ThrDisbursementGatewayInterface::class, StubThrDisbursementGateway::class);

        $this->app->singleton(ImageManager::class, static fn () => ImageManager::gd());

        $this->app->singleton(ImageProcessor::class, static fn ($app) => new ImageProcessor(
            $app->make(ImageManager::class),
        ));

        $this->app->singleton(MediaFileDeleter::class, static fn () => new MediaFileDeleter);

        $this->app->singleton(PolicyAttachmentStorageService::class, static fn ($app) => new PolicyAttachmentStorageService(
            $app->make(ImageProcessor::class),
            $app->make(MediaFileDeleter::class),
        ));

        $this->app->singleton(AvatarStorageService::class, static fn ($app) => new AvatarStorageService(
            $app->make(ImageProcessor::class),
            $app->make(MediaFileDeleter::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view): void {
            // Wrap database access in try-catch to handle connection failures gracefully
            // This prevents error pages from crashing when database is down
            try {
                $localizationSettings = [
                    'language' => WebsiteSettings::localizationLanguage(),
                    'timezone' => WebsiteSettings::localizationTimezone(),
                    'dateFormat' => WebsiteSettings::localizationDateFormat(),
                    'timeFormat' => WebsiteSettings::localizationTimeFormat(),
                ];
            } catch (\Throwable $e) {
                // Fallback to defaults if database is unavailable
                $localizationSettings = [
                    'language' => 'en',
                    'timezone' => 'UTC',
                    'dateFormat' => 'Y-m-d',
                    'timeFormat' => 'H:i:s',
                ];
            }

            try {
                $businessSettings = [
                    'companyName' => WebsiteSettings::businessCompanyName(),
                    'email' => WebsiteSettings::businessEmail(),
                    'phone' => WebsiteSettings::businessPhone(),
                ];
            } catch (\Throwable $e) {
                // Fallback to defaults if database is unavailable
                $businessSettings = [
                    'companyName' => 'Arcav',
                    'email' => 'support@arcav.local',
                    'phone' => '+1-000-0000',
                ];
            }

            $view->with('runtimeLocalizationSettings', $localizationSettings);
            $view->with('runtimeBusinessSettings', $businessSettings);
        });
    }
}
