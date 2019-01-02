@extends('layouts.app')
@section('title', '分期付款列表')

@section('content')
    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">
            <div class="panel panel-default">
                <div class="panel-heading text-center"><h2>分期付款列表</h2></div>
                <div class="panel-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                        <tr>
                            <th>編號</th>
                            <th>金額</th>
                            <th>期數</th>
                            <th>費率</th>
                            <th>狀態</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($installments as $installment)
                            <tr>
                                <td>{{ $installment->no }}</td>
                                <td>￥{{ $installment->total_amount }}</td>
                                <td>{{ $installment->count }}</td>
                                <td>{{ $installment->fee_rate }}%</td>
                                <td>{{ \App\Models\Installment::$statusMap[$installment->status] }}</td>
                                <td><a class="btn btn-primary btn-xs" href="">查看</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="pull-right">{{ $installments->render() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection