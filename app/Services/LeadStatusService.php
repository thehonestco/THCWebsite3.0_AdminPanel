<?php

namespace App\Services;

use App\Models\Lead;

class LeadStatusService
{
    public static function calculate($opportunities): string
    {
        if ($opportunities->isEmpty()) {
            return 'Fresh';
        }

        $statuses = $opportunities
            ->pluck('status')
            ->filter()
            ->unique();

        // 🔥 Highest priority
        if ($statuses->contains('Convert')) {
            return 'Converted';
        }

        $active = [
            'Intro Call',
            'Req. Gathering',
            'Proposal',
            'Follow Up',
        ];

        if ($statuses->intersect($active)->isNotEmpty()) {
            return 'Opportunity';
        }

        if ($statuses->every(fn ($s) => $s === 'Hold')) {
            return 'Cold';
        }

        if ($statuses->every(fn ($s) => $s === 'Dropped')) {
            return 'Dropped';
        }

        return 'Fresh';
    }

    public static function update(int $leadId): void
    {
        $lead = Lead::with('opportunities')->find($leadId);

        if (!$lead) {
            return;
        }

        $newStage = self::calculate($lead->opportunities);

        if ($lead->stage !== $newStage) {
            $lead->update(['stage' => $newStage]);
        }
    }
}
