<?php

namespace App\Http\Controllers\Mobile;

use App\Constants\ConstUserRole;
use App\Constants\ConstUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Models\Admin;
use App\Models\Borrower;
use App\Models\CreditScore;
use App\Models\Liveliness;
use App\Models\User;
use App\Models\UserDevice;
use App\Traits\BaseApiResponse;
use App\Traits\OTP;
use App\Traits\UploadImage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    use BaseApiResponse;
    use OTP;
    use UploadImage;


    /**
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function Register(RegisterRequest $request)
    {
        // Validate if user exists (moved to RegisterRequest if possible)
        if (User::where('email', $request->email)->exists()) {
            return $this->failed(null, 'Registration Failed', 'User already exists', 409);
        }

        DB::beginTransaction();

        try {
            // Create user
            $user = User::create([
                'email'    => $request->email,
                'password' => bcrypt($request->password),
                'phone'    => $request->phone,
                'role'     => ConstUserRole::BORROWER,
                'status'   => ConstUserStatus::ACTIVE
            ]);

            // Handle image upload
            $imagePath = $request->hasFile('image')
                ? $this->uploadImage($request->file('image'), 'borrower', 'public')
                : null;

            // Create borrower profile
            Borrower::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'gender'     => $request->gender,
                'dob'       => $request->dob,
                'address'    => $request->address,
                'image'     => $imagePath,
                'user_id'    => $user->id,
            ]);

            // Create credit score
            CreditScore::create([
                'user_id' => $user->id,
                'score'  => 50,  // Consider making this configurable
                'status' => 1,
            ]);

            // Generate token with device context
            $deviceId = $request->header('X-Device-ID', 'unknown_device');
            $deviceName = $request->header('X-Device-Name', 'Unknown Device');
            $deviceType = $request->header('X-Device-Type', 'web'); // Default to 'web' if not provided

            $token = $user->createToken($deviceId)->plainTextToken;

            // Associate device with user (if using UserDevice model)
            if (class_exists(UserDevice::class)) {
                UserDevice::updateOrCreate(
                    ['device_id' => $deviceId],
                    [
                        'user_id' => $user->id,
                        'device_name' => $deviceName,
                        'device_type' => $deviceType,
                        'ip_address' => $request->ip(),
                        'os' => $request->userAgent(),
                        'last_login_at' => now(),
                    ]
                );
            }

            DB::commit();

            // Eager load borrower profile
            $user->load('borrower');

            Log::channel('auth_log')->info('User registered', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'device'  => $deviceId,
                'ip'      => $request->ip()
            ]);

            return $this->successLogin($user, $token, 'Registration Successful', 'Welcome to our platform');

        } catch (Exception $e) {
            DB::rollBack();

            Log::channel('auth_error')->error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip'    => $request->ip()
            ]);

            return $this->failed(
                config('app.debug') ? $e->getMessage() : null,
                'Registration Error',
                'An error occurred during registration',
                500
            );
        }
    }

    /**
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request)
    {

        $user = User::query()->where('email', $request->input('email'))->first();

        if (!$user || !password_verify($request->input('password'), $user->password)) {
            return $this->failed(null, 'Fail', 'Invalid email or password', 401);
        }

        if ($user->status != ConstUserStatus::ACTIVE) {
            return $this->failed(null, 'Fail', 'User is not active, Please Contact Support Team!', 403);
        }

        //check $user->role if admin or borrower so join the table
        if ($user->role != ConstUserRole::BORROWER) {
            return $this->mobileError('You are not a borrower');
        }
        $profile = Borrower::query()->where('user_id', $user->id)->first();
        $device = $request->current_device;

        if ($device) {
            // Associate device with user
            $device->update(['user_id' => $user->id]);

            // Create token specific to this device
            $token = $user->createToken($device->device_id)->plainTextToken;
        } else {
            // Fallback if device tracking fails
            $token = $user->createToken('fallback_device')->plainTextToken;
        }

        //add $profile to user
        $user->profile = $profile;
        $user->role = (int) $user->role;
        $user->status = (int) $user->status;

        Log::channel('auth_log')->info('User login successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
        ]);
        return $this->successLogin($user, $token, 'Login', 'Login successful');
    }
    //get me function
    public function me()
    {
        $user = auth()->user();
        return $this->success($user, 'User', 'User data retrieved successfully');
    }
    //send OTP with auth user
    public function sendVerify(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required',
        ]);
        // Check if the phone number is already taken by another user
        $existingUser = User::query()->where('phone', $validated['phone'])->where('id', '!=', auth()->id())->first();
        if ($existingUser) {
            return $this->failed(null, 'Fail', 'Phone number already taken', 409);
        }
        $user = auth()->user();
        $data = $this->sendOTP($user);
        //send OTP to user email
        return $this->success($data, 'OTP', 'OTP sent successfully');
    }
    //verify OTP with auth user
    public function verifyOTP(VerifyCodeRequest $request)
    {
        $user = auth()->user();
        $data = $this->verifyOtpCode($user, $request->input('code'));
        if (!$data) {
            return $this->failed(null, 'Fail', 'Invalid OTP', 401);
        }

        $user = User::query()->where('id', $user->id)->first();

        $profile = null;
        //check $user->role if admin or borrower so join the table
        if ($user->role == ConstUserRole::BORROWER) {
            $profile = Borrower::query()->where('user_id', $user->id)->first();
        }

        if ($user->role == ConstUserRole::ADMIN) {
            $profile = Admin::query()->where('user_id', $user->id)->first();
        }
        $user->profile = $profile;

        Log::channel('otp_log')->info('OTP verified successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        return $this->success($user, 'OTP', 'OTP verified successfully');
    }

    //logout function
    public function logout()
    {
        $user = auth()->user();
        $user->tokens()->delete();
        return $this->success(null, 'Logout', 'Logout successful');
    }

    //telegram_chat_id store
    public function storeTelegramChatId(Request $request)
    {
        $request->validate([
            'chat_id' => 'required',
        ]);

        $chatId = $request->input('chat_id');

        $user = auth()->user();
        $user->telegram_chat_id = $chatId;
        $user->save();
        return $this->success(null, 'Telegram', 'Telegram chat id stored successfully');
    }

    //liveliness to store array of image 3
    public function liveliness(Request $request)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'nullable', // example validation rules
        ]);

        $user = auth()->user();
        $images = $request->file('images');
        $imagePaths = [];

        // Check if images were uploaded
        if (empty($images)) {
            return $this->failed(null,'No images were uploaded', 400);
        }

        foreach ($images as $image) {
            $imagePath = $this->uploadImage($image, 'liveliness', 'public');

            // Store each image immediately after upload
            Liveliness::create([
                'user_id' => $user->id,
                'image' => $imagePath,
            ]);
        }

        $data = [
            'success' => true,
        ];

        return $this->success($data, 'Liveliness images uploaded successfully');
    }

    //change password
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed|different:current_password',
        ]);

        $user = auth()->user();
        // Check if the current password is correct
        if (!password_verify($validated['current_password'], $user->password)) {
            return $this->failed(null, 'Fail', 'Old password is incorrect', 401);
        }

        try {
            $user->update([
                'password' => bcrypt($validated['password']),
            ]);

            return $this->success($user, 'Change Password', 'Password changed successfully');

        } catch (\Exception $e) {
            return $this->failed($e->getMessage(), 'Fail', 'Old password is incorrect', 401);
        }
    }

    //update profile
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $profile = $this->getUserProfile($user);

        if (!$profile) {
            return $this->failed(null, 'Fail', 'Profile not found', 404);
        }

        // Check if the phone number is already taken by another user
        $existingUser = User::query()->where('phone', $request->input('phone'))->where('id', '!=', $user->id)->first();
        if ($existingUser) {
            return $this->failed(null, 'Fail', 'Phone number already taken', 409);
        }

        $image = $request->file('image');
        $imagePath = $profile->image;
        if ($image) {
            $imagePath = $this->uploadImage($image, 'borrower', 'public');
            // Delete the old image if it exists
            if ($profile->image && file_exists(public_path($profile->image))) {
                unlink(public_path($profile->image));
            }
        }

        $profile->update([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'gender' => $request->input('gender'),
            'dob' => $request->input('dob'),
            'address' => $request->input('address'),
            'image' => $imagePath,
        ]);

        // Update user data
        $user->update([
            'phone' => $request->input('phone'),
        ]);

        $profile = null;
        //check $user->role if admin or borrower so join the table
        if ($user->role == ConstUserRole::BORROWER) {
            $profile = Borrower::query()->where('user_id', $user->id)->first();
        }

        if ($user->role == ConstUserRole::ADMIN) {
            $profile = Admin::query()->where('user_id', $user->id)->first();
        }

        $token = $user->createToken('token_base_name')->plainTextToken;

        //add $profile to user
        $user->profile = $profile;
        $user->role = (int) $user->role;
        $user->status = (int) $user->status;

        return $this->successLogin($user, $token, 'Login', 'Login successful');
    }

    /**
     * Get the related profile model for the user.
     *
     * @param \App\Models\User $user
     * @return \App\Models\Admin|\App\Models\Borrower|null
     */
    protected function getUserProfile($user)
    {
        if ($user->role == ConstUserRole::BORROWER) {
            return Borrower::query()->where('user_id', $user->id)->first();
        }

        if ($user->role == ConstUserRole::ADMIN) {
            return Admin::query()->where('user_id', $user->id)->first();
        }

        return null;
    }

}
