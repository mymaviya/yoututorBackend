<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginHoliday;
use Illuminate\Http\Request;
use App\Services\AuditService;

class LoginHolidayController extends Controller
{
    public function index()
    {
        return LoginHoliday::latest('date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date|unique:login_holidays,date',
            'title' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        AuditService::log('Holidays','Create','Login holiday created for date: ' . $data['date'], null, $data);

        return LoginHoliday::create([
            'date' => $data['date'],
            'title' => $data['title'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Request $request, LoginHoliday $loginHoliday)
    {
        $data = $request->validate([
            'date' => 'required|date|unique:login_holidays,date,' . $loginHoliday->id,
            'title' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $oldData = $loginHoliday->toArray();
        $loginHoliday->update($data);

        AuditService::log('Holidays','Update','Login holiday updated for date: ' . $data['date'], $oldData, $loginHoliday->toArray());

        return response()->json([
            'message' => 'Holiday updated successfully',
            'data' => $loginHoliday,
        ]);
    }

    public function destroy(LoginHoliday $loginHoliday)
    {
        $oldData = $loginHoliday->toArray();
        $loginHoliday->delete();

        AuditService::log('Holidays','Delete','Login holiday deleted for date: ' . $oldData['date'], $oldData, null);

        return response()->json([
            'message' => 'Holiday deleted successfully',
        ]);
    }
}
