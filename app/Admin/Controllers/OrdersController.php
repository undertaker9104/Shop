<?php

namespace App\Admin\Controllers;

use App\Http\Requests\Admin\HandleRefundRequest;
use App\Http\Requests\Request;
use App\Models\Order;
use App\Exceptions\InvalidRequestException;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Omnipay\Omnipay;

class OrdersController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('訂單列表')
            ->body($this->grid());
    }

    /**
     * Show interface.
     *
     * @param mixed   $id
     * @param Content $content
     * @return Content
     */
    public function show(Order $order, Content $content)
    {
        return $content
            ->header('查看訂單')
            ->body(view('admin.orders.show',['order'=>$order]));
    }

    /**
     * Edit interface.
     *
     * @param mixed   $id
     * @param Content $content
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header('Edit')
            ->description('description')
            ->body($this->form()->edit($id));
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        return $content
            ->header('Create')
            ->description('description')
            ->body($this->form());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Order);

        $grid->model()->whereNotNull('paid_at')->orderBy('paid_at', 'desc');

        $grid->no('訂單流水號');
        // 展示关联关系的字段时，使用 column 方法
        $grid->column('user.name', '買家');
        $grid->total_amount('總金額')->sortable();
        $grid->paid_at('支付時間')->sortable();
        $grid->ship_status('物流')->display(function($value) {
            return Order::$shipStatusMap[$value];
        });
        $grid->refund_status('退款狀態')->display(function($value) {
            return Order::$refundStatusMap[$value];
        });
        // 禁用创建按钮，后台不需要创建订单
        $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            // 禁用删除和编辑按钮
            $actions->disableDelete();
            $actions->disableEdit();
        });
        $grid->tools(function ($tools) {
            // 禁用批量删除按钮
            $tools->batch(function ($batch) {
                $batch->disableDelete();
            });
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed   $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Order::findOrFail($id));

        $show->id('Id');
        $show->no('No');
        $show->user_id('User id');
        $show->address('Address');
        $show->total_amount('Total amount');
        $show->remark('Remark');
        $show->paid_at('Paid at');
        $show->payment_method('Payment method');
        $show->payment_no('Payment no');
        $show->refund_status('Refund status');
        $show->refund_no('Refund no');
        $show->closed('Closed');
        $show->reviewed('Reviewed');
        $show->ship_status('Ship status');
        $show->ship_data('Ship data');
        $show->extra('Extra');
        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Order);

        $form->text('no', 'No');
        $form->number('user_id', 'User id');
        $form->textarea('address', 'Address');
        $form->decimal('total_amount', 'Total amount');
        $form->textarea('remark', 'Remark');
        $form->datetime('paid_at', 'Paid at')->default(date('Y-m-d H:i:s'));
        $form->text('payment_method', 'Payment method');
        $form->text('payment_no', 'Payment no');
        $form->text('refund_status', 'Refund status')->default('pending');
        $form->text('refund_no', 'Refund no');
        $form->switch('closed', 'Closed');
        $form->switch('reviewed', 'Reviewed');
        $form->text('ship_status', 'Ship status')->default('pending');
        $form->textarea('ship_data', 'Ship data');
        $form->textarea('extra', 'Extra');

        return $form;
    }

    public  function ship(Order $order,Request $request){
        if(!$order->paid_at){
            throw new InvalidRequestException('該訂單未付款');
        }
        if($order->ship_status !== Order::SHIP_STATUS_PENDING){
            throw new InvalidRequestException('該訂單已發貨');
        }
        $data = $this->validate($request,[
            'express_company' => ['required'],
            'express_no'      => ['required'],
        ],[],[
            'express_company' => '物流公司',
            'express_no'      => '物流單號',
        ]);
        $order->update([
            'ship_status' => Order::SHIP_STATUS_DELIVERED,
            // 我们在 Order 模型的 $casts 属性里指明了 ship_data 是一个数组
            // 因此这里可以直接把数组传过去
            'ship_data'   => $data,
        ]);

        // 返回上一页
        return redirect()->back();
    }

    public function handleRefund(HandleRefundRequest $request,Order $order){
        if($order->refund_status !== Order::REFUND_STATUS_APPLIED){
            throw new InvalidRequestException('訂單狀態不正確');
        }

        if($request->input('agree')){
            $extra = $order->extra ?: [];
            unset($extra['refund_disagree_reason']);
            $order->update([
                'extra' => $extra,
            ]);
            $this->_refundOrder($order);
        }else{
            $extra = $order->extra ?:[];
            $extra['refund_disagree_reason'] = $request->input('reason');
            $order->update([
                'refund_status' => Order::REFUND_STATUS_PENDING,
                'extra' => $extra,
            ]);
        }
        return $order;
    }

    protected function _refundOrder(Order $order){
        switch($order->payment_method){
            case 'wechat':

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
                throw new InternalException('未知订单支付方式：'.$order->payment_method);
                break;
            case 'paypal':

                $transaction_id = $order->extra['transaction_id'];
                $params = array(
                    'amount' => $order->total_amount,
                    'transactionReference' => $transaction_id,
                );
                $gateway = Omnipay::create('PayPal_Express');
                $gateway->setUsername('a9581987-faculty_api1.gmail.com'); // here you should place the email of the business sandbox account
                $gateway->setPassword('7N3MHMBC6V7KAR9H'); // here will be the password for the account
                $gateway->setSignature('AdCth9i9nL2TOOvMhWwrfSSFHhw9AWUM2ydvYBmoxOOituG3joC-jVmv'); // and the signature for the account
                $gateway->setTestMode(true); // set it to true when you develop and when you go to production to false
                $response = $gateway->refund($params)->send(); // here you send details to PayPal\

                if ($response->isSuccessful()) {
                    // redirect to offsite payment gateway
                    // 将订单的退款状态标记为退款成功并保存退款订单号
                    $order->update([
                        'refund_no' => $transaction_id,
                        'refund_status' => Order::REFUND_STATUS_SUCCESS,
                    ]);
                } else {
                    // 将退款失败的保存存入 extra 字段
                    $extra = $order->extra;
                    $extra['refund_failed_code'] ='just_fail';
                    // 将订单的退款状态标记为退款失败
                    $order->update([
                        'refund_no' => $transaction_id,
                        'refund_status' => Order::REFUND_STATUS_FAILED,
                        'extra' => $extra,
                    ]);
                }
                break;
        }
    }
}
