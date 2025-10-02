<?php

namespace App\Services\Providers;

use Illuminate\Support\ServiceProvider;

use App\Services\Order\Contracts\{
    OrderRepository,
    MenuRepository,
    ModifierResolver,
    TextNormalizer,
    ToppingPolicyRepository
};

use App\Services\Order\Impl\{
    SessionOrderRepository,
    EloquentMenuRepository,
    ConfigMenuRepository,
    CompoundMenuRepository,
    DefaultModifierResolver,
    ConfigToppingPolicyRepository
};

use App\Services\Order\{
    TextNormalizerImpl,
    OrderMutator,
    OrderService
};

use App\Services\Order\Parsing\{
    CommandParser,
    NumberWordConverter,
    NameMatcher
};

final class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Order storage
        $this->app->bind(OrderRepository::class, fn ($app) =>
        new SessionOrderRepository($app['session'])
        );

        // Menu (DB-first, config fallback)
        $this->app->singleton(EloquentMenuRepository::class);
        $this->app->singleton(ConfigMenuRepository::class);
        $this->app->bind(MenuRepository::class, CompoundMenuRepository::class);

        // ✅ Topping policies (config-backed)
        $this->app->singleton(ToppingPolicyRepository::class, ConfigToppingPolicyRepository::class);

        // Parsing & helpers
        $this->app->singleton(ModifierResolver::class, DefaultModifierResolver::class);
        $this->app->singleton(TextNormalizer::class, TextNormalizerImpl::class);
        $this->app->singleton(NumberWordConverter::class);
        $this->app->singleton(NameMatcher::class, function ($app) {
            return new NameMatcher(
                $app->make(MenuRepository::class),
                $app->make(TextNormalizer::class)
            );
        });
        $this->app->singleton(CommandParser::class);

        // Mutation & facade
        $this->app->singleton(OrderMutator::class);
        $this->app->singleton(OrderService::class);
    }

    public function boot(): void {}
}
