<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\UserInformation;
use App\Models\Login;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('register');
    }

    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        $user = $this->create($request->all());

        Auth::login($user);

        return redirect()->intended('dashboard');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'user_name' => ['required', 'string', 'max:255'],
            'user_surname' => ['required', 'string', 'max:255'],
            'user_email' => ['required', 'string', 'email', 'max:255', 'unique:user_information,user_email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    protected function create(array $data)
    {
        DB::beginTransaction();
        
        try {
            // Create user information record
            $userInfo = UserInformation::create([
                'user_name' => $data['user_name'],
                'user_surname' => $data['user_surname'],
                'user_title' => $data['user_title'],
                'user_phone' => $data['user_phone'],
                'user_email' => $data['user_email'],
                'user_dob' => $data['user_dob'],
                'user_job_start' => $data['user_job_start'],
                'user_job_end' => $data['user_job_end'] ?? null,
                'user_active' => isset($data['user_active']) ? 1 : 0,
                'user_department' => $data['user_department'],
            ]);

            // Create login record
            $login = Login::create([
                'email' => $data['user_email'],
                'user_login_pass' => Hash::make($data['password']),
                'user_id' => $userInfo->user_id,
                'last_login_attampt' => now()
            ]);

            DB::commit();
            return $userInfo;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}
