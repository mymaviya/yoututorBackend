<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\ProposalVersion;

class ProposalVersionService
{
    public static function createSnapshot(Proposal $proposal): void
    {
        $proposal->load([
            'sections',
            'items'
        ]);

        ProposalVersion::create([
            'proposal_id' => $proposal->id,
            'version_no' => $proposal->versions()->count() + 1,
            'snapshot' => [
                'proposal' => $proposal->toArray(),
                'sections' => $proposal->sections->toArray(),
                'items' => $proposal->items->toArray(),
            ],
            'created_by' => auth()->id(),
        ]);
    }
}