<?php

declare(strict_types=1);

namespace Tests\Feature\CV;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentTest extends TestCase
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

    public function test_store_lists_and_downloads_documents(): void
    {
        $created = $this->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->image('degree.png', 400, 300),
        ])->assertCreated()
            ->assertJsonPath('data.original_name', 'degree.png')
            ->assertJsonPath('data.scan_status', 'skipped');

        // Internal storage details must never leak into API responses.
        $this->assertArrayNotHasKey('path', $created->json('data'));
        $this->assertArrayNotHasKey('disk', $created->json('data'));

        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(1, 'data');

        $document = $this->user->documents()->firstOrFail();
        $this->get("/api/v1/documents/{$document->id}/download")->assertOk();
    }

    public function test_store_rejects_disallowed_types(): void
    {
        $this->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('script.sh', 10, 'application/x-sh'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_other_users_documents_are_forbidden(): void
    {
        $created = $this->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('file.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $id = $created->json('data.id');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/documents/{$id}/download")->assertStatus(403);
        $this->deleteJson("/api/v1/documents/{$id}")->assertStatus(403);
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_destroy_removes_file_from_disk(): void
    {
        $created = $this->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('file.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $path = $this->user->documents()->firstOrFail()->path;

        $this->deleteJson("/api/v1/documents/{$created->json('data.id')}")->assertOk();

        $this->assertDatabaseCount('documents', 0);
        Storage::disk('local')->assertMissing($path);
    }
}
