<?php

declare(strict_types=1);

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\TextGenerationProvider;
use App\Domain\AI\Data\GeneratedText;
use InvalidArgumentException;

final class LocalTextGenerationProvider implements TextGenerationProvider
{
    public function generate(string $operation, array $context): GeneratedText
    {
        if ($operation !== 'cover_letter') {
            throw new InvalidArgumentException("Unsupported local AI operation: {$operation}");
        }

        $candidateName = (string) ($context['candidate_name'] ?? 'Candidate');
        $jobTitle = (string) ($context['job_title'] ?? 'the advertised role');
        $companyName = (string) ($context['company_name'] ?? 'your organization');
        $skills = array_values(array_filter(array_map('strval', $context['skills'] ?? [])));
        $skillLine = $skills === [] ? 'my professional experience' : implode(', ', array_slice($skills, 0, 5));

        $content = "Dear Hiring Manager,\n\n"
            . "I am writing to apply for the {$jobTitle} role at {$companyName}. "
            . "My background includes {$skillLine}, and I would welcome the opportunity to contribute these skills to your team.\n\n"
            . "I would be glad to discuss how my experience can support {$companyName}'s goals. Thank you for considering my application.\n\n"
            . "Sincerely,\n{$candidateName}";

        return new GeneratedText($content, 'local', 'template-v1', 'cover-letter-v1');
    }
}