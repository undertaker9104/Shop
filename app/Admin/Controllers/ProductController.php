<?php

namespace App\Admin\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class ProductController extends CommonProductsController
{
    use HasResourceActions;

    /**
     * Show interface.
     *
     * @param mixed   $id
     * @param Content $content
     * @return Content
     */
    public function show($id, Content $content)
    {
        return $content
            ->header('Detail')
            ->description('description')
            ->body($this->detail($id));
    }


    /**
     * Make a show builder.
     *
     * @param mixed   $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Product::findOrFail($id));

        $show->id('Id');
        $show->title('Title');
        $show->description('Description');
        $show->image('Image');
        $show->on_sale('On sale');
        $show->rating('Rating');
        $show->sold_count('Sold count');
        $show->review_count('Review count');
        $show->price('Price');
        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
    }

    public function getProductType()
    {
        // TODO: Implement getProductType() method.
        return Product::TYPE_NORMAL;
    }

    protected function customGrid(Grid $grid)
    {
        // TODO: Implement customGrid() method.
        $grid->model()->with(['category']);
        $grid->id('ID')->sortable();
        $grid->title('商品名稱');
        $grid->column('category.name','分類名稱');
        $grid->on_sale('已上架')->display(function($value){
            return $value? '是':'否';
        });
        $grid->rating('評分');
        $grid->sold_count('銷量');
        $grid->review_count('評論數');
        $grid->price('價格');
    }

    protected function customForm(Form $form)
    {
        // TODO: Implement customForm() method.
    }
}
