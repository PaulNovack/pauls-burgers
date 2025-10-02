<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Services\Order\Contracts\{
    OrderRepository, MenuRepository, ModifierResolver, TextNormalizer
};
use App\Services\Order\Impl\{
    SessionOrderRepository, EloquentMenuRepository, ConfigMenuRepository,
    CompoundMenuRepository, DefaultModifierResolver
};
use App\Services\Order\{
    TextNormalizerImpl, OrderMutator, OrderService
};
use App\Services\Order\Parsing\{
    CommandParser, NumberWordConverter, NameMatcher
};

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Storage
        $this->app->bind(OrderRepository::class, SessionOrderRepository::class);

        // Menu (DB first, then config)
        $this->app->singleton(EloquentMenuRepository::class);
        $this->app->singleton(ConfigMenuRepository::class);
        $this->app->bind(MenuRepository::class, function ($app) {
            return new CompoundMenuRepository(
                $app->make(EloquentMenuRepository::class),
                $app->make(ConfigMenuRepository::class),
            );
        });

        // Parsing + helpers
        $this->app->singleton(ModifierResolver::class, DefaultModifierResolver::class);
        $this->app->singleton(TextNormalizer::class, TextNormalizerImpl::class);
        $this->app->singleton(NumberWordConverter::class);
        $this->app->singleton(NameMatcher::class, fn($app) =>
        new NameMatcher($app->make(MenuRepository::class), $app->make(TextNormalizer::class))
        );
        $this->app->singleton(CommandParser::class);
        $this->app->singleton(OrderMutator::class);
        $this->app->singleton(OrderService::class);
    }
}
