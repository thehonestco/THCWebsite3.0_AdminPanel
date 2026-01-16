<?php

namespace App\Services;

use App\Models\Lead;
use Carbon\Carbon;

class LeadStatusService
{
    /**
     * Decide lead stage based on opportunity statuses
     */
    public static function calculate($opportunities): string
    {
        if ($opportunities->isEmpty()) {
            return 'Fresh';
        }

        $statuses = $opportunities
            ->pluck('status')
            ->filter()
            ->unique();

        /**
         * 🔥 HIGHEST PRIORITY
         * If ANY opportunity is converted
         */
        if ($statuses->contains('convert')) {
            return 'Converted';
        }

        /**
         * 🟢 Active opportunity stages
         */
        $active = [
            'intro-call',
            'requirement',
            'proposal',
        ];

        if ($statuses->intersect($active)->isNotEmpty()) {
            return 'Opportunity';
        }

        /**
         * 🟡 All on hold
         */
        if ($statuses->every(fn ($s) => $s === 'hold')) {
            return 'Cold';
        }

        /**
         * 🔴 All dropped
         */
        if ($statuses->every(fn ($s) => $s === 'drop')) {
            return 'Dropped';
        }

        return 'Fresh';
    }

    /**
     * Update lead stage + convert to client if needed
     */
    public static function update(int $leadId): void
    {
        $lead = Lead::with('opportunities')->find($leadId);

        if (!$lead) {
            return;
        }

        $newStage = self::calculate($lead->opportunities);

        /**
         * 🔥 AUTO CONVERT LEAD → CLIENT
         */
        if (
            $newStage === 'Converted' &&
            !$lead->is_converted
        ) {
            $lead->update([
                'stage'        => 'Converted',
                'is_converted' => true,
                'converted_at' => Carbon::now(),
            ]);

            return;
        }

        /**
         * Normal stage update
         */
        if ($lead->stage !== $newStage) {
            $lead->update([
                'stage' => $newStage
            ]);
        }
    }
}
