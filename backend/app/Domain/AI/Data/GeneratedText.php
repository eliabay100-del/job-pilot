<?php

declare(strict_types=1);

namespace App\Domain\AI\Data;

final readonly class GeneratedText
{
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public string $promptVersion,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {
    }
}