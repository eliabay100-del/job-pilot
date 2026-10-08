<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Companies\Resources\CompanyResource;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    public function show(Request $request, Company $company): JsonResponse
    {
        Gate::authorize('view', $company);

        $company->load('industry')->loadCount(['jobs' => fn ($query) => $query->published()]);

        return new JsonResponse(['success' => true, 'data' => (new CompanyResource($company))->resolve()]);
    }
}
