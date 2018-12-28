<?php

namespace App\Http\Controllers;
use App\Exceptions\InvalidRequestException;
use App\Models\Category;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Models\Product;
use mysql_xdevapi\Exception;
use App\Services\CategoryService;
class ProductsController extends Controller
{
    public function index(Request $request, CategoryService $categoryService){
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

        if ($request->input('category_id') && $category = Category::find($request->input('category_id'))) {
            // 如果这是一个父类目
            if ($category->is_directory) {
                // 则筛选出该父类目下所有子类目的商品
                $builder->whereHas('category', function ($query) use ($category) {
                    // 这里的逻辑参考本章第一节
                    $query->where('path', 'like', $category->path.$category->id.'-%');
                });
            } else {
                // 如果这不是一个父类目，则直接筛选此类目下的商品
                $builder->where('category_id', $category->id);
            }
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
                                                'order' => $order,
                                            ],
                                            'category' => $category ?? null,
                                            'categoryTree' => $categoryService->getCategoryTree(),
                                            ]);
    }

    public function show(Request $request, Product $product) {
        if(!$product->on_sale) {
            throw new InvalidRequestException('商品未上架');
        }
        $favored = false;

        if($user = $request->user()) {
            $favored = boolval($user->favoriteProducts()->find($product->id));
        }
        $reviews = OrderItem::query()->with(['order.user','productSku'])
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at')
            ->orderBy('reviewed_at','desc')
            ->limit(10)
            ->get();
        return view('products.show',['product' => $product, 'favored' => $favored, 'reviews' => $reviews]);
    }

    public function favor(Product $product, Request $request) {
        $user = $request->user();
        if($user->favoriteProducts()->find($product->id)){
            return [];
        }
        $user->favoriteProducts()->attach($product);
        return [];
    }

    public function disfavor(Product $product, Request $request) {
        $user = $request->user();
        $user->favoriteProducts()->detach($product);
        return [];
    }

    public function favorites(Request $request) {
        $products = $request->user()->favoriteProducts()->paginate(16);
        return view('products.favorites', ['products' => $products]);
    }
}
