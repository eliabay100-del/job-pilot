<?php

declare(strict_types=1);

namespace Tests\Feature\CV;

use App\Models\CvVersion;
use App\Models\Document;
use App\Models\Company;
use App\Models\Job;
use App\Models\Skill;
use Database\Seeders\TaxonomySeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CvUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_upload_stores_file_privately_and_creates_version(): void
    {
        $response = $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('my cv.pdf', 500, 'application/pdf'),
            'title' => 'Main CV',
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'uploaded')
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.parse_status', 'pending')
            ->assertJsonPath('data.document.original_name', 'my cv.pdf');

        $document = $this->user->documents()->firstOrFail();

        // Safe generated filename; original name never used on disk.
        $this->assertStringNotContainsString('my cv', $document->path);
        $this->assertSame($this->user->id, $document->uploaded_by);
        $this->assertSame(64, strlen($document->sha256));
        Storage::disk('local')->assertExists($document->path);

        $this->assertNotNull($response->json('data.download_url'));
    }

    public function test_upload_increments_version_numbers(): void
    {
        $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('cv1.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.version_number', 1);

        $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('cv2.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.version_number', 2);

        $this->getJson('/api/v1/cv')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_parse_stores_reviewable_extraction_without_mapping_profile_data(): void
    {
        $profile = $this->user->ensureCandidateProfile();
        $path = 'documents/1/resume.doc';
        Storage::disk('local')->put($path, 'Name: Hana Tesfaye | Email: hana@example.com | Location: Addis Ababa');
        $document = Document::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'uploaded_by' => $this->user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'resume.doc',
            'mime_type' => 'application/msword',
            'size_bytes' => 72,
            'sha256' => hash('sha256', 'cv'),
            'scan_status' => 'skipped',
            'scanned_at' => now(),
        ]);
        $cv = $profile->cvVersions()->create([
            'document_id' => $document->id,
            'title' => 'Resume',
            'kind' => 'uploaded',
            'version_number' => 1,
        ]);

        $this->postJson("/api/v1/cv/{$cv->id}/parse")
            ->assertOk()
            ->assertJsonPath('data.parse_status', 'needs_confirmation')
            ->assertJsonPath('data.structured_data.name', 'Hana Tesfaye')
            ->assertJsonPath('data.structured_data.email', 'hana@example.com');

        $this->assertDatabaseHas('cv_versions', [
            'id' => $cv->id,
            'parse_status' => 'needs_confirmation',
        ]);
        $this->assertDatabaseMissing('candidate_profiles', ['display_name' => 'Hana Tesfaye']);

        $this->postJson("/api/v1/cv/{$cv->id}/confirm", ['fields' => ['name', 'location']])
            ->assertOk()
            ->assertJsonPath('data.parse_status', 'confirmed');

        $this->assertDatabaseHas('candidate_profiles', [
            'id' => $profile->id,
            'display_name' => 'Hana Tesfaye',
            'city' => 'Addis Ababa',
        ]);
    }

    public function test_tailor_creates_traceable_version_from_profile_facts(): void
    {
        $this->seed(TaxonomySeeder::class);
        $profile = $this->user->ensureCandidateProfile();
        $skill = Skill::where('name', 'Laravel')->firstOrFail();
        $profile->skills()->create(['skill_id' => $skill->id, 'level' => 4, 'source' => 'candidate']);
        $source = $profile->cvVersions()->create([
            'title' => 'Main CV',
            'kind' => 'uploaded',
            'structured_data' => ['name' => 'Hana Tesfaye'],
            'parse_status' => 'confirmed',
            'version_number' => 1,
        ]);
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'verification_status' => 'verified']);
        $job = Job::create([
            'company_id' => $company->id,
            'title' => 'Laravel Engineer',
            'slug' => 'laravel-engineer',
            'description' => 'Build Laravel services.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $job->skills()->attach($skill->id, ['is_required' => true]);

        $this->postJson("/api/v1/cv/{$source->id}/tailor", ['target_job_id' => $job->id])
            ->assertOk()
            ->assertJsonPath('data.kind', 'tailored')
            ->assertJsonPath('data.tailored_for_job_id', $job->id)
            ->assertJsonPath('data.structured_data.source_cv_version_id', $source->id)
            ->assertJsonPath('data.structured_data.skills.0.relevant_to_job', true);
    }

    public function test_cover_letter_is_stored_with_source_and_usage_metadata(): void
    {
        $this->seed(TaxonomySeeder::class);
        $profile = $this->user->ensureCandidateProfile();
        $skill = Skill::where('name', 'Laravel')->firstOrFail();
        $profile->skills()->create(['skill_id' => $skill->id, 'level' => 4, 'source' => 'candidate']);
        $source = $profile->cvVersions()->create([
            'title' => 'Main CV', 'kind' => 'uploaded', 'parse_status' => 'confirmed', 'version_number' => 1,
        ]);
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'verification_status' => 'verified']);
        $job = Job::create([
            'company_id' => $company->id, 'title' => 'Laravel Engineer', 'slug' => 'laravel-engineer',
            'description' => 'Build Laravel services.', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->postJson("/api/v1/cv/{$source->id}/cover-letter", ['target_job_id' => $job->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.source_cv_version_id', $source->id)
            ->assertJsonPath('data.provider', 'local')
            ->assertJsonPath('data.job_id', $job->id);

        $this->assertDatabaseHas('cover_letters', ['source_cv_version_id' => $source->id, 'job_id' => $job->id]);
        $this->assertDatabaseHas('ai_usage', ['operation' => 'cover_letter', 'user_id' => $this->user->id]);
    }

    public function test_upload_rejects_disallowed_extension_and_mime(): void
    {
        $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('cv.exe', 10, 'application/x-msdownload'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('big.pdf', 6 * 1024, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_stored_extension_comes_from_mime_not_client_filename(): void
    {
        // A .php client name is blocked outright by Laravel's shouldBlockPhpUpload,
        // so use a different mismatch to prove the extension is server-derived.
        $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('resume.exe', 10, 'application/pdf'),
        ])->assertCreated();

        $document = $this->user->documents()->firstOrFail();

        $this->assertSame('resume.exe', $document->original_name);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertStringEndsWith('.pdf', $document->path);
    }

    public function test_upload_requires_authentication(): void
    {
        auth()->forgetGuards();

        $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])->assertStatus(401);
    }

    public function test_download_streams_own_file_only(): void
    {
        $created = $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $cvId = $created->json('data.id');

        $download = $this->get("/api/v1/cv/{$cvId}/download")->assertOk();

        // Serves the original filename, not the generated uuid name.
        $this->assertStringContainsString(
            'cv.pdf',
            (string) $download->headers->get('content-disposition'),
        );

        $other = User::factory()->create();
        $otherCv = CvVersion::create([
            'candidate_profile_id' => $other->ensureCandidateProfile()->id,
            'title' => 'Other CV',
            'kind' => 'uploaded',
            'version_number' => 1,
        ]);

        $this->getJson("/api/v1/cv/{$otherCv->id}")->assertStatus(403);
        $this->getJson("/api/v1/cv/{$otherCv->id}/download")->assertStatus(403);
        $this->deleteJson("/api/v1/cv/{$otherCv->id}")->assertStatus(403);
        $this->assertDatabaseCount('cv_versions', 2);
        $this->assertDatabaseHas('cv_versions', ['id' => $cvId]);
    }

    public function test_non_numeric_identifier_is_a_not_found_not_a_database_error(): void
    {
        // Without the numeric route pattern this reached Postgres as a bigint
        // bind and failed with "invalid input syntax for type bigint" (500).
        $this->getJson('/api/v1/cv/undefined')
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->deleteJson('/api/v1/cv/undefined')->assertStatus(404);
        $this->getJson('/api/v1/documents/undefined/download')->assertStatus(404);
    }

    public function test_destroy_removes_version_and_unreferenced_file(): void
    {
        $created = $this->postJson('/api/v1/cv/upload', [
            'file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $cvId = $created->json('data.id');
        $path = $this->user->documents()->firstOrFail()->path;

        $this->deleteJson("/api/v1/cv/{$cvId}")->assertOk();

        $this->assertDatabaseCount('cv_versions', 0);
        $this->assertDatabaseCount('documents', 0);
        Storage::disk('local')->assertMissing($path);
    }
}
