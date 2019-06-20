<?php namespace Vsb;

use Vsb\Facades\PaymentManager;

use Illuminate\Queue\QueueManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;


class TradePaymentsServiceProvider extends LaravelServiceProvider {
    protected $defer = true;// Delay initializing this service for good performance
    public function provides() {
        return [];
    }
    public function boot() {
        $this->registerEvents();
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'trade-payments');
    }
    public function registerEvents(){
        // $events = $this->app->make(Dispatcher::class);
        // $events->listen($event, $listener);
    }
    public function register() {
        $this->mergeConfigFrom(__DIR__.'/../config/trade-payments.php', 'trade-payments');
        $this->registerServices();
    }
    public function registerServices(){
        $this->app->singleton('vsb.payments', function ($app) {
            return new PaymentManager($app);
        });
    }
    public function checkDepends(){
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