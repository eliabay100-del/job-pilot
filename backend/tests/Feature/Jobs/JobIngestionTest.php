<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Domain\JobSources\Actions\IngestJobSource;
use App\Domain\JobSources\Adapters\CsvJobSourceAdapter;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobSource;
use App\Models\JobSourceRecord;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class JobIngestionTest extends TestCase
{
    use RefreshDatabase;

    private string $csvPath;

    protected function setUp(): void
    {
        parent::setUp();

        Skill::create(['name' => 'PHP', 'slug' => 'php']);
        Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);

        $this->csvPath = tempnam(sys_get_temp_dir(), 'jp-ingest-').'.csv';

        file_put_contents($this->csvPath, implode("\n", [
            'source_url,external_id,title,company,city,work_mode,employment_type,seniority,salary_min_monthly,salary_max_monthly,application_deadline,description,skills',
            'https://jobs.example/1,EXT-1,Senior Laravel Developer,Gebeya Inc.,Addis Ababa,hybrid,full_time,senior,60000,90000,2026-12-31,Build Laravel APIs for clients.,PHP|Laravel',
            'https://jobs.example/2,EXT-2,Branch Officer,Unknown Bank PLC,Bahir Dar,onsite,full_time,junior,20000,30000,2026-12-31,Serve walk-in customers.,',
            'https://jobs.example/3,EXT-3,Bad Enum Role,Gebeya Inc.,Addis Ababa,wfh,,,,,,,,,',
            'https://jobs.example/4,EXT-4,Missing Title,,Addis Ababa,onsite,,,,,,,,,',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->csvPath);
        parent::tearDown();
    }

    private function ingest(): \App\Domain\JobSources\IngestionSummary
    {
        $source = JobSource::firstOrCreate(['name' => 'csv']);

        return app(IngestJobSource::class)->handle($source, app(CsvJobSourceAdapter::class), ['path' => $this->csvPath]);
    }

    public function test_valid_records_publish_for_known_companies_and_hold_unknown_ones(): void
    {
        Company::create(['name' => 'Gebeya Inc.', 'slug' => 'gebeya-inc', 'verification_status' => 'verified']);

        $summary = $this->ingest();

        $this->assertSame(4, $summary->fetched);
        $this->assertSame(2, $summary->created);
        $this->assertSame(2, $summary->rejected);

        $known = Job::where('slug', 'senior-laravel-developer-gebeya-inc')->firstOrFail();
        $this->assertSame('published', $known->status);
        $this->assertNotNull($known->published_at);
        $this->assertSame('low', $known->risk_level);
        $this->assertSame(
            ['Laravel', 'PHP'],
            $known->skills()->orderBy('name')->pluck('name')->all(),
        );

        // A company the platform has never seen is held for moderation instead
        // of being published straight from an untrusted feed.
        $unknown = Job::where('slug', 'branch-officer-unknown-bank-plc')->firstOrFail();
        $this->assertSame('pending_review', $unknown->status);
        $this->assertNull($unknown->published_at);
        $this->assertSame('review', $unknown->risk_level);
        $this->assertSame('unverified', $unknown->company->verification_status);

        $this->assertSame(
            ['parsed', 'parsed', 'rejected', 'rejected'],
            JobSourceRecord::orderBy('id')->pluck('status')->all(),
        );
        $this->assertStringContainsString('work_mode', JobSourceRecord::orderBy('id')->skip(2)->first()->error);
    }

    public function test_rerunning_the_same_feed_deduplicates_by_fingerprint(): void
    {
        Company::create(['name' => 'Gebeya Inc.', 'slug' => 'gebeya-inc', 'verification_status' => 'verified']);

        $this->ingest();
        $summary = $this->ingest();

        $this->assertSame(2, $summary->duplicated);
        $this->assertSame(0, $summary->created);
        $this->assertSame(2, Job::count());
        $this->assertSame(
            ['duplicate', 'duplicate', 'rejected', 'rejected'],
            JobSourceRecord::orderBy('id')->pluck('status')->all(),
        );
        $this->assertNotNull(Job::where('slug', 'senior-laravel-developer-gebeya-inc')->first()->last_checked_at);
    }

    public function test_same_vacancy_from_a_second_url_collapses_into_one_job(): void
    {
        Company::create(['name' => 'Gebeya Inc.', 'slug' => 'gebeya-inc', 'verification_status' => 'verified']);
        $this->ingest();

        file_put_contents($this->csvPath, implode("\n", [
            'source_url,external_id,title,company,city,work_mode,employment_type,seniority,salary_min_monthly,salary_max_monthly,application_deadline,description,skills',
            'https://mirror.example/repost,EXT-9,Senior Laravel Developer,Gebeya Inc.,Addis Ababa,hybrid,full_time,senior,60000,90000,2026-12-31,Build Laravel APIs for clients.,PHP|Laravel',
        ]));

        $summary = $this->ingest();

        $this->assertSame(1, $summary->duplicated);
        $this->assertSame(2, Job::count());
        $this->assertSame(2, Job::where('slug', 'senior-laravel-developer-gebeya-inc')->first()->sourceRecords()->count());
    }

    public function test_command_reports_the_summary_table(): void
    {
        Company::create(['name' => 'Gebeya Inc.', 'slug' => 'gebeya-inc', 'verification_status' => 'verified']);

        $this->artisan('jobs:ingest', ['source' => 'csv', '--path' => $this->csvPath])
            ->expectsTable(
                ['fetched', 'created', 'duplicated', 'rejected'],
                [[4, 2, 0, 2]],
            )
            ->assertSuccessful();
    }

    public function test_missing_csv_file_fails_loudly(): void
    {
        $source = JobSource::firstOrCreate(['name' => 'csv']);

        $this->expectException(RuntimeException::class);

        app(IngestJobSource::class)->handle($source, app(CsvJobSourceAdapter::class), ['path' => '/nonexistent/feed.csv']);
    }
}
