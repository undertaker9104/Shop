<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
class ProductsController extends Controller
{
    public function index(Request $request){
        // 创建一个查询构造器
        $builder = Product::query()->where('on_sale',true);

        if($search = $request->input('search','')) {
            $like = '%'.$search.'%';
            // 模糊搜索商品标题、商品详情、SKU 标题、SKU描述
            $builder->where(function($query) use ($like){
               $query->where('title','like',$like)
                   ->orWhere('description','like',$like)
                   ->orWhereHas('skus',function($query) use ($like){
                       $query->where('title', 'like', $like)
                           ->orWhere('description', 'like', $like);
                   });
            });
        }

        if($order = $request->input('order','')) {
            if(preg_match('/^(.+)_(asc|desc)$/',$order,$m)){
                if(in_array($m[1],['price', 'sold_count','rating'])){
                    $builder->orderBy($m[1],$m[2]);
                }
            }
        }

        $products = $builder->paginate(16);

        return view('products.index',['products' => $products,
                                            'filters' => [
                                                'search' => $search,
                                                'order' => $order
                                            ]]);
    }
}
