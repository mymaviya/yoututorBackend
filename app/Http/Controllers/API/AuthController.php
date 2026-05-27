<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    // public function login(Request $request)
    // {
    //     $user = User::where('email', $request->email)->first();

    //     if (!$user || !Hash::check($request->password, $user->password)) {
    //         return response()->json(['message' => 'Invalid credentials'], 401);
    //     }

    //     $token = $user->createToken('auth_token')->plainTextToken;

    //     return response()->json([
    //         'token' => $token,
    //         'user' => [
    //             'id' => $user->id,
    //             'name' => $user->name,
    //             'email' => $user->email,
    //             'contact' => $user->contact,
    //             'address' => $user->address,
    //             'role' => $user->role,
    //             'profile' => $user->profile ? asset('storage/' . $user->profile) : null
    //         ]
    //     ]);
    // }

    public function login(Request $request)
    {
        $validated = $request->validate(
            [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
                'remember' => 'nullable|boolean'
            ]
        );

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {

            throw ValidationException::withMessages([

                'email' => ['Invalid email or password.']
            ]);
        }

        $accessError = $this->checkLoginAccess($user);

        if ($accessError) {
            return response()->json([
                'message' => $accessError,
            ], 403);
        }

        if ($request->remember) {

            $user->remember_token = Str::random(60);

            $user->save();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'remember_token' => $request->remember ? $user->remember_token : null,
            'user' => [

                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->contact,
                'address' => $user->address,
                'role' => $user->role,
                'profile' => $user->profile ? asset('storage/' . $user->profile) : null
            ]
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:3|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'confirm_password' => 'required|same:password',

        ]);



        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'student',

        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->contact,
                'address' => $user->address,
                'role' => $user->role,
                'profile' => $user->profile ? asset('storage/' . $user->profile) : null
            ]
        ], 201);
    }

    public function currentUser(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'contact' => 'required|numeric|digits:10',
            'address' => 'required|string',
            'profile' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        // Update basic info
        $user->name = $request->name;
        $user->email = $request->email;
        $user->contact = $request->contact;
        $user->address = $request->address;

        // Upload image
        if ($request->hasFile('profile')) {

            // delete old image
            if ($user->profile && file_exists(storage_path('app/public/' . $user->profile))) {
                unlink(storage_path('app/public/' . $user->profile));
            }

            $file = $request->file('profile');
            $name = Str::slug($user->name);
            $timestamp = time();           // unique
            $extension = $file->getClientOriginalExtension();

            $filename = $name . '-' . $timestamp . '.' . $extension;

            // 📂 Store file
            $path = $file->storeAs('profiles', $filename, 'public');

            $user->profile = $path;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->contact,
                'address' => $user->address,
                'role' => $user->role,
                'profile' => $user->profile ? asset('storage/' . $user->profile) : null
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }


    private function buildLoginWindow($user)
    {
        $timezone = 'Asia/Kolkata';

        $now = Carbon::now($timezone);

        $startTime = $user->daily_login_start_time ?: '09:00:00';
        $endTime = $user->daily_login_end_time ?: '17:00:00';

        $startToday = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $now->toDateString() . ' ' . $startTime,
            $timezone
        );

        $endToday = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $now->toDateString() . ' ' . $endTime,
            $timezone
        );

        return [$now, $startToday, $endToday];
    }

    private function loginResumeMessage($user)
    {
        [$now, $startToday, $endToday] = $this->buildLoginWindow($user);

        // BEFORE LOGIN START TIME
        if ($now->lt($startToday)) {

            $diff = $now->diff($startToday);

            $timeText = '';

            if ($diff->h > 0) {
                $timeText .= $diff->h . ' hrs ';
            }

            if ($diff->i > 0) {
                $timeText .= $diff->i . ' min';
            }

            $timeText = trim($timeText);

            return 'Login will resume after ' .
                $timeText .
                ' at ' .
                $startToday->format('h:i A') . '.';
        }

        // AFTER LOGIN END TIME
        if ($now->gt($endToday)) {

            $startTomorrow = $startToday->copy()->addDay();

            $diff = $now->diff($startTomorrow);

            $timeText = '';

            if ($diff->h > 0) {
                $timeText .= $diff->h . ' hrs ';
            }

            if ($diff->i > 0) {
                $timeText .= $diff->i . ' min';
            }

            $timeText = trim($timeText);

            return 'Login will resume after ' .
                $timeText .
                ' at ' .
                $startTomorrow->format('h:i A') . '.';
        }

        return 'Login not allowed currently.';
    }

    private function checkLoginAccess($user)
    {
        $timezone = 'Asia/Kolkata';

        $today = Carbon::today($timezone);

        if (!$user->is_active) {
            return 'Your account is inactive.';
        }

        if (!$user->login_enabled) {
            return 'Your login access has been disabled.';
        }

        if (
            $user->login_start_date &&
            $today->lt(Carbon::parse($user->login_start_date, $timezone))
        ) {
            $startDate = Carbon::parse($user->login_start_date, $timezone);

            return 'Your login access will start from ' .
                $startDate->format('d M Y') . '.';
        }

        if (
            $user->login_end_date &&
            $today->gt(Carbon::parse($user->login_end_date, $timezone))
        ) {
            return 'Your login access has expired.';
        }

        if ($user->daily_login_start_time && $user->daily_login_end_time) {
            [$now, $startToday, $endToday] = $this->buildLoginWindow($user);

            if ($now->lt($startToday) || $now->gt($endToday)) {
                return $this->loginResumeMessage($user);
            }
        }

        return null;
    }
}
