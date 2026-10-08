<?php

declare(strict_types=1);

namespace App\Domain\Candidates\Actions;

use App\Models\CandidateProfile;
use Illuminate\Support\Carbon;

/**
 * Layer 2 deterministic intelligence (SPEC section 2): years_experience is
 * computed from experience rows with overlapping intervals merged — never
 * taken from user input or AI.
 */
class RecomputeYearsExperience
{
    public function handle(CandidateProfile $profile): int
    {
        $intervals = $profile->experiences()
            ->get(['start_date', 'end_date', 'is_current'])
            ->map(fn ($exp) => [
                $exp->start_date->copy(),
                ($exp->is_current || $exp->end_date === null)
                    ? Carbon::today()
                    : ($exp->end_date->isFuture() ? Carbon::today() : $exp->end_date->copy()),
            ])
            ->filter(fn (array $iv) => $iv[1]->greaterThan($iv[0]))
            ->sortBy(fn (array $iv) => $iv[0]->timestamp)
            ->values();

        $days = 0;
        $mergedStart = null;
        $mergedEnd = null;

        foreach ($intervals as [$start, $end]) {
            if ($mergedEnd !== null && $start->lessThanOrEqualTo($mergedEnd)) {
                if ($end->greaterThan($mergedEnd)) {
                    $mergedEnd = $end;
                }

                continue;
            }

            if ($mergedStart !== null) {
                $days += $mergedStart->diffInDays($mergedEnd);
            }

            $mergedStart = $start;
            $mergedEnd = $end;
        }

        if ($mergedStart !== null) {
            $days += $mergedStart->diffInDays($mergedEnd);
        }

        $years = (int) floor($days / 365);
        $profile->forceFill(['years_experience' => $years])->saveQuietly();

        return $years;
    }
}
