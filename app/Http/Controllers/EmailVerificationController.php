<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Cache;
use Exception;
use Mail;
use App\Notifications\EmailVerificationNotification;

class EmailVerificationController extends Controller
{
    public function verify(Request $request) {
        $email = $request->input('email');
        $token = $request->input('token');

        if(!$email || !$token) {
            throw new Exception('驗證連結不正確');
        }

        if($token != Cache::get('email_verification_'.$email)) {
            throw new Exception('驗證連結不正確或已過期');
        }

        if(!$user = User::where('email',$email)->first()) {
            throw new Exception('用戶不存在');
        }

        Cache::forget('email_verification_'.$email);
        $user->update(['email_verified' => true]);

        return view('pages.success',['msg' => '信箱驗證成功']);
    }

    public function send(Request $request) {
        $user = $request->user();

        if($user->email_verified) {
            throw new Exception('你已經驗證過信箱了');
        }

        $user->notify(new EmailVerificationNotification());

        return view('pages.success', ['msg' => '郵件發送成功']);
    }
}
