<?php

namespace App\Imports;

use App\Models\Role;
use App\Models\User;
use App\Models\Teacher;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class TeachersImport implements ToCollection
{
    public int $imported = 0;
    public int $skipped = 0;
    public array $errors = [];
    public array $credentials = [];

    public function collection(Collection $rows)
    {
        $teacherRole = Role::where('slug', 'teacher')->first();

        foreach ($rows->skip(1) as $index => $row) {
            $rowNumber = $index + 1;

            $name = trim($row[0] ?? '');
            $email = trim($row[1] ?? '');
            $mobile = trim($row[2] ?? '');
            $qualification = trim($row[3] ?? '');
            $address = trim($row[4] ?? '');

            if (!$name || !$email || !$mobile) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Name, Email and Mobile are required.";
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Email already exists.";
                continue;
            }

            $passwordPlain = $this->generatePassword($name, $mobile);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'contact' => $mobile,
                'address' => $address,
                'role' => 'teacher',
                'role_id' => $teacherRole?->id,
                'password' => Hash::make($passwordPlain),
                'is_active' => true,
                'login_enabled' => true,
                'login_start_date' => now()->toDateString(),
                'password_change_required' => true,
                'password_changed_at' => null,
            ]);

            Teacher::create([
                'user_id' => $user->id,
                'qualification' => $qualification,
                'is_active' => true,
            ]);

            $this->credentials[] = [
                'name' => $name,
                'email' => $email,
                'password' => $passwordPlain,
            ];

            $this->imported++;
        }
    }

    private function generatePassword($name, $mobile): string
    {
        $firstName = explode(' ', trim($name))[0];
        $lastFour = substr(preg_replace('/\D/', '', $mobile), -4);

        return Str::ucfirst(Str::lower($firstName)) . $lastFour;
    }
}
