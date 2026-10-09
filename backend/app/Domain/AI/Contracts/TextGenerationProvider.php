<?php

declare(strict_types=1);

namespace App\Domain\AI\Contracts;

use App\Domain\AI\Data\GeneratedText;

interface TextGenerationProvider
{
    /**
     * @param array<string, mixed> $context
     */
    public function generate(string $operation, array $context): GeneratedText;
}