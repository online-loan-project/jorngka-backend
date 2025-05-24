<?php

namespace App\Http\Controllers\Auth;

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
use App\Traits\BaseApiResponse;
use App\Traits\OTP;
use App\Traits\UploadImage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

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
        // Check if the user has already registered recently
        $existingUser = User::query()->where('email', $request->input('email'))->first();
        if ($existingUser) {
            return $this->failed(null, 'Fail', 'User already exists', 409);
        }

        DB::beginTransaction(); //protect the database from any error if error occurs it will rollback

        try {
            // Create the user
            $user = User::query()->create([
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password'),),
                'phone' => $request->input('phone'),
                'role' => ConstUserRole::BORROWER,
                'status' => ConstUserStatus::ACTIVE
                ]);

            $image = $request->file('image');
            $imagePath = null;
            if ($image) {
                $imagePath = $this->uploadImage($image, 'borrower', 'public');
            }

            Borrower::query()->create([
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'gender' => $request->input('gender'),
                'dob' => $request->input('dob'),
                'address' => $request->input('address'),
                'image' => $imagePath,
                'user_id' => $user->id,
            ]);

            // Generate a token for the user
            $token = $user->createToken('token_base_name')->plainTextToken;

            //give the user a default credit score
            CreditScore::query()->create([
                'user_id' => $user->id,
                'score' => 50,
                'status' => 1,
            ]);

            // Store registration time in session
            session(['registered_time' => now()]);

            // Commit the transaction
            DB::commit();

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

            return $this->successLogin($user, $token, 'Register', 'Register successful');
        } catch (Exception $exception) {
            // Rollback the transaction in case of error
            DB::rollBack();

            return $this->failed($exception->getMessage(), 'Error', 'Error from server');
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
    //get me function
    public function me()
    {
        $user = auth()->user();
        return $this->success($user, 'User', 'User data retrieved successfully');
    }
    //send OTP with auth user
    public function sendVerify()
    {
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
            $imagePaths[] = $imagePath;

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
