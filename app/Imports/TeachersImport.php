<?php

namespace App\Imports;

use App\Models\Role;
use App\Models\User;
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

    public function __construct(
        private readonly ?int $subscriptionId = null
    ) {}

    public function collection(Collection $rows)
    {
        $teacherRole = Role::where('slug', 'teacher')->first();

        if (!$teacherRole) {
            $this->errors[] = "Teacher role not found. Please seed roles first.";
            return;
        }

        foreach ($rows->skip(1) as $index => $row) {
            $rowNumber = $index + 2;

            $name = trim((string) ($row[0] ?? ''));
            $email = strtolower(trim((string) ($row[1] ?? '')));
            $mobile = trim((string) ($row[2] ?? ''));
            $address = trim((string) ($row[4] ?? ''));

            if (!$name || !$email || !$mobile) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Name, Email and Mobile are required.";
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Invalid email address.";
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Email already exists.";
                continue;
            }

            if ($this->subscriptionId) {
                $subscription = \App\Models\Subscription::find($this->subscriptionId);

                if ($subscription && $subscription->max_users) {
                    $currentUsers = User::where('subscription_id', $this->subscriptionId)->count();

                    if ($currentUsers >= $subscription->max_users) {
                        $this->skipped++;
                        $this->errors[] = "Row {$rowNumber}: User limit reached for this subscription.";
                        continue;
                    }
                }
            }

            $passwordPlain = $this->generatePassword($name, $mobile);

            User::create([
                'subscription_id' => $this->subscriptionId,
                'name' => $name,
                'email' => $email,
                'contact' => $mobile,
                'address' => $address ?: null,
                'role' => 'teacher',
                'role_id' => $teacherRole->id,
                'password' => Hash::make($passwordPlain),
                'is_active' => true,
                'login_enabled' => true,
                'login_start_date' => now()->toDateString(),
                'password_change_required' => true,
            ]);

            $this->credentials[] = [
                'name' => $name,
                'email' => $email,
                'password' => $passwordPlain,
            ];

            $this->imported++;
        }
    }

    private function generatePassword(string $name, string $mobile): string
    {
        $firstName = explode(' ', trim($name))[0] ?: 'Teacher';
        $lastFour = substr(preg_replace('/\D/', '', $mobile), -4) ?: '0000';

        return Str::ucfirst(Str::lower($firstName)) . $lastFour;
    }
}
