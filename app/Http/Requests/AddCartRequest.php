<?php

namespace App\Http\Requests;

use App\Models\ProductSku;
class AddCartRequest extends Request
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'sku_id' => [
                'required',
                function($attribute, $value, $fail){
                    if(!$sku = ProductSku::find($value)){
                        $fail('該商品不存在');
                        return;
                    }
                    if(!$sku->product->on_sale){
                        $fail('該商品未上架');
                        return;
                    }
                    if($sku->stock === 0){
                        $fail('該商品已售完');
                        return;
                    }
                    if($this->input('amount') > 0 && $sku->stock < $this->input('amount')) {
                        $fail('該商品庫存不足');
                        return;
                    }
                }
            ],
            'amount' => ['required','integer','min:1'],
        ];
    }

    public function  attributes(){
        return[
            'amount' => '商品數量'
        ];
    }

    public function messages(){
        return[
            'sku_id.required' => '請選擇商品'
        ];
    }
}
