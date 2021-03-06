<?php

namespace App\Http\Controllers;

use App\Exceptions\CouponCodeUnavailableException;
use App\Http\Requests\Admin\HandleRefundRequest;
use App\Http\Requests\ApplyRefundRequest;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\Request;
use App\Jobs\CloseOrder;
use App\Models\CouponCode;
use App\Models\OrderItem;
use App\Models\ProductSku;
use App\Models\UserAddress;
use App\Models\Order;
use Carbon\Carbon;
use App\Services\CartService;
use App\Services\OrderService;
use App\Http\Requests\SendReviewRequest;
use App\Events\OrderReviewed;
use App\Http\Requests\CrowdFundingOrderRequest;
use Omnipay\Common\Exception\InvalidRequestException;

class OrdersController extends Controller
{
    public function store(OrderRequest $request, OrderService $orderService)
    {
        $user  = $request->user();
        $address = UserAddress::find($request->input('address_id'));
        $coupon = null;

        if ($code = $request->input('coupon_code')){
            $coupon = CouponCode::where('code',$code)->first();
            if(!$coupon){
                throw new CouponCodeUnavailableException('優惠券不存在');
            }
        }

        return $orderService->store($user,$address,$request->input('remark'),$request->input('items'),$coupon);
    }

    public function index(Request $request){
        $orders = Order::query()->with(['items.product', 'items.productSku'])
                                ->where('user_id',$request->user()->id)
                                ->orderBy('created_at','desc')
                                ->paginate();
        return view('orders.index',['orders' => $orders]);
    }

    public function show(Order $order, Request $request){
        $this->authorize('own',$order);
        return view('orders.show',['order' => $order->load(['items.product','items.productSku'])]);
    }

    public function received(Order $order,Request $request){
        $this->authorize('own', $order);
        if ($order->ship_status !== Order::SHIP_STATUS_DELIVERED) {
            throw new InvalidRequestException('發貨狀態不正確');
        }
        // 更新发货状态为已收到
        $order->update(['ship_status' => Order::SHIP_STATUS_RECEIVED]);
        // 返回原页面
        return $order;
    }

    public function review(Order $order,Request $request){
        // 校验权限
        $this->authorize('own', $order);
        // 判断是否已经支付
        if (!$order->paid_at) {
            throw new InvalidRequestException('該訂單未支付,不可評價');
        }
        // 使用 load 方法加载关联数据，避免 N + 1 性能问题
        return view('orders.review', ['order' => $order->load(['items.productSku', 'items.product'])]);
    }
    public function sendReview(Order $order, SendReviewRequest $request){
        // 校验权限
        $this->authorize('own', $order);
        if (!$order->paid_at) {
            throw new InvalidRequestException('該訂單未支付,不可評價');
        }
        // 判断是否已经评价
        if ($order->reviewed) {
            throw new InvalidRequestException('該訂單已評價');
        }
        $reviews = $request->input('reviews');
        \DB::transaction(function() use ($order,$reviews){
           foreach($reviews as $review){
               $orderItem = $order->items()->find($review['id']);
               $orderItem->update([
                   'rating' => $review['rating'],
                   'review'      => $review['review'],
                   'reviewed_at' => Carbon::now(),
               ]);
           }
            // 将订单标记为已评价
            $order->update(['reviewed' => true]);
            event(new OrderReviewed($order));
        });
        return redirect()->back();
    }

    public  function  applyRefund(Order $order,ApplyRefundRequest $request){
        // 校验订单是否属于当前用户
        $this->authorize('own', $order);
        // 判断订单是否已付款
        if (!$order->paid_at) {
            throw new InvalidRequestException('該訂單已支付,不可退款');
        }
        // 判断订单退款状态是否正确
        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            throw new InvalidRequestException('該訂單已經申請過退款');
        }
        // 判斷不是募資訂單
        if ($order->type === Order::TYPE_CROWDFUNDING) {
            throw new InvalidRequestException('募資訂單不支持退款');
        }
        // 将用户输入的退款理由放到订单的 extra 字段中
        $extra= $order->extra ?: [];
        $extra['refund_reason'] = $request->input('reason');
        // 将订单退款状态改为已申请退款
        $order->update([
            'refund_status' => Order::REFUND_STATUS_APPLIED,
            'extra'         => $extra,
        ]);

        return $order;
    }

    // 创建一个新的方法用于接受众筹商品下单请求
    public function crowdfunding(CrowdFundingOrderRequest $request, OrderService $orderService)
    {
        $user    = $request->user();
        $sku     = ProductSku::find($request->input('sku_id'));
        $address = UserAddress::find($request->input('address_id'));
        $amount  = $request->input('amount');

        return $orderService->crowdfunding($user, $address, $sku, $amount);
    }

}