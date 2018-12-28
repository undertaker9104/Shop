<?php

namespace App\Admin\Controllers;

use App\Http\Requests\Request;
use App\Models\Category;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class CategoriesController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('商品分類列表')
            ->body($this->grid());
    }

    /**
     * Show interface.
     *
     * @param mixed $id
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
     * Edit interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header('編輯商品分類')
            ->body($this->form(true)->edit($id));
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        return $content
            ->header('創建商品分類')
            ->body($this->form(false));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Category);

        $grid->id('ID');
        $grid->name('名稱');
        $grid->is_directory('是否有子類')->display(function ($value){
            return $value? '是':'否';
        });
        $grid->level('層級');
        $grid->path('分類路徑');
        $grid->actions(function($actions){
            $actions->disableView();
        });
        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Category::findOrFail($id));

        $show->id('Id');
        $show->name('Name');
        $show->parent_id('Parent id');
        $show->is_directory('Is directory');
        $show->level('Level');
        $show->path('Path');
        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($isEditing = false)
    {
        $form = new Form(new Category);

        $form->text('name', '分類名稱');
        if ($isEditing) {
            $form->display('is_directory','是否有子類')->with(function($value){
               return $value?'是':'否';
            });

            $form->display('parent.name','父分類');
        } else {
            $form->radio('is_directory','是否有子類')
                ->options(['1' => '是', '0' => '否'])
                ->default('0')
                ->rules('required');

            $form->select('parent_id','父分類')->ajax('/admin/api/categories');
        }

        return $form;
    }

    public function apiIndex(Request $request) {
        $search = $request->input('q');
        $result = Category::query()->where('is_directory', boolval($request->input('is_directory',true)))
                                    ->where('name','like','%'.$search.'%')
                                    ->paginate();
        $result->setCollection($result->getCollection()->map(function(Category $category) {
            return ['id' => $category->id, 'text' => $category->full_name];
        }));
        return $result;
    }
}
