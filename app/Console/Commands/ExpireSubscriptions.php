<?php

namespace App\Console\Commands;

use App\Models\LicenseKey;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire subscriptions and licenses automatically';

    public function handle()
    {
        DB::transaction(function () {

            $subscriptions = Subscription::whereIn('status', [
                    'trial',
                    'active'
                ])
                ->whereDate('ends_at', '<', now()->toDateString())
                ->get();

            foreach ($subscriptions as $subscription) {

                $subscription->update([
                    'status' => 'expired'
                ]);

                LicenseKey::where('subscription_id', $subscription->id)
                    ->update([
                        'status' => 'expired'
                    ]);
            }
        });

        $this->info('Expired subscriptions updated successfully.');

        return self::SUCCESS;
    }
}