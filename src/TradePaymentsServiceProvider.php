<?php namespace Vsb;

use Vsb\Facades\PaymentManager;

// use Illuminate\Queue\QueueManager;
// use Illuminate\Contracts\Events\Dispatcher;
// use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;


class TradePaymentsServiceProvider extends LaravelServiceProvider {
    protected $defer = false;// Delay initializing this service for good performance
    public function provides() {
        return [];
    }
    public function boot() {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'trade-payments');
        
        $this->publishes([
            __DIR__.'/../config/trade-payments.php' => config_path('trade-payments.php'),
        ]);
        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/trade-payments'),
        ]);
    }
    public function register() {
        $this->mergeConfigFrom(__DIR__.'/../config/trade-payments.php', 'trade-payments');
        $this->app->singleton('vsb.payments', function ($app) {
            return new PaymentManager();
        });
    }
    protected function checkDepends(){
        $classes = [
            '\App\Account',
            '\App\Merchant',
            '\App\Transaction',
            '\App\User',
        ];
        foreach($classes as $class) if(!class_exists($class)) throw new \Exception( trans('trade-payments::depends.'.$class));
    }
}
?>
