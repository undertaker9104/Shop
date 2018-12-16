<?php

use Illuminate\Http\Request;
use PayPal\Api\Item;
use PayPal\Api\Payer;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Payment;
use PayPal\Api\ItemList;
use PayPal\Api\WebProfile;
use PayPal\Api\InputFields;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;
use PayPal\Api\PaymentExecution;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('create-paypal',function(Request $request){
    $apiContext = new \PayPal\Rest\ApiContext(
        new \PayPal\Auth\OAuthTokenCredential(
            'Aehr1z_51vtXjdFglQS-hF3NwGhHA8fNbvXe8-Vc6Dtw_kliduUGcKhqE6oFdZABCbtVyXdb1W1Qevla',
            'EK0RS5VUHGVI4HIxa9pRXGPNrSBc23jMTMeHuEX6qoylxiHCkIjPCVLvYI4EnKqXHQv9m3nDmsrGnefC'
        )
    );
    $payer = new Payer();
    $payer->setPaymentMethod("paypal");
    $item1 = new Item();
    $item1->setName('Ground Coffee 40 oz')
        ->setCurrency('USD')
        ->setQuantity(1)
        ->setSku("123123") // Similar to `item_number` in Classic API
        ->setPrice(7.5);
    $item2 = new Item();
    $item2->setName('Granola bars')
        ->setCurrency('USD')
        ->setQuantity(5)
        ->setSku("321321") // Similar to `item_number` in Classic API
        ->setPrice(2);
    $itemList = new ItemList();
    $itemList->setItems(array($item1, $item2));
    $details = new Details();
    $details->setShipping(1.2)
        ->setTax(1.3)
        ->setSubtotal(17.50);
    $amount = new Amount();
    $amount->setCurrency("USD")
        ->setTotal(20)
        ->setDetails($details);
    $transaction = new Transaction();
    $transaction->setAmount($amount)
        ->setItemList($itemList)
        ->setDescription("Payment description")
        ->setInvoiceNumber(uniqid());
    $redirectUrls = new RedirectUrls();
    $redirectUrls->setReturnUrl("http://laravel-paypal-example.test")
        ->setCancelUrl("http://laravel-paypal-example.test");
    // Add NO SHIPPING OPTION
    $inputFields = new InputFields();
    $inputFields->setNoShipping(1);
    $webProfile = new WebProfile();
    $webProfile->setName('test' . uniqid())->setInputFields($inputFields);
    $webProfileId = $webProfile->create($apiContext)->getId();
    $payment = new Payment();
    $payment->setExperienceProfileId($webProfileId); // no shipping
    $payment->setIntent("sale")
        ->setPayer($payer)
        ->setRedirectUrls($redirectUrls)
        ->setTransactions(array($transaction));
    try {
        $payment->create($apiContext);
    } catch (Exception $ex) {
        echo $ex;
        exit(1);
    }
    return $payment;
});
