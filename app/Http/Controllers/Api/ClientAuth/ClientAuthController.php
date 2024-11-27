<?php

namespace App\Http\Controllers\Api\ClientAuth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientAuthRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Traits\UploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
class ClientAuthController extends Controller
{
    use UploadTrait;
    public function register(ClientAuthRequest $request)
    {
        $Client = Client::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
        ]);
        $this->uploadImage($request, 'upload_image', 'photo', 'Clients', $Client->id, 'App\Models\Client');

        $token = Auth::guard('admin')->login($Client);
        return ApiResponse::sendResponse(201, 'Client Created Successfully', [
            'Client' => new ClientResource($Client),
            'authorisation' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);

    }

    public function login(Request $request)
    {
        $this->ensureIsNotRateLimited($request);
        return $this->authenticate($request);
    }

    public function logout()
    {
        Auth::guard('client')->logout();
        return ApiResponse::sendResponse(200, 'Client Logout Successfully ', []);
    }


    public function authenticate($request)
    {
        $fieldType = filter_var($request->login_id, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        if ($fieldType === 'email') {
            $request->validate([
                'login_id' => 'required|email|exists:clients,email',
                'password' => 'required|min:5|max:45',
            ], [
                'login_id.required' => 'Email or Username is required.',
                'login_id.email' => 'Invalid email address.',
                'login_id.exists' => 'Email does not exist in the system.',
                'password.required' => 'Password is required',
            ]);
        } else {
            $request->validate([
                'login_id' => 'required|exists:clients,name',
                'password' => 'required|min:5|max:45',
            ], [
                'login_id.required' => 'Email or Username is required.',
                'login_id.exists' => 'Username does not exist in the system.',
                'password.required' => 'Password is required',
            ]);
        }

        $credentials = [
            $fieldType => $request->login_id,
            'password' => $request->password,
        ];

        if (Auth::guard('client')->attempt($credentials)) {
            RateLimiter::clear($this->throttleKey($request));
            $Client = Auth::guard('client')->user(); // Correct guard
            $token = Auth::guard('client')->attempt($credentials); // Correct guard
            return ApiResponse::sendResponse(200, 'Client login successful', [
                new ClientResource($Client),
                'authorisation' => [
                    'token' => $token,
                    'type' => 'bearer',
                ]
            ]);
        } else {
            RateLimiter::hit($this->throttleKey($request));
            return ApiResponse::sendResponse(400, 'incorrect credentials', []);
        }
    }


    protected function ensureIsNotRateLimited(Request $request)
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($request));
            $response = ApiResponse::sendResponse(429, "Too Many Attempts. Try again in $seconds seconds");
            throw new ValidationException(validator([], []), $response);
        } else {
            session()->forget('retry_after');
        }
    }


    protected function throttleKey(Request $request)
    {
        return Str::lower($request->input('login_id')) . '|' . $request->ip();
    }


}

