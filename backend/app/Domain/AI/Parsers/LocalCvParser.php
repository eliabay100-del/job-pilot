<?php

declare(strict_types=1);

namespace App\Domain\AI\Parsers;

use App\Domain\AI\Contracts\CvParser;

/**
 * Conservative local parser used until a hosted AI provider is configured.
 * It only returns values found verbatim in the extracted CV text.
 */
final class LocalCvParser implements CvParser
{
    public function parse(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $email = $this->firstMatch('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', $text);
        $phone = $this->firstMatch('/(?:\+251|0)\s?\d{2,3}[\s-]?\d{3}[\s-]?\d{3,4}/', $text);

        $data = [
            'name' => $this->labelValue($text, ['name', 'full name']),
            'email' => $email,
            'phone' => $phone,
            'location' => $this->labelValue($text, ['location', 'address', 'city']),
            'summary' => $this->labelValue($text, ['summary', 'profile', 'objective']),
            'education' => [],
            'experience' => [],
            'skills' => [],
            'projects' => [],
            'certifications' => [],
            'languages' => [],
            'source' => 'local-text-parser',
        ];

        return [
            'structured_data' => $data,
            'confidence' => $email !== null || $data['name'] !== null ? 0.65 : 0.35,
        ];
    }

    private function firstMatch(string $pattern, string $text): ?string
    {
        preg_match($pattern, $text, $matches);
        return isset($matches[0]) ? trim($matches[0]) : null;
    }

    private function labelValue(string $text, array $labels): ?string
    {
        $pattern = '/(?:' . implode('|', array_map(fn (string $label): string => preg_quote($label, '/'), $labels)) . ')\s*[:\-]\s*([^|.;]{2,120})/iu';
        preg_match($pattern, $text, $matches);
        return isset($matches[1]) ? trim($matches[1]) : null;
    }
}