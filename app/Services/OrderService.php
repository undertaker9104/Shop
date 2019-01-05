<?php
namespace App\Services;
use App\Models\CouponCode;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\ProductSku;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use Carbon\Carbon;
use Omnipay\Omnipay;
class OrderService
{
    public function store(User $user,UserAddress $address,$remark,$items,CouponCode $coupon = null){

        if($coupon) {
            $coupon->checkAvailable($user);
        }
        $order = \DB::transaction(function() use ($user,$address,$remark,$items,$coupon){
            $address->update(['last_used_at' => Carbon::now()]);

            $order = new Order([
               'address' => [
                   'address'       => $address->full_address,
                   'zip'           => $address->zip,
                   'contact_name'  => $address->contact_name,
                   'contact_phone' => $address->contact_phone,
               ],
                'remark' => $remark,
                'total_amount' => 0,
                'type' => Order::TYPE_NORMAL,
            ]);

            $order->user()->associate($user);
            $order->save();

            $totalAmount = 0;
            foreach($items as $data){
                $sku = ProductSku::find($data['sku_id']);
                $item = $order->items()->make([
                    'amount' => $data['amount'],
                    'price' => $sku->price,
                ]);

                $item->product()->associate($sku->product_id);
                $item->productSku()->associate($sku);
                $item->save();
                $totalAmount += $sku->price * $data['amount'];
                if ($sku->decreaseStock($data['amount']) <= 0) {
                    throw new InvalidRequestException('該商品庫存不足');
                }
            }

            if($coupon){
                // 总金额已经计算出来了，检查是否符合优惠券规则
                $coupon->checkAvailable($user,$totalAmount);
                // 把订单金额修改为优惠后的金额
                $totalAmount = $coupon->getAdjustedPrice($totalAmount);
                // 将订单与优惠券关联
                $order->couponCode()->associate($coupon);
                // 增加优惠券的用量，需判断返回值
                if ($coupon->changeUsed() <= 0) {
                    throw new CouponCodeUnavailableException('該優惠券已被兌完');
                }
            }

            // 更新订单总金额
            $order->update(['total_amount' => $totalAmount]);
            $skuIds = collect($items)->pluck('sku_id')->all();
            app(CartService::class)->remove($skuIds);

            return $order;
        });
        dispatch(new CloseOrder($order,config('app.order_ttl')));
        return $order;
    }

    // 新建一个 crowdfunding 方法用于实现众筹商品下单逻辑
    public function crowdfunding(User $user, UserAddress $address, ProductSku $sku, $amount)
    {
        // 开启事务
        $order = \DB::transaction(function () use ($amount, $sku, $user, $address) {
            // 更新地址最后使用时间
            $address->update(['last_used_at' => Carbon::now()]);
            // 创建一个订单
            $order = new Order([
                'address'      => [ // 将地址信息放入订单中
                    'address'       => $address->full_address,
                    'zip'           => $address->zip,
                    'contact_name'  => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark'       => '',
                'total_amount' => $sku->price * $amount,
                'type' => Order::TYPE_CROWDFUNDING,
            ]);
            // 订单关联到当前用户
            $order->user()->associate($user);
            // 写入数据库
            $order->save();
            // 创建一个新的订单项并与 SKU 关联
            $item = $order->items()->make([
                'amount' => $amount,
                'price'  => $sku->price,
            ]);
            $item->product()->associate($sku->product_id);
            $item->productSku()->associate($sku);
            $item->save();
            // 扣减对应 SKU 库存
            if ($sku->decreaseStock($amount) <= 0) {
                throw new InvalidRequestException('該商品庫存不足');
            }

            return $order;
        });

        // 众筹结束时间减去当前时间得到剩余秒数
        $crowdfundingTtl = $sku->product->crowdfunding->end_at->getTimestamp() - time();
        // 剩余秒数与默认订单关闭时间取较小值作为订单关闭时间
        dispatch(new CloseOrder($order, min(config('app.order_ttl'), $crowdfundingTtl)));

        return $order;
    }

    public function refundOrder(Order $order)
    {
        switch ($order->payment_method) {
            case 'wechat':
                // 生成退款订单号
                $refundNo = Order::getAvailableRefundNo();
                app('wechat_pay')->refund([
                    'out_trade_no' => $order->no, // 之前的订单流水号
                    'total_fee' => $order->total_amount * 100, //原订单金额，单位分
                    'refund_fee' => $order->total_amount * 100, // 要退款的订单金额，单位分
                    'out_refund_no' => $refundNo, // 退款订单号
                    // 微信支付的退款结果并不是实时返回的，而是通过退款回调来通知，因此这里需要配上退款回调接口地址
                    'notify_url' => 'http://requestbin.fullcontact.com/******' // 由于是开发环境，需要配成 requestbin 地址
                ]);
                // 将订单状态改成退款中
                $order->update([
                    'refund_no' => $refundNo,
                    'refund_status' => Order::REFUND_STATUS_PROCESSING,
                ]);
                break;
            case 'alipay':
                // 用我们刚刚写的方法来生成一个退款订单号
                $refundNo = Order::getAvailableRefundNo();
                // 调用支付宝支付实例的 refund 方法
                $ret = app('alipay')->refund([
                    'out_trade_no' => $order->no, // 之前的订单流水号
                    'refund_amount' => $order->total_amount, // 退款金额，单位元
                    'out_request_no' => $refundNo, // 退款订单号
                ]);
                // 根据支付宝的文档，如果返回值里有 sub_code 字段说明退款失败
                if ($ret->sub_code) {
                    // 将退款失败的保存存入 extra 字段
                    $extra = $order->extra;
                    $extra['refund_failed_code'] = $ret->sub_code;
                    // 将订单的退款状态标记为退款失败
                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_FAILED,
                        'extra' => $extra,
                    ]);
                } else {
                    // 将订单的退款状态标记为退款成功并保存退款订单号
                    $order->update([
                        'refund_no' => $refundNo,
                        'refund_status' => Order::REFUND_STATUS_SUCCESS,
                    ]);
                }
                break;
            default:
                // 原则上不可能出现，这个只是为了代码健壮性
                throw new InternalException('未知訂單支付方式：' . $order->payment_method);
                break;
            case 'paypal':

                $transaction_id = $order->extra['transaction_id'];
                $params = array(
                    'amount' => $order->total_amount,
                    'transactionReference' => $transaction_id,
                );
                $gateway = Omnipay::create('PayPal_Express');
                $gateway->setUsername('a9581987-faculty_api1@gmail.com'); // here you should place the email of the business sandbox account
                $gateway->setPassword('7N3MHMBC6V7KAR9H'); // here will be the password for the account
                $gateway->setSignature('AdCth9i9nL2TOOvMhWwrfSSFHhw9AWUM2ydvYBmoxOOituG3joC-jVmv'); // and the signature for the account
                $gateway->setTestMode(true); // set it to true when you develop and when you go to production to false
                $response = $gateway->refund($params)->send(); // here you send details to PayPal\
                    $order->update([
                        'refund_no' => $transaction_id,
                        'refund_status' => Order::REFUND_STATUS_SUCCESS,
                    ]);
                break;
        }
    }
}
