<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EducationLevel;
use App\Models\Industry;
use App\Models\Institution;
use App\Models\JobCategory;
use App\Models\JobRole;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only taxonomy lookups (Layer 1 platform data — the database is the
 * source of truth). Used by the frontend for typeaheads and selects.
 */
class TaxonomyController extends Controller
{
    public function skills(Request $request): JsonResponse
    {
        $skills = Skill::query()
            ->where('is_active', true)
            ->when($request->string('q')->toString(), fn ($q, string $term) => $q->where('name', 'ilike', "%{$term}%"))
            ->orderBy('name')
            ->limit(min((int) $request->integer('limit', 50), 100))
            ->get(['id', 'name', 'slug']);

        return new JsonResponse(['success' => true, 'data' => $skills]);
    }

    public function educationLevels(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => EducationLevel::orderBy('rank')->get(['id', 'name']),
        ]);
    }

    public function institutions(Request $request): JsonResponse
    {
        $institutions = Institution::query()
            ->when($request->string('q')->toString(), fn ($q, string $term) => $q->where('name', 'ilike', "%{$term}%"))
            ->orderBy('name')
            ->limit(min((int) $request->integer('limit', 50), 100))
            ->get(['id', 'name', 'type', 'city']);

        return new JsonResponse(['success' => true, 'data' => $institutions]);
    }

    public function industries(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => Industry::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function jobCategories(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => JobCategory::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function jobRoles(Request $request): JsonResponse
    {
        $roles = JobRole::query()
            ->where('is_active', true)
            ->when($request->integer('job_category_id'), fn ($q, int $id) => $q->where('job_category_id', $id))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'job_category_id']);

        return new JsonResponse(['success' => true, 'data' => $roles]);
    }
}
