@extends('layouts.app')
@section('title', '查看訂單')

@section('content')
    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4>訂單詳情</h4>
                </div>
                <div class="panel-body">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>商品信息</th>
                            <th class="text-center">單價</th>
                            <th class="text-center">數量</th>
                            <th class="text-right item-amount">小計</th>
                        </tr>
                        </thead>
                        @foreach($order->items as $index => $item)
                            <tr>
                                <td class="product-info">
                                    <div class="preview">
                                        <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">
                                            <img src="{{ $item->product->image_url }}">
                                        </a>
                                    </div>
                                    <div>
            <span class="product-title">
               <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">{{ $item->product->title }}</a>
             </span>
                                        <span class="sku-title">{{ $item->productSku->title }}</span>
                                    </div>
                                </td>
                                <td class="sku-price text-center vertical-middle">￥{{ $item->price }}</td>
                                <td class="sku-amount text-center vertical-middle">{{ $item->amount }}</td>
                                <td class="item-amount text-right vertical-middle">￥{{ number_format($item->price * $item->amount, 2, '.', '') }}</td>
                            </tr>
                        @endforeach
                        <tr><td colspan="4"></td></tr>
                    </table>
                    <div class="order-bottom">
                        <div class="order-info">
                            <div class="line"><div class="line-label">收貨地址：</div><div class="line-value">{{ join(' ', $order->address) }}</div></div>
                            <div class="line"><div class="line-label">訂單備註：</div><div class="line-value">{{ $order->remark ?: '-' }}</div></div>
                            <div class="line"><div class="line-label">訂單編號：</div><div class="line-value">{{ $order->no }}</div></div>
                        </div>
                        <div class="order-summary text-right">
                            <div class="total-amount">
                                <span>訂單總價：</span>
                                <div class="value">￥{{ $order->total_amount }}</div>
                            </div>
                            <div>
                                <span>訂單狀態：</span>
                                <div class="value">
                                    @if($order->paid_at)
                                        @if($order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
                                            已支付
                                        @else
                                            {{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}
                                        @endif
                                    @elseif($order->closed)
                                        已關閉
                                    @else
                                        未支付
                                    @endif
                                </div>
                                <!-- 支付按钮开始 -->
                                @if(!$order->paid_at && !$order->closed)
                                    <div class="payment-buttons">
                                        <a class="btn btn-primary btn-sm" href="{{ route('payment.alipay', ['order' => $order->id]) }}">支付寶支付</a>
                                    </div>
                                    <form action="{{ route('payment.paypal',['order'=>$order->id]) }}" method="post">
                                        {{ csrf_field() }}
                                        <button type="submit" class="btn btn-success">
                                           Paypal支付
                                        </button>
                                    </form>
                                    <div id="paypal-button"></div>
                                @endif
                                <!-- 支付按钮结束 -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scriptsAfterJs')
    <script src="https://www.paypalobjects.com/api/checkout.js"></script>
    <script>
        paypal.Button.render({
            env: 'sandbox', // Or 'production'
            // Set up the payment:
            // 1. Add a payment callback
            payment: function(data, actions) {
                // 2. Make a request to your server
                return actions.request.post('/api/create-paypal')
                .then(function(res) {
                        // 3. Return res.id from the response
                    console.log(res);
                    console.log(res.id);
                        return res.id;
                    });
            },
            // Execute the payment:
            // 1. Add an onAuthorize callback
            onAuthorize: function(data, actions) {
                // 2. Make a request to your server
                return actions.request.post('/my-api/execute-payment/', {
                    paymentID: data.paymentID,
                    payerID:   data.payerID
                })
                    .then(function(res) {
                        // 3. Show the buyer a confirmation message.
                    });
            }
        }, '#paypal-button');
    </script>
@endsection