<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Domain\AI\Contracts\TextGenerationProvider;
use App\Domain\AI\Providers\LocalTextGenerationProvider;
use Tests\TestCase;

class TextGenerationProviderTest extends TestCase
{
    public function test_local_provider_generates_traceable_cover_letter_text(): void
    {
        $provider = $this->app->make(TextGenerationProvider::class);

        $result = $provider->generate('cover_letter', [
            'candidate_name' => 'Hana Tesfaye',
            'job_title' => 'Laravel Engineer',
            'company_name' => 'Acme',
            'skills' => ['Laravel', 'PostgreSQL'],
        ]);

        $this->assertInstanceOf(LocalTextGenerationProvider::class, $provider);
        $this->assertSame('local', $result->provider);
        $this->assertSame('cover-letter-v1', $result->promptVersion);
        $this->assertStringContainsString('Hana Tesfaye', $result->content);
        $this->assertStringContainsString('Laravel Engineer', $result->content);
        $this->assertStringContainsString('Laravel, PostgreSQL', $result->content);
    }
}