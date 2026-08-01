<?php

namespace Tests\Unit;

use App\Services\Receipts\PdfTextReceiptExtractor;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

class PdfTextReceiptExtractorTest extends TestCase
{
    private PdfTextReceiptExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new PdfTextReceiptExtractor(new Parser);
    }

    /**
     * The layout of a provider-issued fuel receipt, with the station's and
     * customer's details replaced - only the parsed columns are verbatim.
     */
    private function receipt(): string
    {
        return <<<'TXT'
            Musterr Tankstelle
            Musterstr. 1
            12345 Musterstadt
            *          20,35 Liter  SÄULENNUMMER  6  *
            *Super                   A      42,51 EUR*
             2,089 EUR/Liter

            Betrag:                          42.51 EUR

             TOTAL      42,51 EUR

             MWST 19,00% A                   6,79 EUR
             NETTO     35,72 EUR BRUTTO     42,51 EUR

            Technische Sicherheitseinrichtung
            928;2026-07-30T14:24:13.000Z;2026-07-30T14
            #19119 30.07.26 16:24              K.0004
            TXT;
    }

    public function test_it_reads_date_liters_and_total(): void
    {
        $result = $this->extractor->fromText($this->receipt());

        $this->assertSame('2026-07-30', $result->date);
        $this->assertSame(20.35, $result->liters);
        $this->assertSame(42.51, $result->priceTotal);
        $this->assertTrue($result->isComplete());
    }

    public function test_the_unit_price_is_not_mistaken_for_the_amount_filled(): void
    {
        // "2,089 EUR/Liter" sits right next to the litre column.
        $this->assertSame(20.35, $this->extractor->fromText($this->receipt())->liters);
    }

    public function test_it_prefers_the_local_footer_date_over_the_fiscal_utc_one(): void
    {
        // A fill just after midnight local time is still the previous day in
        // UTC - the footer is what the customer would call the date.
        $text = <<<'TXT'
             TOTAL      42,51 EUR
            928;2026-07-30T23:24:13.000Z;
            #19119 31.07.26 01:24              K.0004
            TXT;

        $this->assertSame('2026-07-31', $this->extractor->fromText($text)->date);
    }

    public function test_it_falls_back_to_the_fiscal_date_without_a_footer(): void
    {
        $text = " TOTAL      42,51 EUR\n928;2026-07-30T14:24:13.000Z;";

        $this->assertSame('2026-07-30', $this->extractor->fromText($text)->date);
    }

    public function test_it_falls_back_to_the_gross_amount_without_a_total_line(): void
    {
        $text = ' NETTO     35,72 EUR BRUTTO     42,51 EUR';

        $this->assertSame(42.51, $this->extractor->fromText($text)->priceTotal);
    }

    public function test_it_reads_amounts_in_the_thousands(): void
    {
        $text = " TOTAL      1.234,56 EUR\n*         123,45 Liter  SÄULENNUMMER  6  *";

        $result = $this->extractor->fromText($text);

        $this->assertSame(1234.56, $result->priceTotal);
        $this->assertSame(123.45, $result->liters);
    }

    public function test_an_unrecognised_layout_yields_nothing_rather_than_a_guess(): void
    {
        $result = $this->extractor->fromText('Vielen Dank für Ihren Einkauf. Bitte kommen Sie wieder.');

        $this->assertTrue($result->isEmpty());
    }
}
