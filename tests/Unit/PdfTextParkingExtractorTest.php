<?php

namespace Tests\Unit;

use App\Services\Receipts\PdfTextParkingExtractor;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

/**
 * Exercised against the text an EasyPark invoice yields, so the patterns can be
 * checked without keeping a real invoice - they carry name, address, plate and
 * payment details - in the repository. Only the layout below is taken from a
 * real invoice; every plate, place, amount and reference number is invented.
 */
class PdfTextParkingExtractorTest extends TestCase
{
    private function extractor(): PdfTextParkingExtractor
    {
        return new PdfTextParkingExtractor(new Parser);
    }

    /** One parking session plus the provider's charge for it. */
    private function invoice(): string
    {
        return <<<'TXT'
            EasyPark | Rechnung
            Datum: 06.08.2026

            	Belegverweis:	1000000000
            Zeitraum:	01.08.2026 - 31.08.2026

            Transaktionen

            Parkvorgänge  Beleg der im Namen des Betreibers (siehe unten) ausgestellt wird
             Details zum Parkvorgang	Parkzone	Betrag (exkl.
            MwSt.)

             1.Startzeit: 05.08.2026 11:30	Stadt Musterstadt
            HRN: DE100000000
            Parkzone: 500001
            Name der Parkzone: Tarif II
            0,98 EUR 0 % 0,00 EUR 0,98
             Endzeit: 05.08.2026 12:29
             Nummernschild: MAB1234
             Transaktions-ID: 1-DE-0000-000000

            Summe Parkvorgänge:	0,98 EUR	0,00 EUR 0,98
            EasyPark-Dienstleistung  In Rechnung gestellt im Namen und auf Rechnung von EasyPark
             Grund der Rechnungsstellung Spezifikation	Betrag (exkl.
            MwSt.)

             2.Transaktions-ID: 0-DE-EP-
            000000000
            Tarif II 05.08.2026 - 05.08.2026	0,46 EUR 19% 0,09 EUR 0,55

            Summe EasyPark-Dienstleistung:	0,46 EUR	0,09 EUR 0,55
            Total, payment via
            	1,53
            TXT;
    }

    public function test_it_reads_the_session_off_the_invoice(): void
    {
        $sessions = $this->extractor()->fromText($this->invoice());

        $this->assertCount(1, $sessions);

        $session = $sessions[0];
        $this->assertSame('2026-08-05', $session->date);
        $this->assertSame('Musterstadt, Tarif II', $session->location);
        $this->assertSame('11:30', $session->startTime);
        $this->assertSame('12:29', $session->endTime);
        $this->assertSame('MAB1234', $session->licensePlate);
    }

    /**
     * The invoice bills 0,98 for the parking and 0,55 for handling it; 1,53 is
     * what left the account, and that is what the entry has to say.
     */
    public function test_the_provider_charge_lands_on_the_session_it_belongs_to(): void
    {
        $session = $this->extractor()->fromText($this->invoice())[0];

        $this->assertEqualsWithDelta(0.98, $session->cost, 0.001);
        $this->assertEqualsWithDelta(0.55, $session->fee, 0.001);
        $this->assertEqualsWithDelta(1.53, $session->total(), 0.001);
    }

    public function test_a_charge_matching_no_session_becomes_an_entry_of_its_own(): void
    {
        // Same invoice, but the charge covers a tariff that was never parked.
        $text = str_replace(
            'Tarif II 05.08.2026 - 05.08.2026	0,46',
            'Tarif IX 05.08.2026 - 05.08.2026	0,46',
            $this->invoice(),
        );

        $sessions = $this->extractor()->fromText($text);

        $this->assertCount(2, $sessions);
        $this->assertNull($sessions[0]->fee);
        $this->assertSame('EasyPark Gebühr – Tarif IX', $sessions[1]->location);
        $this->assertEqualsWithDelta(0.55, $sessions[1]->total(), 0.001);
    }

    public function test_every_session_of_a_longer_invoice_is_read(): void
    {
        $second = <<<'TXT'
             2.Startzeit: 12.08.2026 09:05	Stadt Beispielstadt
            HRN: DE100000000
            Parkzone: 500002
            Name der Parkzone: Tarif I
            2,40 EUR 0 % 0,00 EUR 2,40
             Endzeit: 12.08.2026 11:05
             Nummernschild: MAB1234
            TXT;

        $text = str_replace('Summe Parkvorgänge:', $second."\nSumme Parkvorgänge:", $this->invoice());

        $sessions = $this->extractor()->fromText($text);

        $this->assertCount(2, $sessions);
        $this->assertSame('Beispielstadt, Tarif I', $sessions[1]->location);
        $this->assertEqualsWithDelta(2.40, $sessions[1]->total(), 0.001);
        // The charge names Tarif II, so it stays with the first session.
        $this->assertNull($sessions[1]->fee);
    }

    public function test_another_providers_document_yields_nothing_rather_than_a_guess(): void
    {
        $this->assertSame([], $this->extractor()->fromText('Parkschein, 2,00 EUR, 12:00-14:00'));
    }
}
