<?php

declare(strict_types=1);

namespace App\Domain\AI\Actions;

use App\Domain\AI\Contracts\CvParser;
use App\Models\CvVersion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class ParseCv
{
    public function __construct(private readonly CvParser $parser)
    {
    }

    public function handle(CvVersion $version): CvVersion
    {
        $version->update(['parse_status' => 'parsing', 'parse_error' => null]);

        try {
            $document = $version->document;
            if ($document === null) {
                throw new RuntimeException('This CV has no attached file.');
            }

            $text = $this->extractText($document->disk, $document->path, $document->mime_type);
            if (mb_strlen(trim($text)) < 20) {
                throw new RuntimeException('The CV did not contain enough readable text to parse.');
            }

            $result = $this->parser->parse($text);
            $version->update([
                'structured_data' => $result['structured_data'],
                'parse_confidence' => $result['confidence'],
                'parse_status' => 'needs_confirmation',
            ]);
        } catch (\Throwable $exception) {
            $version->update([
                'parse_status' => 'failed',
                'parse_error' => $exception->getMessage(),
            ]);
        }

        return $version->fresh('document');
    }

    private function extractText(string $disk, string $path, string $mimeType): string
    {
        $storage = Storage::disk($disk);
        $contents = $storage->get($path);

        if ($mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $zip = new ZipArchive();
            $temporaryPath = tempnam(sys_get_temp_dir(), 'jobpilot-cv-');
            file_put_contents($temporaryPath, $contents);
            if ($zip->open($temporaryPath) !== true) {
                @unlink($temporaryPath);
                throw new RuntimeException('The DOCX file could not be opened.');
            }
            $xml = $zip->getFromName('word/document.xml') ?: '';
            $zip->close();
            @unlink($temporaryPath);
            return html_entity_decode(strip_tags($xml));
        }

        if ($mimeType === 'application/pdf') {
            return preg_replace('/[^\x20-\x7E\r\n]/', ' ', $contents) ?? '';
        }

        return strip_tags($contents);
    }
}