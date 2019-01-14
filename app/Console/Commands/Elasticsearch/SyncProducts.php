<?php

namespace App\Console\Commands\Elasticsearch;

use Illuminate\Console\Command;
use App\Models\Product;

class SyncProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '將商品數據同步到elasticsearch';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //獲取elasticsearch對象
        $es = app('es');

        Product::query()->with(['skus','properties'])
                        //使用chunkById 避免一次性載入太多數據
                        ->chunkById(100,function($products) use($es) {
                            $this->info(sprintf('正在同步ID為範圍為 %s 至 %s 的商品',$products->first()->id,$products->last()->id));
                            $req = ['body' => []];
                            foreach($products as $product) {
                                $data = $product->toESArray();
                                $req['body'][] = [
                                    'index' => [
                                        '_index' => 'products',
                                        '_type'  => '_doc',
                                        '_id'    => $data['id'],
                                    ],
                                ];
                                $req['body'][] = $data;
                            }

                            try{
                                $es->bulk($req);
                            }catch(\Exception $e) {
                                $this->error($e->getMessage());
                            }
                        });

            $this->info('同步完成');
    }
}
