<?php

declare(strict_types=1);

return [
    // Bumped whenever weights/penalties change so stored matches record which
    // configuration produced them.
    'model_version' => 'deterministic-v1',

    // Component weights (relative). SPEC section 19 requires them configurable.
    // A component that cannot be scored for a given pair returns null and its
    // weight drops out, so the remaining weights are renormalized.
    'weights' => [
        'skills' => 30,
        'experience' => 20,
        'education' => 15,
        'seniority' => 10,
        'location' => 10,
        'work_mode' => 5,
        'preference' => 10,
        'semantic' => 0,
    ],

    // Subtracted from the weighted score, clamped to 0..100.
    'penalties' => [
        'missing_required_skill' => 4,
        'missing_required_skill_cap' => 20,
        'experience_below_min' => 8,
        'education_below_min' => 8,
    ],

    // Skills coverage. Required skills count double; a skill the candidate has
    // but with fewer years than the posting asks for earns partial credit.
    'skills' => [
        'required_weight' => 2,
        'optional_weight' => 1,
        'below_min_years_credit' => 0.5,
    ],

    'experience' => [
        'in_band' => 100,
        // Over-qualification is only mildly penalized, never below the floor.
        'over_max_floor' => 60,
        'over_max_step' => 5,
    ],

    // Deterministic years-of-experience → seniority ladder (jobs.seniority enum).
    'seniority_ladder' => [
        ['max_years' => 0, 'level' => 'entry'],
        ['max_years' => 2, 'level' => 'junior'],
        ['max_years' => 5, 'level' => 'mid'],
        ['max_years' => 9, 'level' => 'senior'],
        ['max_years' => 12, 'level' => 'lead'],
        ['max_years' => PHP_INT_MAX, 'level' => 'manager'],
    ],

    'seniority_order' => [
        'entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive',
    ],

    // Score by candidate-level index minus job-level index.
    'seniority_fit' => [
        'exact' => 100,
        'one_above' => 85,
        'far_above' => 70,
        'one_below' => 70,
        'two_below' => 50,
        'far_below' => 35,
    ],

    'location' => [
        'same_city' => 100,
        'preferred_location' => 90,
        'same_region' => 80,
        'relocatable' => 60,
        'other' => 30,
    ],

    'work_mode' => [
        'match' => 100,
        'mismatch' => 40,
    ],

    // Preference is the mean of the sub-signals that the candidate has set.
    'preference' => [
        'match' => 100,
        'employment_mismatch' => 40,
        'taxonomy_mismatch' => 50,
        'salary_negotiable' => 80,
    ],

    // Recommendations by overall score band, highest threshold first.
    'recommendations' => [
        80 => 'Strong candidate. Apply.',
        60 => 'Good fit — review the gaps before applying.',
        40 => 'Partial fit — consider closing the listed gaps first.',
        0 => 'Not a fit yet — focus on the missing requirements.',
    ],

    // Used when neither the posting nor the profile states anything scoreable.
    'insufficient_data_recommendation' => 'Not enough data to score this match yet — add skills, experience and education.',

    // How many published jobs a single /matches request is allowed to score.
    'max_jobs_per_request' => 500,
];
