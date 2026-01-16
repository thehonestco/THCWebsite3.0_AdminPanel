<?php

namespace App\Services;

use App\Models\Lead;
use Carbon\Carbon;

class LeadStatusService
{
    public static function calculate($opportunities): string
    {
        if ($opportunities->isEmpty()) {
            return 'Fresh';
        }

        $total = $opportunities->count();

        $statuses = $opportunities
            ->pluck('status')
            ->filter()
            ->unique();

        $filledCount = $opportunities
            ->whereNotNull('status')
            ->count();

        /**
         * 🔥 CONVERT → CLIENT
         */
        if ($statuses->contains('convert')) {
            return 'Converted';
        }

        /**
         * 🟢 ACTIVE
         */
        $active = ['intro-call', 'requirement', 'proposal'];

        if ($statuses->intersect($active)->isNotEmpty()) {
            return 'Opportunity';
        }

        /**
         * 🟡 COLD
         * Only when ALL opportunities are hold
         */
        if (
            $filledCount === $total &&
            $statuses->count() === 1 &&
            $statuses->contains('hold')
        ) {
            return 'Cold';
        }

        /**
         * 🔴 DROPPED
         */
        if (
            $filledCount === $total &&
            $statuses->count() === 1 &&
            $statuses->contains('drop')
        ) {
            return 'Dropped';
        }

        return 'Fresh';
    }

    public static function update(int $leadId): void
    {
        $lead = Lead::with('opportunities')->find($leadId);
        if (!$lead) return;

        $newStage = self::calculate($lead->opportunities);

        if ($newStage === 'Converted' && !$lead->is_converted) {
            $lead->update([
                'stage'        => 'Converted',
                'is_converted' => true,
                'converted_at' => Carbon::now(),
            ]);
            return;
        }

        if ($lead->stage !== $newStage) {
            $lead->update(['stage' => $newStage]);
        }
    }
}
