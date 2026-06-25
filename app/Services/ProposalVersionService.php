<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\ProposalVersion;
use Illuminate\Support\Facades\Schema;

class ProposalVersionService
{
    public static function createSnapshot(Proposal $proposal): void
    {
        $proposal->load([
            'sections',
            'items',
        ]);

        $payload = [
            'proposal_id' => $proposal->id,
            'version_no' => $proposal->versions()->count() + 1,
            'snapshot' => [
                'proposal' => $proposal->toArray(),
                'sections' => $proposal->sections->toArray(),
                'items' => $proposal->items->toArray(),
            ],
            'created_by' => auth()->id(),
        ];

        if (Schema::hasColumn('proposal_versions', 'subscription_id')) {
            $payload['subscription_id'] = $proposal->subscription_id ?? auth()->user()?->subscription_id;
        }

        ProposalVersion::create($payload);
    }
}
