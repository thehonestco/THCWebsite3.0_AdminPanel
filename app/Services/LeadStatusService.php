<?php

namespace App\Services;

use App\Models\Lead;
use Carbon\Carbon;

class LeadStatusService
{
    public static function calculate($opportunities): string
    {
        // 1️⃣ No opportunities
        if ($opportunities->isEmpty()) {
            return 'Fresh';
        }

        $statuses = $opportunities
            ->pluck('stage')
            ->filter()
            ->values();

        // 2️⃣ CONVERT (highest priority)
        if ($statuses->contains('convert')) {
            return 'Converted';
        }

        $activeStatuses = [
            'intro-call',
            'requirement',
            'proposal',
            'follow-up',
        ];

        // 3️⃣ OPPORTUNITY
        if ($statuses->intersect($activeStatuses)->isNotEmpty()) {
            dd("1");
            return 'Opportunity';
        }

        // 4️⃣ DROPPED (all dropped)
        if (
            $statuses->isNotEmpty() &&
            $statuses->every(fn ($s) => $s === 'drop')
        ) {
            // dd("2");
            return 'Dropped';
        }

        // 5️⃣ COLD
        if ($statuses->contains('hold')) {
            dd("3");
            return 'Cold';
        }
dd("4");
        return 'Fresh';
    }

    public static function update(int $leadId): void
    {
        $lead = Lead::with('opportunities')->find($leadId);
        if (!$lead) return;

        $newStage = self::calculate($lead->opportunities);

        // 🔥 Lead → Client conversion
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
