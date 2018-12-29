<?php

namespace App\Admin\Controllers;

use App\Models\Category;
use App\Models\CrowdfundingProduct;
use App\Models\Product;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;

class CrowdfundingProductsController extends CommonProductsController
{
    use HasResourceActions;


    public function getProductType()
    {
        // TODO: Implement getProductType() method.
        return Product::TYPE_CROWDFUNDING;
    }

    protected function customGrid(Grid $grid)
    {
        // TODO: Implement customGrid() method.
        $grid->id('ID')->sortable();
        $grid->title('商品名稱');
        $grid->on_sale('已上架')->display(function ($value) {
            return $value ? '是' : '否';
        });
        $grid->price('价格');
        // 展示众筹相关字段
        $grid->column('crowdfunding.target_amount', '目標金额');
        $grid->column('crowdfunding.end_at', '结束時間');
        $grid->column('crowdfunding.total_amount', '目前金额');
        $grid->column('crowdfunding.status', ' 狀態')->display(function ($value) {
            return CrowdfundingProduct::$statusMap[$value];
        });
    }

    protected function customForm(Form $form)
    {
        // TODO: Implement customForm() method.
        // 添加众筹相关字段
        $form->text('crowdfunding.target_amount', '募資目标金额')->rules('required|numeric|min:0.01');
        $form->datetime('crowdfunding.end_at', '募資结束时间')->rules('required|date');
    }
}