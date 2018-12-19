<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CouponCode extends Model
{
    // 用常量的方式定义支持的优惠券类型
    const TYPE_FIXED = 'fixed';
    const TYPE_PERCENT = 'percent';
    protected $appends = ['description'];
    public static $typeMap = [
        self::TYPE_FIXED   => '固定金额',
        self::TYPE_PERCENT => '比例',
    ];

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'total',
        'used',
        'min_amount',
        'not_before',
        'not_after',
        'enabled',
    ];
    protected $casts = [
        'enabled' => 'boolean',
    ];
    // 指明这两个字段是日期类型
    protected $dates = ['not_before', 'not_after'];

    public static function findAvailableCode($length=16){
        do {
            // 生成一个指定长度的随机字符串，并转成大写
            $code = strtoupper(Str::random($length));
            // 如果生成的码已存在就继续循环
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    public function getDescriptionAttribute(){
        $str = '';
        if($this->min_amount > 0){
            $str = '滿'.$this->min_amount;
        }
        if($this->type === self::TYPE_PERCENT){
            return $str.'優惠'.str_replace('.00','',$this->value).'%';
        }
        return $str.'減'.str_replace('.00','',$this->value);
    }

    public function checkAvailable($orderAmount =  null){
        if (!$this->enabled) {
            throw new CouponCodeUnavailableException('優惠券不存在');
        }

        if ($this->total - $this->used <= 0) {
            throw new CouponCodeUnavailableException('優惠券已經被兌完');
        }

        if ($this->not_before && $this->not_before->gt(Carbon::now())) {
            throw new CouponCodeUnavailableException('該優惠券現在還不能使用');
        }

        if ($this->not_after && $this->not_after->lt(Carbon::now())) {
            throw new CouponCodeUnavailableException('該優惠券已經過期');
        }

        if (!is_null($orderAmount) && $orderAmount < $this->min_amount) {
            throw new CouponCodeUnavailableException('訂單金額不滿足該優惠券最低金額');
        }
    }

    public function getAdjustedPrice($orderAmount){
        if ($this->type === self::TYPE_FIXED){
            return max(0.01,$orderAmount - $this->value);
        }
        return number_format($orderAmount * (100 - $this.value)/ 100,2,'.','');
    }

    public function changeUsed($increase = true){
        if ($increase) {
            return $this->newQuery()->where('id',$this->id)->where('used','<',$this->total)->increment('used');

        } else {
            return $this->decrement('used');
        }
    }
}
