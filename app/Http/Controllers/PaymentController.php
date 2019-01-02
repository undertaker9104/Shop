<?php

namespace App\Http\Controllers;

use App\Jobs\RefundCrowdfundingOrders;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Exceptions\InvalidRequestException;
use App\Events\OrderPaid;
use Carbon\Carbon;
use Omnipay\Omnipay;
use Illuminate\Validation\Rule;
use App\Models\Installment;

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
            $extra = $order->extra;
            $extra['transaction_id'] = $paypalResponse['PAYMENTINFO_0_TRANSACTIONID'];
            $order->update([
                'paid_at'        => Carbon::now(), // 支付时间
                'payment_method' => 'paypal', // 支付方式
                'payment_no'     => $order->id, // 订单号
                'extra' => $extra, //交易id
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

    public function wechatRefundNotify(Request $request)
    {
        // 给微信的失败响应
        $failXml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[FAIL]]></return_msg></xml>';
        $data = app('wechat_pay')->verify(null, true);

        // 没有找到对应的订单，原则上不可能发生，保证代码健壮性
        if(!$order = Order::where('no', $data['out_trade_no'])->first()) {
            return $failXml;
        }

        if ($data['refund_status'] === 'SUCCESS') {
            // 退款成功，将订单退款状态改成退款成功
            $order->update([
                'refund_status' => Order::REFUND_STATUS_SUCCESS,
            ]);
        } else {
            // 退款失败，将具体状态存入 extra 字段，并表退款状态改成失败
            $extra = $order->extra;
            $extra['refund_failed_code'] = $data['refund_status'];
            $order->update([
                'refund_status' => Order::REFUND_STATUS_FAILED,
                'extra' => $extra
            ]);
        }

        return app('wechat_pay')->success();
    }
    public function payByInstallment(Order $order, Request $request) {
        $this->authorize('own',$order);

        if ($order->paid_at || $order->closed) {
            throw new InvalidRequestException('訂單狀態不正確');
        }

        $this->validate($request,[
           'count' => ['required',Rule::in(array_keys(config('app.installment_fee_rate')))],
        ]);

        Installment::query()->where('order_id',$order->id)
                            ->where('status',Installment::STATUS_PENDING)
                            ->delete();
        $count = $request->input('count');
        $installment = new Installment([
            // 总本金即为商品订单总金额
            'total_amount' => $order->total_amount,
            // 分期期数
            'count'        => $count,
            // 从配置文件中读取相应期数的费率
            'fee_rate'     => config('app.installment_fee_rate')[$count],
            // 从配置文件中读取当期逾期费率
            'fine_rate'    => config('app.installment_fine_rate'),
        ]);
        $installment->user()->associate($request->user());
        $installment->order()->associate($order);
        $installment->save();
        $dueDate = Carbon::tomorrow();
        $base = big_number($order->total_amount)->divide($count)->getValue();
        $fee = big_number($base)->multiply($installment->fee_rate)->divide(100)->getValue();
        for($i = 0; $i < $count; $i++) {
            if ($i === $count -1) {
                $base = big_number($order->total_amount)->subtract(big_number($base)->multiply($count - 1));
            }
            $installment->items()->create([
                'sequence' => $i,
                'base'     => $base,
                'fee'      => $fee,
                'due_date' => $dueDate,
            ]);
            $dueDate = $dueDate->copy()->addDays(30);
        }
        return $installment;
    }

}
