<?php namespace Simexis\Casset;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class CassetServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;
	
	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->publishes([
            __DIR__.'/config/config.php' => config_path('casset.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__.'/config/config.php', 'casset'
        );
		
		if ($route = $this->app['config']->get('casset.route')) {
			Route::get(trim($route, '/') . '/{type}', 'Simexis\Casset\Controllers\CassetController@getIndex');
		}
	}
	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton('casset', function ($app) {
			return new Casset;
		});
	}
	
	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('casset');
	}
}
