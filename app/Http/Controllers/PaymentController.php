<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Exceptions\InvalidRequestException;
use App\Events\OrderPaid;
use Carbon\Carbon;
use Omnipay\Omnipay;


class PaymentController extends Controller
{

    public function payByAlipay(Order $order,Request $request){
        $this->authorize('own',$order);
        if ($order->closed || $order->paid_at){
            throw new InvalidRequestException('訂單狀態不正確');
        }
        return app('alipay')->web([
            'out_trade_no' => $order->no,
            'total_amount' => $order->total_amount,
            'subject' => '支付 Laravel Shop 的訂單：'.$order->no,
        ]);
    }
    public function payByPaypal(Order $order,Request $request){

        $gateway = Omnipay::create('PayPal_Rest');
        $gateway->initialize(array(
            'clientId' => 'Aehr1z_51vtXjdFglQS-hF3NwGhHA8fNbvXe8-Vc6Dtw_kliduUGcKhqE6oFdZABCbtVyXdb1W1Qevla',
            'secret'   => 'EK0RS5VUHGVI4HIxa9pRXGPNrSBc23jMTMeHuEX6qoylxiHCkIjPCVLvYI4EnKqXHQv9m3nDmsrGnefC',
            'testMode' => true, // Or false when you are ready for live transactions
        ));
        // Do a purchase transaction on the gateway
        try {
            $transaction = $gateway->purchase(array(
                'amount' => $order->total_amount,
                'currency'      => 'USD',
                'description'   => 'This is a test purchase transaction.',
                'cancelUrl' => route('orders.show',['order'=>$order]),
                'returnUrl' => route('payment.paypal',['order'=>$order]),
            ));
            $response = $transaction->send();

            if($response->isSuccessful()){
                $order->update([
                    'paid_at'        => Carbon::now(), // 支付时间
                    'payment_method' => 'paypal', // 支付方式
                    'payment_no'     => $order->id, // 订单号
                ]);
                $this->afterPaid($order);
            }

        } catch (\Exception $e) {
            return view('pages.error',['msg' => '數據不正確']);
        }

        return view('pages.success',['msg' => '付款成功']);
    }





    public function alipayReturn(){
        try{
            app('alipay')->verify();
        }catch(\Exception $e){
            return view('pages.error',['msg' => '數據不正確']);
        }
        return view('pages.success',['msg' => '付款成功']);
    }

    public function alipayNotify(){
        $data = app('alipay')->verify();
        if(!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return app('alipay')->success();
        }
        $order = Order::where('no',$data->out_trade_no)->first();
        if (!$order) {
            return 'fail';
        }
        if ($order->paid_at) {
            // 返回数据给支付宝
            return app('alipay')->success();
        }
        $order->update([
            'paid_at'        => Carbon::now(), // 支付时间
            'payment_method' => 'alipay', // 支付方式
            'payment_no'     => $data->trade_no, // 支付宝订单号
        ]);
        $this->afterPaid($order);
        return app('alipay')->success();
    }


    protected function afterPaid(Order $order){
        event(new OrderPaid($order));
    }
}
