<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([

            'name' => [
                'required',
                'string',
                'max:255'
            ],

            'email' => [
                'required',
                'email',
                Rule::unique('users')
                    ->ignore($user->id)
            ],

            'contact' => [
                'nullable',
                'string',
                'max:20'
            ],

            'address' => [
                'nullable',
                'string',
                'max:500'
            ],

            'profile' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048'
            ]
        ]);

        if ($request->hasFile('profile')) {

            if (
                $user->profile &&
                file_exists(
                    storage_path(
                        'app/public/' . $user->profile
                    )
                )
            ) {
                unlink(
                    storage_path(
                        'app/public/' . $user->profile
                    )
                );
            }

            /*
            |--------------------------------------------------------------------------
            | STORE NEW IMAGE
            |--------------------------------------------------------------------------
            */

            $image = $request->file('profile');

            $imageName =
                strtolower(
                    str_replace(
                        ' ',
                        '-',
                        $user->name
                    )
                )
                . '-'
                . time()
                . '.'
                . $image->getClientOriginalExtension();

            $image->storeAs(
                'profiles',
                $imageName,
                'public'
            );

            $data['profile'] =
                'profiles/' . $imageName;
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE USER
        |--------------------------------------------------------------------------
        */

        $user->update($data);

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'message' =>
            'Profile updated successfully',

            'user' => [

                'id' =>
                $user->id,

                'name' =>
                $user->name,

                'email' =>
                $user->email,

                'contact' =>
                $user->contact,

                'address' =>
                $user->address,

                'role' =>
                $user->role,

                'profile' =>
                $user->profile
                    ? asset(
                        'storage/' .
                            $user->profile
                    )
                    : null
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CHANGE PASSWORD
    |--------------------------------------------------------------------------
    */

    public function changePassword(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([

            'current_password' => [
                'required'
            ],

            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed'
            ]
        ]);

        /*
        |--------------------------------------------------------------------------
        | CHECK CURRENT PASSWORD
        |--------------------------------------------------------------------------
        */

        if (
            !Hash::check(
                $data['current_password'],
                $user->password
            )
        ) {

            return response()->json([

                'errors' => [

                    'current_password' => [
                        'Current password is incorrect.'
                    ]
                ]

            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE PASSWORD
        |--------------------------------------------------------------------------
        */

        $user->update([

            'password' =>
            Hash::make(
                $data['password']
            )
        ]);

        return response()->json([

            'message' =>
            'Password updated successfully'
        ]);
    }
}
