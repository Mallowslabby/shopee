<?php

namespace Bhavinjr\Shoppingwishlist;

use Illuminate\Auth\Events\Logout;
use Illuminate\Session\SessionManager;
use Illuminate\Support\ServiceProvider;

class ShoppingwishlistServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('wishlist', 'Bhavinjr\Shoppingwishlist\Wishlist');

        $config = __DIR__ . '/../config/wishlist.php';
        $this->mergeConfigFrom($config, 'wishlist');

        $this->publishes([__DIR__ . '/../config/wishlist.php' => config_path('wishlist.php')], 'config');

        $this->app['events']->listen(Logout::class, function () {
            if ($this->app['config']->get('wishlist.destroy_on_logout')) {
                $this->app->make(SessionManager::class)->forget('wishlist');
            }
        });

        if ( ! class_exists('CreateShoppingwishlistTable')) {
            // Publish the migration
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__.'/../database/migrations/0000_00_00_000000_create_shoppingwishlist_table.php' => database_path('migrations/'.$timestamp.'_create_shoppingwishlist_table.php'),
            ], 'migrations');
        }
    }
}
