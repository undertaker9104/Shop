<?php

use Illuminate\Database\Seeder;
use App\Models\Category;
class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $categories = [
            [
                'name'     => '手機配件',
                'children' => [
                    ['name' => '手機殼'],
                    ['name' => '貼模'],
                    ['name' => '存儲卡'],
                    ['name' => '數據線'],
                    ['name' => '充電器'],
                    [
                        'name'     => '耳機',
                        'children' => [
                            ['name' => '有線耳機'],
                            ['name' => '藍芽耳機'],
                        ],
                    ],
                ],
            ],
            [
                'name'     => '電腦配件',
                'children' => [
                    ['name' => '顯示器'],
                    ['name' => '顯示卡'],
                    ['name' => '内存'],
                    ['name' => 'CPU'],
                    ['name' => '主機板'],
                    ['name' => '硬碟'],
                ],
            ],
            [
                'name'     => '電腦主機',
                'children' => [
                    ['name' => '筆記本'],
                    ['name' => '台式機'],
                    ['name' => '平板電腦'],
                    ['name' => '一體機'],
                    ['name' => '服務器'],
                    ['name' => '工作站'],
                ],
            ],
            [
                'name'     => '手機通訊',
                'children' => [
                    ['name' => '智能機'],
                    ['name' => '老人機'],
                    ['name' => '對講機'],
                ],
            ],
        ];

        foreach ($categories as $data) {
            $this->createCategory($data);
        }
    }

    public function createCategory($data, $parent = null) {
        $category = new Category(['name' => $data['name']]);
        $category->is_directory = isset($data['children']);

        if (!is_null($parent)) {
            $category->parent()->associate($parent);
        }
        $category->save();

        if (isset($data['children']) && is_array($data['children'])) {
            foreach ($data['children'] as $child) {
                $this->createCategory($child, $category);
            }
        }
    }
}
