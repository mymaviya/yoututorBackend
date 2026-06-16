<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\LicenseKey;
use App\Models\User;
use App\Models\AppNotification;
use App\Mail\SubscriptionExpiryReminderMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ProcessExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-expiry';

    protected $description = 'Process expiring and expired subscriptions';

    public function handle(): int
    {
        $this->info('Processing subscriptions...');

        $today = Carbon::today();

        /*
        |--------------------------------------------------------------------------
        | Expire Active / Trial Subscriptions
        |--------------------------------------------------------------------------
        */

        $expiredSubscriptions = Subscription::whereIn('status', ['active', 'trial'])
            ->whereDate('ends_at', '<', $today)
            ->get();

        foreach ($expiredSubscriptions as $subscription) {

            $subscription->update([
                'status' => 'expired'
            ]);

            LicenseKey::where('subscription_id', $subscription->id)
                ->update([
                    'status' => 'expired'
                ]);

            if ($subscription->email) {
                Mail::to($subscription->email)
                    ->queue(new SubscriptionExpiryReminderMail($subscription->load('plan'),0)
                    );
            }


            $this->createNotification(
                'Subscription Expired',
                "{$subscription->school_name} subscription has expired."
            );

            $this->line(
                "Expired subscription: {$subscription->school_name}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Expiry Reminders
        |--------------------------------------------------------------------------
        */

        foreach ([7, 3, 1] as $days) {

            $subscriptions = Subscription::whereIn('status', ['active', 'trial'])
                ->whereDate('ends_at', $today->copy()->addDays($days))
                ->get();

            foreach ($subscriptions as $subscription) {

                $message = match ($days) {
                    7 => "Subscription expires in 7 days.",
                    3 => "Subscription expires in 3 days.",
                    default => "Subscription expires tomorrow.",
                };

                if ($subscription->email) {
                    Mail::to($subscription->email)
                        ->queue(
                            new SubscriptionExpiryReminderMail($subscription->load('plan'), $days)
                        );
                }

                $this->createNotification(
                    'Subscription Expiry Reminder',
                    "{$subscription->school_name}: {$message}"
                );

                $this->line(
                    "Reminder sent for {$subscription->school_name} ({$days} days)"
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Expire License Keys Separately
        |--------------------------------------------------------------------------
        */

        LicenseKey::where('status', 'active')
            ->whereDate('expires_at', '<', $today)
            ->update([
                'status' => 'expired'
            ]);

        $this->info('Subscription expiry processing completed.');

        return self::SUCCESS;
    }

    protected function createNotification(
        string $title,
        string $message
    ): void {

        if (!class_exists(AppNotification::class)) {
            return;
        }

        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {

            AppNotification::create([
                'user_id' => $admin->id,
                'title' => $title,
                'message' => $message,
                'type' => 'subscription',
                'is_read' => false,
            ]);
        }
    }
}
