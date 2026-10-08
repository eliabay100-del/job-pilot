<?php

declare(strict_types=1);

namespace App\Domain\CV\Actions;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Persists an uploaded file on the private disk with a generated safe filename
 * (SPEC section 38). Extension/MIME/size are enforced by the calling Form
 * Request; this action never trusts the client-supplied name.
 */
class StoreUploadedFile
{
    public const DISK = 'local';

    /**
     * Must cover every MIME type allowed by UploadCvRequest/StoreDocumentRequest;
     * an unmapped type is stored with an inert ".bin" extension rather than the
     * client-supplied one.
     */
    private const EXTENSION_FOR_MIME = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    public function handle(UploadedFile $file, User $user): Document
    {
        // Key off the content-guessed MIME type — the same value the `mimetypes`
        // rule validated — never the client-supplied filename or browser header
        // (SPEC section 38). Otherwise "evil.pdf" sent as application/pdf but
        // named for another type could be stored with an attacker-chosen
        // extension, and a legitimate file could disagree with its own validation.
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $extension = self::EXTENSION_FOR_MIME[$mimeType] ?? 'bin';
        $uuid = (string) Str::uuid();
        $path = $file->storeAs(
            "documents/{$user->id}",
            "{$uuid}.{$extension}",
            ['disk' => self::DISK],
        );

        if ($path === false) {
            throw new RuntimeException('Failed to store uploaded file.');
        }

        return Document::create([
            'uuid' => $uuid,
            'uploaded_by' => $user->id,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $mimeType,
            'size_bytes' => $file->getSize() ?: 0,
            'sha256' => hash_file('sha256', Storage::disk(self::DISK)->path($path)) ?: '',
            // No malware scanner is configured yet (Phase 12 hardening); record
            // the skip explicitly instead of leaving files stuck in "pending".
            'scan_status' => 'skipped',
            'scanned_at' => now(),
        ]);
    }

    public function delete(Document $document): void
    {
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();
    }
}
