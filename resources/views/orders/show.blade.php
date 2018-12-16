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
                            <!-- 输出物流状态 -->
                            <div class="line">
                                <div class="line-label">物流狀態：</div>
                                <div class="line-value">{{ \App\Models\Order::$shipStatusMap[$order->ship_status] }}</div>
                            </div>
                            <!-- 如果有物流信息则展示 -->
                            @if($order->ship_data)
                                <div class="line">
                                    <div class="line-label">物流信息：</div>
                                    <div class="line-value">{{ $order->ship_data['express_company'] }} {{ $order->ship_data['express_no'] }}</div>
                                </div>
                            @endif
                        </div>
                        <div class="order-summary text-right">
                            <div class="total-amount">
                                <span>訂單總價：</span>
                                <div class="value">￥{{ $order->total_amount }}</div>
                            </div>
                            <div>
                                <span>訂單狀態：</span>
                                <div class="value">
                                    @if($order->paid_at && !$order->closed)
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
                                    <!-- 如果订单的发货状态为已发货则展示确认收货按钮 -->
                                        @if($order->ship_status === \App\Models\Order::SHIP_STATUS_DELIVERED)
                                            <div class="receive-button">
                                                <!-- 将原本的表单替换成下面这个按钮 -->
                                                <button type="button" id="btn-receive" class="btn btn-sm btn-success">確認收貨</button>
                                            </div>
                                        @endif
                                </div>
                                <!-- 支付按钮开始 -->
                                @if(!$order->paid_at && !$order->closed)
                                    {{--<div class="payment-buttons">--}}
                                        {{--<a class="btn btn-primary btn-sm" href="{{ route('payment.alipay', ['order' => $order->id]) }}">支付寶支付</a>--}}
                                    {{--</div>--}}
                                    <form action="{{ route('payment.paypal',['order'=>$order->id]) }}" method="post">
                                        {{ csrf_field() }}
                                        <button type="submit" class="btn btn-success">
                                           Paypal支付
                                        </button>
                                    </form>
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
    <script>
        $(document).ready(function() {
            // 确认收货按钮点击事件
            $('#btn-receive').click(function() {
                // 弹出确认框
                swal({
                    title: "確認已經收到商品？",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                    buttons: ['取消', '確認收到'],
                })
                    .then(function(ret) {
                        // 如果点击取消按钮则不做任何操作
                        if (!ret) {
                            return;
                        }
                        // ajax 提交确认操作
                        axios.post('{{ route('orders.received', [$order->id]) }}')
                            .then(function () {
                                // 刷新页面
                                location.reload();
                            })
                    });
            });

        });
    </script>
@endsection