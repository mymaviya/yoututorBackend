<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\LoginHoliday;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\LoginSecurityService;
use App\Services\AuditService;
use Carbon\Carbon;

class AuthController extends Controller
{
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

            AuditService::log('Authentication', 'Failed Login', 'Invalid credentials for email: ' . $request->email, null, null, $user?->id);

            throw ValidationException::withMessages([

                'email' => ['Invalid email or password.']
            ]);
        }

        $accessError = $this->checkSecurityAccess($request, $user);

        if ($accessError) {
            return response()->json([
                'message' => $accessError,
            ], 403);
        }

        $securityError = app(LoginSecurityService::class)
            ->check($request, $user);

        if ($securityError) {
            return response()->json([
                'message' => $securityError,
            ], 403);
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

        if (
            !$user->allow_multiple_sessions &&
            !empty($user->current_session_id) &&
            !empty($user->last_activity_at)
        ) {

            $lastActivity = Carbon::parse($user->last_activity_at);

            if (
                $lastActivity->diffInMinutes(now())
                < $user->session_timeout_minutes
            ) {

                return response()->json([
                    'message' => 'This user is already logged in. Please log out before logging in here.',
                    'code' => 'USER_ALREADY_LOGGED_IN'
                ], 422);
            }
        }

        try {

            app(LoginSecurityService::class)
                ->registerDevice($request, $user);
        } catch (\Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->update([
            'current_session_id' => (string) Str::uuid(),
            'last_activity_at' => now(),
        ]);

        AuditService::log('Authentication', 'Login', 'User logged in successfully with email: ' . $user->email, null, null, $user->id);

        app(LoginSecurityService::class)->registerDevice($request, $user);

        UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => hash('sha256', $request->userAgent() . $request->ip()),
            ],
            [
                'device_name' => $request->header('X-Device-Name'),
                'browser' => $request->header('X-Browser'),
                'platform' => $request->header('X-Platform'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_used_at' => now(),
                'is_trusted' => true,
            ]
        );

        if ($user->device_lock_enabled) {
            $deviceId = hash('sha256', $request->userAgent() . $request->ip());

            $trusted = UserDevice::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->where('is_trusted', true)
                ->exists();

            $deviceCount = UserDevice::where('user_id', $user->id)
                ->where('is_trusted', true)
                ->count();

            if (!$trusted && $deviceCount >= $user->max_devices) {
                return response()->json([
                    'message' => 'Login blocked. Maximum trusted devices reached.',
                ], 403);
            }
        }

        $user->loadMissing('roleData');
        $roleSlug = $user->roleData?->slug;

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
                'role_slug' => $roleSlug,
                'profile' => $user->profile ? asset('storage/' . $user->profile) : null,
                'password_change_required' => (bool) $user->password_change_required,

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

        AuditService::log('Authentication', 'Register', 'New user registered with email: ' . $user->email);

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
        $user = $request->user();

        $user->update([
            'current_session_id' => null,
            'last_activity_at' => null,
        ]);

        $user->tokens()->delete();

        AuditService::log(
            'Authentication',
            'Logout',
            'User logged out'
        );

        return response()->json([
            'message' => 'Logged out successfully.'
        ]);
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

    private function checkSecurityAccess(Request $request, $user)
    {
        // Holiday restriction
        if (!$user->holiday_login_allowed) {
            $isHoliday = LoginHoliday::whereDate('date', now('Asia/Kolkata')->toDateString())
                ->where('is_active', true)
                ->exists();

            if ($isHoliday) {
                return 'Login is restricted today due to holiday.';
            }
        }

        // IP restriction
        if (!empty($user->allowed_ips)) {
            $allowedIps = is_array($user->allowed_ips)
                ? $user->allowed_ips
                : json_decode($user->allowed_ips, true);

            if ($allowedIps && !in_array($request->ip(), $allowedIps)) {
                return 'Login is not allowed from this IP address.';
            }
        }

        return null;
    }

    public function changeFirstPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
            'password_change_required' => false,
            'password_changed_at' => now(),


        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
