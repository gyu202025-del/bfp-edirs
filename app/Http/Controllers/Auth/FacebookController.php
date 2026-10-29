<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class FacebookController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function callback()
    {
        $fbUser = Socialite::driver('facebook')->user();

        $user = User::where('facebook_id', $fbUser->getId())
            ->orWhere('email', $fbUser->getEmail())
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $fbUser->getName(),
                'email' => $fbUser->getEmail(),
                'password' => Str::random(24), // unused for social accounts, but column is required
                'role' => 'Citizen',           // Facebook login is for citizen reporters only
                'status' => 'Active',
                'facebook_id' => $fbUser->getId(),
                'profile_picture' => $fbUser->getAvatar(),
            ]);
        } elseif (!$user->isCitizen()) {
            abort(403, 'Facebook login is available for citizen accounts only.');
        } elseif (! $user->facebook_id) {
            $user->update(['facebook_id' => $fbUser->getId()]);
        }

        abort_unless($user->status === 'Active', 403, 'This account has been deactivated.');
        Auth::login($user);

        return redirect()->route('report.select');
    }
}