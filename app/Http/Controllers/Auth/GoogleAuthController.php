<?php

namespace App\Http\Controllers\Auth;

use App\Constants\ConstUserRole;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Borrower;
use App\Models\CreditScore;
use App\Models\User;
use App\Traits\BaseApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    use BaseApiResponse;
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $name = $googleUser->getName();
            $email = $googleUser->getEmail();
            $image = $googleUser->getAvatar();
            $password = Hash::make($googleUser->getId() . $googleUser->getEmail() . $googleUser->getName());
            $user = \App\Models\User::where('email', $email)->first();
            if (!$user) {
                // User doesn't exist, create a new one
                $request = [
                    'email' => $email,
                    'password' => $password,
                    'phone' => rand(1000000000, 9999999999),
                    'phone_verified_at' => null, // Automatically verify the email
                ];
                $user = User::query()->create($request);

                Borrower::query()->create([
                    'user_id' => $user->id,
                    'first_name' => $name,
                    'last_name' => $name,
                    'gender' => 'N/A',
                    'dob' => Carbon::now(),
                    'address' => 'N/A',
                    'image' => $image,
                ]);

                //give the user a default credit score
                CreditScore::query()->create([
                    'user_id' => $user->id,
                    'score' => 50,
                    'status' => 1,
                ]);
            } else {
                if (is_null($user->phone_verified_at)) {
                    $user->phone_verified_at = now(); // Automatically verify the email
                    $user->save();
                }
            }
            Auth::login($user);
            $token = $user->createToken('token_base_name')->plainTextToken;
            $user = User::query()->where('email', $email)->first();

            $profile = null;
            //check $user->role if admin or borrower so join the table
            if ($user->role == ConstUserRole::BORROWER) {
                $profile = Borrower::query()->where('user_id', $user->id)->first();
            }

            if ($user->role == ConstUserRole::ADMIN) {
                $profile = Admin::query()->where('user_id', $user->id)->first();
            }

            $user->profile = $profile;
            $user->role = (int) $user->role;
            $user->status = (int) $user->status;



            return $this->successLogin($user, $token , 'Login', 'Login successful');
        } catch (\Exception $e) {
            return $this->failed($e->getMessage(), 'Login failed', 'Login failed', 422);
        }
    }
    public function handleGoogleCode(Request $request)
    {
        $code = $request->code;
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $name = $googleUser->getName();
            $email = $googleUser->getEmail();
            $image = $googleUser->getAvatar();
            $password = Hash::make($googleUser->getId() . $googleUser->getEmail() . $googleUser->getName());

            $user = User::where('email', $email)->first();

            if (!$user) {
                // User doesn't exist, create a new one
                $request = [
                    'email' => $email,
                    'password' => $password,
                    'phone' => random_int(1000000000, 9999999999),
                    'phone_verified_at' => null, // Automatically verify the email
                    'status' => 1,
                ];
                $user = User::query()->create($request);

                Borrower::query()->create([
                    'user_id' => $user->id,
                    'first_name' => $name,
                    'last_name' => $name,
                    'gender' => 'N/A',
                    'dob' => Carbon::now(),
                    'address' => 'N/A',
                    'image' => $image,
                ]);

                //give the user a default credit score
                CreditScore::query()->create([
                    'user_id' => $user->id,
                    'score' => 50,
                    'status' => 1,
                ]);
            } else {
                // Optionally, mark the email as verified if user exists
                if (is_null($user->phone_verified_at)) {
                    $user->phone_verified_at = now(); // Automatically verify the email
                    $user->save();
                }
            }
            Auth::login($user);

            $token = $user->createToken('token_base_name')->plainTextToken;
            $user = User::query()->where('email', $email)->first();
            $profile = null;
            //check $user->role if admin or borrower so join the table
            if ($user->role == ConstUserRole::BORROWER) {
                $profile = Borrower::query()->where('user_id', $user->id)->first();
            }

            if ($user->role == ConstUserRole::ADMIN) {
                $profile = Admin::query()->where('user_id', $user->id)->first();
            }

            $user->profile = $profile;
            $user->role = (int) $user->role;
            $user->status = (int) $user->status;

            return $this->successLogin($user, $token , 'Login', 'Login successful');

        } catch (\Exception $e) {
            return $this->failed($e->getMessage(), 'Error', 'Error form server');
        }
    }
}
