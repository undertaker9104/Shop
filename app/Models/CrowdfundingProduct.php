<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrowdfundingProduct extends Model
{
    const STATUS_FUNDING = 'funding';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAIL = 'fail';

    public static $statusMap = [
        self::STATUS_FUNDING => '募資中',
        self::STATUS_SUCCESS => '募資成功',
        self::STATUS_FAIL => '募資失敗',
    ];

    protected $fillable = [
        'total_amount', 'target_amount', 'user_count', 'status', 'end_at'
    ];
    protected $dates = ['end_at'];
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getPerecntAttribute()
    {
        $value = $this->attributes['total_amount'] / $this->attributes['target_amount'];
        return floatval(number_format($value * 100,2,'.',''));
    }

}
