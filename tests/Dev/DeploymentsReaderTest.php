<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use Gamecon\Dev\DeploymentsReader;
use PHPUnit\Framework\TestCase;

class DeploymentsReaderTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures';

    /**
     * @test
     */
    public function missingRootDirectoryIsReportedAsUnavailable(): void
    {
        $reader = new DeploymentsReader(self::FIXTURES . '/no-such-dir');
        self::assertNotNull($reader->unavailableReason());
        self::assertSame([], $reader->readPreviews());
        self::assertSame([], $reader->readArchives());
        self::assertNull($reader->updatedAt());
    }

    /**
     * @test
     */
    public function typicalLayoutYieldsPreviewsAndArchives(): void
    {
        $reader = new DeploymentsReader(self::FIXTURES . '/typical');
        self::assertNull($reader->unavailableReason());

        $previews = $reader->readPreviews();
        self::assertCount(2, $previews);
        // glob() + sort() yields alphabetical order: feature-x, phase1-preview
        self::assertSame('feature-x', $previews[0]->slug);
        // branch is the original git ref (slug 'feature-x' ← branch 'feature_x'),
        // stored separately because the slug is a lossy slugification.
        self::assertSame('feature_x', $previews[0]->branch);
        self::assertSame('phase1-preview', $previews[1]->slug);
        // No "branch" key in this fixture → null (records predating branch tracking).
        self::assertNull($previews[1]->branch);
        self::assertSame('abc1234', $previews[1]->sha7);
        self::assertSame(
            'ghcr.io/gamecon-cz/gamecon:preview-phase1-preview-abc1234',
            $previews[1]->image,
        );
        self::assertNotNull($previews[1]->deployedAt);
        self::assertSame('2026-05-21 21:24:05', $previews[1]->deployedAt->format('Y-m-d H:i:s'));

        $archives = $reader->readArchives();
        self::assertCount(2, $archives);
        self::assertSame(2024, $archives[0]->year);
        self::assertSame('https://2024.gamecon.cz/', $archives[0]->url);
    }

    /**
     * @test
     */
    public function updatedAtReturnsTheNewestDeployedAtAcrossAllRecords(): void
    {
        $reader = new DeploymentsReader(self::FIXTURES . '/typical');
        $updatedAt = $reader->updatedAt();
        self::assertNotNull($updatedAt);
        // feature-x was deployed on 2026-05-22T07:00 — the latest of the four
        self::assertSame('2026-05-22 07:00:00', $updatedAt->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function emptyDirectoriesAreAvailableButEmpty(): void
    {
        $reader = new DeploymentsReader(self::FIXTURES . '/empty');
        self::assertNull($reader->unavailableReason());
        self::assertSame([], $reader->readPreviews());
        self::assertSame([], $reader->readArchives());
        self::assertNull($reader->updatedAt());
    }

    /**
     * @test
     */
    public function badRecordsAreSkippedSilentlyGoodOnesKeepRendering(): void
    {
        $reader = new DeploymentsReader(self::FIXTURES . '/mixed');
        $previews = $reader->readPreviews();

        // The 'mixed' fixture has 4 preview files; only `only-required.json`
        // should make it through:
        //  - future-schema.json → schema_version > 1, skipped (forward-compat)
        //  - missing-url.json   → no url, skipped (invalid record)
        //  - broken.json        → malformed JSON, skipped
        //  - only-required.json → valid minimal record
        self::assertCount(1, $previews);
        self::assertSame('only-required', $previews[0]->slug);
        self::assertNull($previews[0]->image);
        self::assertNull($previews[0]->sha7);
        self::assertNull($previews[0]->branch);
        self::assertNull($previews[0]->deployedAt);

        $archives = $reader->readArchives();
        self::assertCount(1, $archives);
        self::assertSame(2014, $archives[0]->year);
    }

    /**
     * @test
     */
    public function filenameWithGarbageIsSkipped(): void
    {
        $dir = sys_get_temp_dir() . '/gamecon-deployments-' . uniqid('', true);
        mkdir($dir . '/previews', 0o755, true);
        try {
            // Plausible body, but the filename isn't a valid slug
            // (path traversal attempt). Must be skipped.
            file_put_contents(
                $dir . '/previews/..weird.json',
                '{"slug":"..weird","url":"https://x/"}',
            );
            file_put_contents(
                $dir . '/previews/ok-slug.json',
                '{"slug":"ok-slug","url":"https://ok/"}',
            );
            $reader = new DeploymentsReader($dir);
            $previews = $reader->readPreviews();
            self::assertCount(1, $previews);
            self::assertSame('ok-slug', $previews[0]->slug);
        } finally {
            @unlink($dir . '/previews/..weird.json');
            @unlink($dir . '/previews/ok-slug.json');
            @rmdir($dir . '/previews');
            @rmdir($dir);
        }
    }

    /**
     * @test
     */
    public function filenameSuppliesYearOrSlugWhenBodyForgot(): void
    {
        $dir = sys_get_temp_dir() . '/gamecon-deployments-' . uniqid('', true);
        mkdir($dir . '/previews', 0o755, true);
        mkdir($dir . '/archives', 0o755, true);
        try {
            file_put_contents(
                $dir . '/previews/from-filename.json',
                '{"url":"https://from-filename.preview.gamecon.cz/"}',
            );
            file_put_contents(
                $dir . '/archives/2019.json',
                '{"url":"https://2019.gamecon.cz/"}',
            );
            $reader = new DeploymentsReader($dir);
            self::assertSame('from-filename', $reader->readPreviews()[0]->slug);
            self::assertSame(2019, $reader->readArchives()[0]->year);
        } finally {
            @unlink($dir . '/previews/from-filename.json');
            @unlink($dir . '/archives/2019.json');
            @rmdir($dir . '/previews');
            @rmdir($dir . '/archives');
            @rmdir($dir);
        }
    }
}
