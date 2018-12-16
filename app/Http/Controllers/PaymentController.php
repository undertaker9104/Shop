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
    public function payByPaypal(Order $order,Request $request)
    {
        $params = array(
            'cancelUrl' => route('payment.paypal.return'),
            'returnUrl' => route('payment.paypal.return'), // in your case             //  you have registered in the routes 'payment_success'
            'amount' => $order->total_amount,
            'order_id' => $order->id,
        );

        session()->put('params', $params); // here you save the params to the session so you can use them later.
        session()->save();

        $gateway = Omnipay::create('PayPal_Express');
        $gateway->setUsername('a9581987-faculty_api1.gmail.com'); // here you should place the email of the business sandbox account
        $gateway->setPassword('7N3MHMBC6V7KAR9H'); // here will be the password for the account
        $gateway->setSignature('AdCth9i9nL2TOOvMhWwrfSSFHhw9AWUM2ydvYBmoxOOituG3joC-jVmv'); // and the signature for the account
        $gateway->setTestMode(true); // set it to true when you develop and when you go to production to false
        $response = $gateway->purchase($params)->send(); // here you send details to PayPal

        if ($response->isRedirect()) {
            // redirect to offsite payment gateway
            $response->redirect();
        } else {
            // payment failed: display message to customer
            echo $response->getMessage();
        }
    }


    public function paypalReturn(){
        $gateway = Omnipay::create('PayPal_Express');
        $gateway->setUsername('a9581987-faculty_api1.gmail.com'); // here you should place the email of the business sandbox account
        $gateway->setPassword('7N3MHMBC6V7KAR9H'); // here will be the password for the account
        $gateway->setSignature('AdCth9i9nL2TOOvMhWwrfSSFHhw9AWUM2ydvYBmoxOOituG3joC-jVmv'); // and the signature for the account
        $gateway->setTestMode(true);
        $params = session()->get('params');
        $response = $gateway->completePurchase($params)->send();
        $paypalResponse = $response->getData(); // this is the raw response object
        $order = Order::find($params['order_id']);
        if(isset($paypalResponse['PAYMENTINFO_0_ACK']) && $paypalResponse['PAYMENTINFO_0_ACK'] === 'Success') {
            // here you process the response. Save to database ...
            $order->update([
                'paid_at'        => Carbon::now(), // 支付时间
                'payment_method' => 'paypal', // 支付方式
                'payment_no'     => $order->id, // 订单号
            ]);
            $this->afterPaid($order);
            return view('pages.success',['msg' => '付款成功']);
        }
        else {
            // Failed transaction ...
            return view('pages.error',['msg' => '數據不正確']);
        }
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
