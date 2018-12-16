@extends('layouts.app')
@section('title', '订单列表')

@section('content')
    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">
            <div class="panel panel-default">
                <div class="panel-heading">訂單列表</div>
                <div class="panel-body">
                    <ul class="list-group">
                        @foreach($orders as $order)
                            <li class="list-group-item">
                                <div class="panel panel-default">
                                    <div class="panel-heading">
                                        訂單號：{{ $order->no }}
                                        <span class="pull-right">{{ $order->created_at->format('Y-m-d H:i:s') }}</span>
                                    </div>
                                    <div class="panel-body">
                                        <table class="table">
                                            <thead>
                                            <tr>
                                                <th>商品信息</th>
                                                <th class="text-center">單價</th>
                                                <th class="text-center">數量</th>
                                                <th class="text-center">訂單總價</th>
                                                <th class="text-center">狀態</th>
                                                <th class="text-center">操作</th>
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
                                                    <td class="sku-price text-center">￥{{ $item->price }}</td>
                                                    <td class="sku-amount text-center">{{ $item->amount }}</td>
                                                    @if($index === 0)
                                                        <td rowspan="{{ count($order->items) }}" class="text-center total-amount">￥{{ $order->total_amount }}</td>
                                                        <td rowspan="{{ count($order->items) }}" class="text-center">
                                                            @if($order->paid_at)
                                                                @if($order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
                                                                    已支付
                                                                @else
                                                                    {{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}
                                                                @endif
                                                            @elseif($order->closed)
                                                                已關閉
                                                            @else
                                                                未支付<br>
                                                                請於 {{ $order->created_at->addSeconds(config('app.order_ttl'))->format('H:i') }} 前完成支付<br>
                                                                否則訂單將自動關閉
                                                            @endif
                                                        </td>
                                                        <td rowspan="{{ count($order->items) }}" class="text-center">
                                                            <a class="btn btn-primary btn-xs" href="{{route('orders.show',['order' => $order])}}">查看訂單</a>
                                                            <!-- 评价入口开始 -->
                                                            @if($order->paid_at)
                                                                <a class="btn btn-success btn-xs" href="{{ route('orders.review.show', ['order' => $order->id]) }}">
                                                                    {{ $order->reviewed ? '查看評價' : '評價' }}
                                                                </a>
                                                            @endif
                                                        <!-- 评价入口结束 -->
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="pull-right">{{ $orders->render() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection