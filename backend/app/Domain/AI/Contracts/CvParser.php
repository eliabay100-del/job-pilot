<?php

declare(strict_types=1);

namespace App\Domain\AI\Contracts;

interface CvParser
{
    /** @return array{structured_data: array<string, mixed>, confidence: float} */
    public function parse(string $text): array;
}