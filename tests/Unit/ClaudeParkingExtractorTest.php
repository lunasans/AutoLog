<?php

namespace Tests\Unit;

use App\Services\Receipts\ClaudeDocumentReader;
use App\Services\Receipts\ClaudeParkingExtractor;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class ClaudeParkingExtractorTest extends TestCase
{
    /** Stands in for the API call, so the mapping can be exercised on its own. */
    private function extractor(?array $answer): ClaudeParkingExtractor
    {
        $reader = new class($answer) extends ClaudeDocumentReader
        {
            public function __construct(private readonly ?array $answer)
            {
                // Deliberately skips the parent constructor - no client needed.
            }

            public function read(UploadedFile $file, string $prompt, array $schema): ?array
            {
                return $this->answer;
            }
        };

        return new ClaudeParkingExtractor($reader);
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->create('rechnung.pdf', 10, 'application/pdf');
    }

    public function test_it_maps_every_session_the_invoice_lists(): void
    {
        $sessions = $this->extractor(['sessions' => [
            ['date' => '2026-07-03', 'location' => 'Wien Zone 1', 'cost' => 2.4, 'start_time' => '08:15', 'end_time' => '10:30'],
            ['date' => '2026-07-11', 'location' => 'Graz Zentrum', 'cost' => 1.8, 'start_time' => null, 'end_time' => null],
        ]])->extract($this->file());

        $this->assertCount(2, $sessions);
        $this->assertSame('Wien Zone 1', $sessions[0]->location);
        $this->assertSame('08:15', $sessions[0]->startTime);
        $this->assertNull($sessions[1]->startTime);
        $this->assertTrue($sessions[1]->isComplete());
    }

    public function test_seconds_on_a_time_are_dropped_rather_than_discarding_it(): void
    {
        $sessions = $this->extractor(['sessions' => [
            ['date' => '2026-07-03', 'location' => 'Wien Zone 1', 'cost' => 2.4, 'start_time' => '8:15:00', 'end_time' => '10:30'],
        ]])->extract($this->file());

        $this->assertSame('08:15', $sessions[0]->startTime);
    }

    public function test_values_it_could_not_read_do_not_become_wrong_ones(): void
    {
        $sessions = $this->extractor(['sessions' => [
            ['date' => 'Juli 2026', 'location' => '  ', 'cost' => 0, 'start_time' => '99:99', 'end_time' => null],
        ]])->extract($this->file());

        $this->assertNull($sessions[0]->date);
        $this->assertNull($sessions[0]->location);
        $this->assertNull($sessions[0]->cost);
        $this->assertNull($sessions[0]->startTime);
        $this->assertFalse($sessions[0]->isComplete());
    }

    public function test_an_unreadable_document_yields_no_sessions(): void
    {
        $this->assertSame([], $this->extractor(null)->extract($this->file()));
    }
}
