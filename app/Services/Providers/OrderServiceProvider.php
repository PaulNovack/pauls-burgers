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
        /**
         * Storage for the in-progress order (session-backed by default).
         */
        $this->app->singleton(OrderRepository::class, function ($app) {
            return new SessionOrderRepository($app['session']);
        });

        /**
         * Menu repository: DB first, fall back to config.
         */
        $this->app->singleton(EloquentMenuRepository::class);
        $this->app->singleton(ConfigMenuRepository::class);

        $this->app->singleton(MenuRepository::class, function ($app) {
            return new CompoundMenuRepository(
                $app->make(EloquentMenuRepository::class),
                $app->make(ConfigMenuRepository::class)
            );
        });

        /**
         * Text / number utilities.
         */
        $this->app->singleton(TextNormalizer::class, TextNormalizerImpl::class);
        $this->app->singleton(NumberWordConverter::class);
        $this->app->singleton(NameMatcher::class, function ($app) {
            return new NameMatcher(
                $app->make(MenuRepository::class),
                $app->make(TextNormalizer::class)
            );
        });

        /**
         * Topping policy repository (category rules + modifier synonyms).
         * If you later add a DB-backed implementation, just swap this binding.
         */
        $this->app->singleton(ToppingPolicyRepository::class, function () {
            // Config-driven implementation that reads from config('menu.*')
            return new ConfigToppingPolicyRepository();
        });

        /**
         * Modifier resolver with OPTIONAL policy injection.
         * - In app/runtime: we pass the policy from the container.
         * - In tests that new() the resolver directly: it works with zero args.
         */
        $this->app->singleton(ModifierResolver::class, function ($app) {
            $policy = $app->bound(ToppingPolicyRepository::class)
                ? $app->make(ToppingPolicyRepository::class)
                : null;

            // DefaultModifierResolver accepts null and falls back to defaults.
            return new DefaultModifierResolver($policy);
        });

        /**
         * Parser / mutator / façade service.
         */
        $this->app->singleton(CommandParser::class, function ($app) {
            return new CommandParser(
                $app->make(TextNormalizer::class),
                $app->make(NumberWordConverter::class),
                $app->make(NameMatcher::class),
                $app->make(ModifierResolver::class)
            );
        });

        $this->app->singleton(OrderMutator::class, function ($app) {
            return new OrderMutator(
                $app->make(OrderRepository::class),
                $app->make(MenuRepository::class),
                $app->make(TextNormalizer::class),
                $app->make(ModifierResolver::class)
            );
        });

        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService(
                $app->make(OrderRepository::class),
                $app->make(CommandParser::class),
                $app->make(OrderMutator::class)
            );
        });
    }

    public function boot(): void
    {
        // no-op
    }
}
