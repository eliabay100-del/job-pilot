<?php

declare(strict_types=1);

namespace App\Domain\AI\Actions;

use App\Models\CvVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ConfirmCvExtraction
{
    /** @param list<string> $fields */
    public function handle(CvVersion $version, array $fields): CvVersion
    {
        if ($version->parse_status !== 'needs_confirmation' || ! is_array($version->structured_data)) {
            throw new RuntimeException('This CV has no extraction waiting for confirmation.');
        }

        $data = $version->structured_data;
        $profile = $version->candidateProfile;
        $updates = [];

        if (in_array('name', $fields, true) && filled($data['name'] ?? null)) {
            $updates['display_name'] = $data['name'];
        }
        if (in_array('location', $fields, true) && filled($data['location'] ?? null)) {
            $updates['city'] = $data['location'];
        }
        if (in_array('summary', $fields, true) && filled($data['summary'] ?? null)) {
            $updates['summary'] = $data['summary'];
        }

        DB::transaction(function () use ($version, $profile, $updates): void {
            if ($updates !== []) {
                $profile->update($updates);
            }
            $version->update(['parse_status' => 'confirmed']);
        });

        return $version->fresh('document');
    }
}