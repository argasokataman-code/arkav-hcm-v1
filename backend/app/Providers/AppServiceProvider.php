<?php

namespace App\Providers;

use App\Contracts\Hcm\ThrDisbursementGatewayInterface;
use App\Services\Hcm\StubThrDisbursementGateway;
use App\Services\Media\AvatarStorageService;
use App\Services\Media\ImageProcessor;
use App\Services\Media\MediaFileDeleter;
use App\Services\Media\PolicyAttachmentStorageService;
use Illuminate\Support\ServiceProvider;
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
        //
    }
}
