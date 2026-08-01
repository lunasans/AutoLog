<?php

namespace Tests\Unit;

use App\Services\Receipts\ChainedReceiptExtractor;
use App\Services\Receipts\ExtractedReceipt;
use App\Services\Receipts\ReceiptExtractor;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class ChainedReceiptExtractorTest extends TestCase
{
    private function stub(?ExtractedReceipt $result, bool $available = true): ReceiptExtractor
    {
        return new class($result, $available) implements ReceiptExtractor
        {
            public bool $wasCalled = false;

            public function __construct(private ?ExtractedReceipt $result, private bool $available) {}

            public function extract(UploadedFile $file): ExtractedReceipt
            {
                $this->wasCalled = true;

                return $this->result ?? ExtractedReceipt::empty();
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }
        };
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->create('beleg.pdf', 10, 'application/pdf');
    }

    public function test_a_complete_result_stops_the_chain(): void
    {
        $cheap = $this->stub(new ExtractedReceipt(date: '2026-07-30', liters: 20.35, priceTotal: 42.51));
        $expensive = $this->stub(new ExtractedReceipt(date: '1999-01-01'));

        $result = (new ChainedReceiptExtractor([$cheap, $expensive]))->extract($this->file());

        $this->assertSame('2026-07-30', $result->date);
        $this->assertFalse($expensive->wasCalled, 'the paid extractor must not run when the free one sufficed');
    }

    public function test_a_partial_result_falls_through_to_the_next_extractor(): void
    {
        // Only the total was readable - not enough to fill the form.
        $cheap = $this->stub(new ExtractedReceipt(priceTotal: 42.51));
        $expensive = $this->stub(new ExtractedReceipt(date: '2026-07-30', liters: 20.35, priceTotal: 42.51));

        $result = (new ChainedReceiptExtractor([$cheap, $expensive]))->extract($this->file());

        $this->assertTrue($expensive->wasCalled);
        $this->assertTrue($result->isComplete());
    }

    public function test_a_partial_result_is_kept_when_nothing_better_follows(): void
    {
        $cheap = $this->stub(new ExtractedReceipt(priceTotal: 42.51));
        $expensive = $this->stub(ExtractedReceipt::empty());

        $result = (new ChainedReceiptExtractor([$cheap, $expensive]))->extract($this->file());

        $this->assertSame(42.51, $result->priceTotal);
    }

    public function test_unavailable_extractors_are_skipped(): void
    {
        $disabled = $this->stub(new ExtractedReceipt(date: '1999-01-01'), available: false);
        $enabled = $this->stub(new ExtractedReceipt(date: '2026-07-30', liters: 20.35, priceTotal: 42.51));

        $result = (new ChainedReceiptExtractor([$disabled, $enabled]))->extract($this->file());

        $this->assertFalse($disabled->wasCalled);
        $this->assertSame('2026-07-30', $result->date);
    }

    public function test_the_chain_is_available_if_any_extractor_is(): void
    {
        $this->assertTrue((new ChainedReceiptExtractor([
            $this->stub(null, available: false),
            $this->stub(null, available: true),
        ]))->isAvailable());

        $this->assertFalse((new ChainedReceiptExtractor([
            $this->stub(null, available: false),
        ]))->isAvailable());
    }
}
