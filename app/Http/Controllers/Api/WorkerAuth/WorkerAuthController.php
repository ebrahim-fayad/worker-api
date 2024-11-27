<?php

namespace App\Http\Controllers\Api\WorkerAuth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkerAuthRequest;
use App\Http\Resources\WorkerResource;
use App\Models\Worker;
use App\Traits\UploadTrait;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
class WorkerAuthController extends Controller
{
    use UploadTrait;
    public function register(WorkerAuthRequest $request)
    {
        $Worker = Worker::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'location' => $request->location,
        ]);
        $this->uploadImage($request, 'upload_image', 'photo', 'workers', $Worker->id, 'App\Models\Worker');

        $token = Auth::guard('admin')->login($Worker);
        return ApiResponse::sendResponse(200, 'Admin Created Successfully', [
            'worker' => new WorkerResource($Worker),
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
        Auth::guard('worker')->logout();
        return ApiResponse::sendResponse(200, 'Worker Logout Successfully ', []);
    }


    public function authenticate($request)
    {
        $fieldType = filter_var($request->login_id, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        if ($fieldType === 'email') {
            $request->validate([
                'login_id' => 'required|email|exists:workers,email',
                'password' => 'required|min:5|max:45',
            ], [
                'login_id.required' => 'Email or Username is required.',
                'login_id.email' => 'Invalid email address.',
                'login_id.exists' => 'Email does not exist in the system.',
                'password.required' => 'Password is required',
            ]);
        } else {
            $request->validate([
                'login_id' => 'required|exists:workers,name',
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

        if (Auth::guard('worker')->attempt($credentials)) {
            RateLimiter::clear($this->throttleKey($request));
            $worker = Auth::guard('worker')->user(); // Correct guard
            $token = Auth::guard('worker')->attempt($credentials); // Correct guard
            return ApiResponse::sendResponse(201, 'Worker login successful', [
                new WorkerResource($worker),
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
