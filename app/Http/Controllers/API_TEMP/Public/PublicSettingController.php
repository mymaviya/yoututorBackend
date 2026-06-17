<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class PublicSettingController extends Controller
{
    public function index()
    {
        $settings = Setting::where('is_public', true)
            ->get()
            ->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }
}