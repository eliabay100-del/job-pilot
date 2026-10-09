<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\ComponentScore;
use App\Domain\Matching\Contracts\SemanticSimilarityProvider;
use App\Domain\Matching\MatchResult;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Support\Carbon;

/**
 * Deterministic match engine (SPEC section 19). Every component is scored
 * 0..100 from database facts only — no model decides anything factual. A
 * component that cannot be evaluated returns null so its weight is dropped and
 * the rest are renormalized, instead of dragging the score down.
 */
class ScoreJobMatch
{
    public function __construct(private readonly SemanticSimilarityProvider $semantic) {}

    public function handle(CandidateProfile $profile, Job $job): MatchResult
    {
        $profile->loadMissing(['skills', 'educations.educationLevel', 'preference']);
        $job->loadMissing(['skills', 'minEducationLevel', 'company']);

        $matchedSkills = [];
        $missingSkills = [];
        $weakAreas = [];
        $flags = [];
        $penalty = 0.0;

        $weights = (array) config('matching.weights');

        $components = [
            'skills' => $this->scoreSkills($profile, $job, $matchedSkills, $missingSkills, $weakAreas, $penalty),
            'experience' => $this->scoreExperience($profile, $job, $flags, $penalty),
            'education' => $this->scoreEducation($profile, $job, $flags, $penalty),
            'seniority' => $this->scoreSeniority($profile, $job),
            'location' => $this->scoreLocation($profile, $job),
            'work_mode' => $this->scoreWorkMode($profile, $job),
            'preference' => $this->scorePreference($profile, $job),
            'semantic' => $this->scoreSemantic($profile, $job),
        ];

        foreach ($components as $name => $component) {
            $components[$name] = new ComponentScore(
                $component->score,
                (int) ($weights[$name] ?? 0),
                $component->detail,
            );
        }

        if ($missingSkills !== []) {
            $flags[] = 'missing_required_skills';
        }

        // Nothing to score on either side is not the same as a bad fit: say so
        // explicitly rather than presenting 0 as a verdict.
        $scoreable = array_filter(
            $components,
            fn (ComponentScore $component) => $component->isApplied() && $component->weight > 0,
        );

        if ($scoreable === []) {
            $flags[] = 'insufficient_data';
        }

        return new MatchResult(
            candidateProfileId: (int) $profile->id,
            jobId: (int) $job->id,
            components: $components,
            matchedSkills: $matchedSkills,
            missingSkills: $missingSkills,
            weakAreas: $weakAreas,
            hardRequirementFlags: array_values(array_unique($flags)),
            penalty: round($penalty, 2),
            modelVersion: (string) config('matching.model_version'),
            computedAt: Carbon::now(),
        );
    }

    /**
     * Skills coverage, weighted by whether the posting marks them required.
     *
     * @param  array<int, array<string, mixed>>  $matchedSkills
     * @param  array<int, array<string, mixed>>  $missingSkills
     * @param  array<int, array<string, mixed>>  $weakAreas
     */
    private function scoreSkills(
        CandidateProfile $profile,
        Job $job,
        array &$matchedSkills,
        array &$missingSkills,
        array &$weakAreas,
        float &$penalty,
    ): ComponentScore {
        $jobSkills = $job->skills;
        if ($jobSkills->isEmpty()) {
            return new ComponentScore(null, 0, 'Posting lists no skills.');
        }

        $candidateSkills = [];
        foreach ($profile->skills as $candidateSkill) {
            $candidateSkills[(int) $candidateSkill->skill_id] = $candidateSkill;
        }

        $requiredWeight = (float) config('matching.skills.required_weight');
        $optionalWeight = (float) config('matching.skills.optional_weight');
        $partialCredit = (float) config('matching.skills.below_min_years_credit');

        $earned = 0.0;
        $possible = 0.0;
        $missingRequired = 0;

        foreach ($jobSkills as $skill) {
            $isRequired = (bool) $skill->pivot->is_required;
            $weight = $isRequired ? $requiredWeight : $optionalWeight;
            $possible += $weight;

            $minYears = $skill->pivot->min_years === null ? null : (int) $skill->pivot->min_years;
            $candidateSkill = $candidateSkills[(int) $skill->id] ?? null;

            if ($candidateSkill === null) {
                if ($isRequired) {
                    $missingRequired++;
                    $missingSkills[] = [
                        'id' => (int) $skill->id,
                        'name' => $skill->name,
                        'is_required' => true,
                        'min_years' => $minYears,
                    ];
                }

                continue;
            }

            $yearsUsing = $candidateSkill->years_using === null ? null : (float) $candidateSkill->years_using;
            $belowMinimum = $minYears !== null && $yearsUsing !== null && $yearsUsing < $minYears;

            $earned += $belowMinimum ? $weight * $partialCredit : $weight;

            $matchedSkills[] = [
                'id' => (int) $skill->id,
                'name' => $skill->name,
                'is_required' => $isRequired,
                'level' => $candidateSkill->level === null ? null : (int) $candidateSkill->level,
                'years_using' => $yearsUsing,
                'min_years' => $minYears,
            ];

            if ($belowMinimum) {
                $weakAreas[] = [
                    'type' => 'skill_years',
                    'skill_id' => (int) $skill->id,
                    'name' => $skill->name,
                    'message' => sprintf(
                        '%s: you have %s years, the posting asks for %d+.',
                        $skill->name,
                        rtrim(rtrim(number_format($yearsUsing, 1, '.', ''), '0'), '.'),
                        $minYears,
                    ),
                ];
            }
        }

        if ($missingRequired > 0) {
            $perSkill = (float) config('matching.penalties.missing_required_skill');
            $cap = (float) config('matching.penalties.missing_required_skill_cap');
            $penalty += min($missingRequired * $perSkill, $cap);
        }

        return new ComponentScore(
            $possible > 0 ? 100 * $earned / $possible : null,
            0,
            sprintf('%s of %s weighted skill points.', rtrim(rtrim(number_format($earned, 1, '.', ''), '0'), '.'), rtrim(rtrim(number_format($possible, 1, '.', ''), '0'), '.')),
        );
    }

    /**
     * @param  array<int, string>  $flags
     */
    private function scoreExperience(CandidateProfile $profile, Job $job, array &$flags, float &$penalty): ComponentScore
    {
        $min = $job->experience_years_min;
        $max = $job->experience_years_max;

        if ($min === null && $max === null) {
            return new ComponentScore(null, 0, 'Posting states no experience range.');
        }

        $years = (int) $profile->years_experience;
        $inBand = (float) config('matching.experience.in_band');

        if ($min !== null && $years < $min) {
            $flags[] = 'experience_below_min';
            $penalty += (float) config('matching.penalties.experience_below_min');

            return new ComponentScore(
                $min > 0 ? $inBand * $years / $min : $inBand,
                0,
                sprintf('%d years against a %d-year minimum.', $years, $min),
            );
        }

        if ($max !== null && $years > $max) {
            $floor = (float) config('matching.experience.over_max_floor');
            $step = (float) config('matching.experience.over_max_step');

            return new ComponentScore(
                max($floor, $inBand - $step * ($years - $max)),
                0,
                sprintf('%d years against a %d-year maximum.', $years, $max),
            );
        }

        return new ComponentScore($inBand, 0, sprintf('%d years, inside the %s–%s range.', $years, $min ?? '0', $max ?? 'open'));
    }

    /**
     * @param  array<int, string>  $flags
     */
    private function scoreEducation(CandidateProfile $profile, Job $job, array &$flags, float &$penalty): ComponentScore
    {
        $requiredLevel = $job->minEducationLevel;
        $requiredRank = $requiredLevel?->rank === null ? null : (int) $requiredLevel->rank;

        if ($requiredLevel === null || $requiredRank === null || $requiredRank <= 0) {
            return new ComponentScore(null, 0, 'Posting sets no minimum education level.');
        }

        $candidateRank = 0;
        $unranked = false;
        foreach ($profile->educations as $education) {
            $rank = $education->educationLevel?->rank;
            if ($rank === null) {
                $unranked = true;
                continue;
            }

            if ((int) $rank > $candidateRank) {
                $candidateRank = (int) $rank;
            }
        }

        // Education exists but is not mapped to a taxonomy level, so it cannot be
        // compared. Unscoreable is not the same as below the requirement.
        if ($candidateRank === 0 && $unranked) {
            return new ComponentScore(null, 0, 'Your education has no level set — add one to score this component.');
        }

        if ($candidateRank === 0) {
            $flags[] = 'education_below_min';
            $penalty += (float) config('matching.penalties.education_below_min');

            return new ComponentScore(0.0, 0, sprintf('No education recorded; %s required.', $requiredLevel->name));
        }

        if ($candidateRank >= $requiredRank) {
            return new ComponentScore(100.0, 0, sprintf('%s meets the %s requirement.', $this->levelName($profile, $candidateRank), $requiredLevel->name));
        }

        $flags[] = 'education_below_min';
        $penalty += (float) config('matching.penalties.education_below_min');

        return new ComponentScore(
            100 * $candidateRank / $requiredRank,
            0,
            sprintf('%s is below the required %s.', $this->levelName($profile, $candidateRank), $requiredLevel->name),
        );
    }

    private function scoreSeniority(CandidateProfile $profile, Job $job): ComponentScore
    {
        $order = (array) config('matching.seniority_order');
        $jobIndex = $job->seniority === null ? null : array_search($job->seniority, $order, true);

        if ($jobIndex === false || $jobIndex === null) {
            return new ComponentScore(null, 0, 'Posting sets no seniority level.');
        }

        $candidateLevel = $this->seniorityFromYears((int) $profile->years_experience);
        $candidateIndex = (int) array_search($candidateLevel, $order, true);
        $difference = $candidateIndex - (int) $jobIndex;
        $fit = (array) config('matching.seniority_fit');

        $score = match (true) {
            $difference === 0 => (float) $fit['exact'],
            $difference === 1 => (float) $fit['one_above'],
            $difference > 1 => (float) $fit['far_above'],
            $difference === -1 => (float) $fit['one_below'],
            $difference === -2 => (float) $fit['two_below'],
            default => (float) $fit['far_below'],
        };

        return new ComponentScore(
            $score,
            0,
            sprintf('Your level (%s) against the posting (%s).', $candidateLevel, $job->seniority),
        );
    }

    private function scoreLocation(CandidateProfile $profile, Job $job): ComponentScore
    {
        $jobCity = $this->normalize($job->city);
        if ($jobCity === null) {
            return new ComponentScore(null, 0, 'Posting lists no city.');
        }

        $config = (array) config('matching.location');
        $preference = $profile->preference;

        if ($jobCity === $this->normalize($profile->city)) {
            return new ComponentScore((float) $config['same_city'], 0, sprintf('Same city (%s).', $job->city));
        }

        $preferred = array_map($this->normalize(...), (array) ($preference?->locations ?? []));
        if (in_array($jobCity, $preferred, true)) {
            return new ComponentScore((float) $config['preferred_location'], 0, sprintf('%s is one of your preferred locations.', $job->city));
        }

        $jobRegion = $this->normalize($job->region);
        if ($jobRegion !== null && $jobRegion === $this->normalize($profile->region)) {
            return new ComponentScore((float) $config['same_region'], 0, sprintf('Same region (%s).', $job->region));
        }

        if ((bool) ($preference?->willing_to_relocate ?? false)) {
            return new ComponentScore((float) $config['relocatable'], 0, sprintf('%s — you are open to relocating.', $job->city));
        }

        return new ComponentScore((float) $config['other'], 0, sprintf('Role is in %s, you are in %s.', $job->city, $profile->city ?: 'an unlisted city'));
    }

    private function scoreWorkMode(CandidateProfile $profile, Job $job): ComponentScore
    {
        $preference = $profile->preference;
        $modes = array_filter(array_map('strval', (array) ($preference?->work_modes ?? [])));

        if ($modes === []) {
            return new ComponentScore(null, 0, 'No work-mode preference set.');
        }

        $config = (array) config('matching.work_mode');

        if (in_array((string) $job->work_mode, $modes, true)) {
            return new ComponentScore((float) $config['match'], 0, sprintf('%s is one of your accepted work modes.', $job->work_mode));
        }

        if ($job->work_mode === 'remote' && (bool) ($preference?->open_to_remote ?? false)) {
            return new ComponentScore((float) $config['match'], 0, 'Remote and you are open to remote work.');
        }

        return new ComponentScore(
            (float) $config['mismatch'],
            0,
            sprintf('Posting is %s; you prefer %s.', $job->work_mode, implode(', ', $modes)),
        );
    }

    private function scorePreference(CandidateProfile $profile, Job $job): ComponentScore
    {
        $preference = $profile->preference;
        if ($preference === null) {
            return new ComponentScore(null, 0, 'No preferences set.');
        }

        $config = (array) config('matching.preference');
        $match = (float) $config['match'];
        $signals = [];

        $employmentTypes = (array) ($preference->employment_types ?? []);
        if ($employmentTypes !== []) {
            $signals[] = in_array((string) $job->employment_type, array_map('strval', $employmentTypes), true)
                ? $match
                : (float) $config['employment_mismatch'];
        }

        $categories = array_map('intval', (array) ($preference->job_category_ids ?? []));
        if ($categories !== [] && $job->job_category_id !== null) {
            $signals[] = in_array((int) $job->job_category_id, $categories, true) ? $match : (float) $config['taxonomy_mismatch'];
        }

        $roles = array_map('intval', (array) ($preference->desired_job_role_ids ?? []));
        if ($roles !== [] && $job->job_role_id !== null) {
            $signals[] = in_array((int) $job->job_role_id, $roles, true) ? $match : (float) $config['taxonomy_mismatch'];
        }

        $industries = array_map('intval', (array) ($preference->desired_industry_ids ?? []));
        $jobIndustryId = $job->industry_id ?? $job->company?->industry_id;
        if ($industries !== [] && $jobIndustryId !== null) {
            $signals[] = in_array((int) $jobIndustryId, $industries, true) ? $match : (float) $config['taxonomy_mismatch'];
        }

        $salaryMin = $preference->salary_min_monthly_eth === null ? null : (float) $preference->salary_min_monthly_eth;
        if ($salaryMin !== null && $salaryMin > 0) {
            $jobMax = $job->salary_max_monthly ?? $job->salary_min_monthly;

            if ($jobMax !== null) {
                $signals[] = $jobMax >= $salaryMin
                    ? $match
                    : max(0.0, $match * $jobMax / $salaryMin);
            } elseif ($job->salary_negotiable) {
                $signals[] = (float) $config['salary_negotiable'];
            }
        }

        if ($signals === []) {
            return new ComponentScore(null, 0, 'Preferences do not overlap anything this posting states.');
        }

        return new ComponentScore(
            array_sum($signals) / count($signals),
            0,
            sprintf('%d preference signal(s) compared.', count($signals)),
        );
    }

    private function scoreSemantic(CandidateProfile $profile, Job $job): ComponentScore
    {
        $similarity = $this->semantic->similarity($profile, $job);

        if ($similarity === null) {
            return new ComponentScore(null, 0, sprintf('Semantic fit unavailable (%s).', $this->semantic->name()));
        }

        return new ComponentScore(max(0.0, min(100.0, $similarity)), 0, sprintf('Semantic fit from %s.', $this->semantic->name()));
    }

    private function seniorityFromYears(int $years): string
    {
        foreach ((array) config('matching.seniority_ladder') as $rung) {
            if ($years <= (int) $rung['max_years']) {
                return (string) $rung['level'];
            }
        }

        return 'manager';
    }

    private function levelName(CandidateProfile $profile, int $rank): string
    {
        foreach ($profile->educations as $education) {
            if ((int) ($education->educationLevel?->rank ?? 0) === $rank) {
                return (string) $education->educationLevel?->name;
            }
        }

        return 'Your highest education level';
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtolower($value);
    }
}
