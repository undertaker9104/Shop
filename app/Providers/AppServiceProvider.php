<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Yansongda\Pay\Pay;
use Carbon\Carbon;
use Elasticsearch\ClientBuilder as ESClientBuilder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 当 Laravel 渲染 products.index 和 products.show 模板时，就会使用 CategoryTreeComposer 这个来注入类目树变量
        // 同时 Laravel 还支持通配符，例如 products.* 即代表当渲染 products 目录下的模板时都执行这个 ViewComposer
        \View::composer(['products.index', 'products.show'], \App\Http\ViewComposers\CategoryTreeComposer::class);
        Carbon::setLocale('zh_TW');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('alipay',function(){
            $config = config('pay.alipay');
            $config['notify_url'] = 'http://requestbin.leo108.com/zbnqqpzb';
            $config['return_url'] = route('payment.alipay.return');
            if(app()->environment() !== 'production'){
                $config['mode'] = 'dev';
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['levet'] = Logger::WARNING;
            }
            return Pay::alipay($config);
        });

        $this->app->singleton('wechat_pay', function () {
            $config = config('pay.wechat');
            if (app()->environment() !== 'production') {
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            // 调用 Yansongda\Pay 来创建一个微信支付对象
            return Pay::wechat($config);
        });

        $this->app->singleton('es', function () {
            // 从配置文件读取 Elasticsearch 服务器列表
            $builder = ESClientBuilder::create()->setHosts(config('database.elasticsearch.hosts'));

            return $builder->build();
        });
    }
}
