@extends('layouts.app')
@section('title','提示')

@section('content')
    <div class="panel panel-default">
        <div class="panel-heading">
            提示
        </div>
        <div class="panel-body text-center">
            <h1>請先驗證信箱</h1>
            <a class="btn btn-primary" href="{{ route('email_verification.send') }}">重新發送驗證郵件</a>
        </div>
    </div>
@endsection