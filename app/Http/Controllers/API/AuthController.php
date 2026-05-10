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
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'nullable|boolean'
            ]
        );

        $user = User::where('email',$validated['email'])->first();

        if (!$user ||!Hash::check($validated['password'],$user->password)) {

            throw ValidationException::withMessages([

                'email' => ['Invalid email or password.']
            ]);
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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),

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
        ]);
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
}
