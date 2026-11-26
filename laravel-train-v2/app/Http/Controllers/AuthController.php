<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware(ActiveUser::class)->except('logout');
        $this->middleware('guest')->only('showLogin','login');
        $this->middleware('auth')->only('showPasswordEdit');
    }

    // Hiện form login
    public function showLogin() : View {
        return view('auth.login', [ // Dữ liệu mặc định cho form đăng nhập
            'username'          => '',
            'usernameError'     => false,
            'passwordError'     => false,
            'loginErrorMessage' => '',
        ]);
    }

    // Xử lý login
    public function login(Request $request) : View | RedirectResponse {
        if($request->input('action') === 'clear'){
            return redirect()->route('login'); // Mở lại form login khi user bấm xóa trống
        }

        $validator = Validator::make($request->only('username', 'password'), [ // Chỉ nhận 2 trường từ request để truyền vào validate
            'username' => 'required',
            'password' => 'required',
        ]);

        $usernameError = $validator->errors()->has('username'); // Check lỗi của trường username
        $passWordError = $validator->errors()->has('password'); // Check lỗi của trường password
        $username = $usernameError ? '' : $request->input('username');
        $password = $passWordError ? '' : $request->input('password');
        $loginErrorMessage = __('messages.login_failed');

        if(! $usernameError && ! $passWordError){
            $user = User::query()->where('username', $username)->first();

            if(! $user){ // Kiểm tra user tồn tại
                $usernameError = true;
            }
            elseif($user->status !== AppConstants::STATUS_ACTIVE){ // Kiểm tra user còn hoạt động
                $usernameError = true;
            }
            elseif(!Hash::check($password, $user->password_hash)){ // Kiểm tra mật khẩu 
                $passWordError = true;
            }
            else{
                Auth::login($user); // login thành công
                return redirect()->route($user->password_status === 0 ? 'password.edit' : 'dashboard'); // Chuyển hướng về trang dashboard hoặc đổi mật khẩu lần đầu
            }
        }
        
        return view('auth.login', [
            'username'          => $username,
            'usernameError'     => $usernameError,
            'passwordError'     => $passWordError,
            'loginErrorMessage' => $loginErrorMessage,
        ]);
    }

    // Xử lý logout
    public function logout(Request $request) : RedirectResponse {
        Auth::logout(); // Thoát trạng lái login
        $request->session()->invalidate(); // Xóa session cũ
        $request->session()->regenerateToken(); // Tạo token mới
        return redirect()->route('login');
    }
}
