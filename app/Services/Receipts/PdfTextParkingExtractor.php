<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

/**
 * Reads an EasyPark invoice straight out of the PDF's text layer.
 *
 * These are generated documents, so every figure is already text - no model,
 * no cost, and no risk of a misread digit on an invoice that may list dozens
 * of sessions. Anchored on EasyPark's own labels; a different provider yields
 * nothing rather than a wrong reading, and the caller falls through to the
 * vision extractor.
 */
class PdfTextParkingExtractor implements ParkingExtractor
{
    /** Everything between the sessions table and its total. */
    private const SESSIONS_BLOCK = '/^\s*Parkvorgänge\b(.*?)^\s*Summe Parkvorgänge/msu';

    /** The provider's own charges, billed in a table of their own. */
    private const FEES_BLOCK = '/^\s*EasyPark-Dienstleistung\b(.*?)^\s*Summe EasyPark-Dienstleistung/msu';

    /** Each session opens with a numbered start time, e.g. " 1.Startzeit: ". */
    private const SESSION_SPLIT = '/^\s*\d+\.(?=Startzeit:)/mu';

    private const START = '/Startzeit:\s*(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}:\d{2})/u';

    private const END = '/Endzeit:\s*\d{2}\.\d{2}\.\d{4}\s+(\d{2}:\d{2})/u';

    /** Trails the start time on the same line, e.g. "Stadt Bergisch Gladbach". */
    private const CITY = '/Startzeit:[^\n\t]*\t\s*(?:Stadt\s+)?([^\n\t]+)/u';

    private const ZONE_NAME = '/Name der Parkzone:\s*([^\n\t]+)/u';

    /** Stays on its own line - a plate never wraps. */
    private const PLATE = '/Nummernschild:[ \t]*([A-Z0-9ÄÖÜ][A-Z0-9ÄÖÜ \-]*)/u';

    /**
     * An amount row: net, VAT rate, VAT, gross. Only the gross figure is of
     * interest - that is what left the account.
     */
    private const AMOUNTS = '/([\d.]*\d,\d{2})\s*EUR\s+[\d,]+\s*%\s+([\d.]*\d,\d{2})\s*EUR\s+([\d.]*\d,\d{2})/u';

    /** A fee row names the tariff it belongs to and the days it covers. */
    private const FEE = '/^\s*([^\n\t]+?)\s+(\d{2})\.(\d{2})\.(\d{4})\s*-\s*(\d{2})\.(\d{2})\.(\d{4})\s*\t?\s*(?=[\d.]*\d,\d{2}\s*EUR)/mu';

    public function __construct(private readonly Parser $parser) {}

    public function isAvailable(): bool
    {
        return true;
    }

    /** @return list<ExtractedParkingSession> */
    public function extract(UploadedFile $file): array
    {
        if ($file->getMimeType() !== 'application/pdf') {
            return [];
        }

        try {
            $text = $this->parser->parseFile($file->getRealPath())->getText();
        } catch (\Throwable $e) {
            // Encrypted, malformed or image-only PDFs land here. Not an error
            // worth surfacing - the next extractor gets a turn.
            Log::debug('PDF text layer unreadable', ['exception' => $e]);

            return [];
        }

        return $this->fromText($text);
    }

    /**
     * Split out from extract() so the patterns can be exercised against invoice
     * text directly, without checking a real invoice into the repository.
     *
     * @return list<ExtractedParkingSession>
     */
    public function fromText(string $text): array
    {
        $sessions = $this->sessions($text);

        if ($sessions === []) {
            return [];
        }

        // The provider bills its handling charge per session in a table of its
        // own. It is money paid for that parking, so it belongs on the entry -
        // anything that matches no session is kept as an entry of its own
        // rather than quietly dropped.
        [$sessions, $unmatched] = $this->applyFees($sessions, $this->fees($text));

        return array_merge($sessions, $unmatched);
    }

    /** @return list<ExtractedParkingSession> */
    private function sessions(string $text): array
    {
        if (! preg_match(self::SESSIONS_BLOCK, $text, $block)) {
            return [];
        }

        $sessions = [];

        foreach (preg_split(self::SESSION_SPLIT, $block[1]) as $chunk) {
            if (! preg_match(self::START, $chunk, $start)) {
                continue;
            }

            $sessions[] = new ExtractedParkingSession(
                date: "{$start[3]}-{$start[2]}-{$start[1]}",
                location: $this->location($chunk),
                cost: $this->gross($chunk),
                startTime: $start[4],
                endTime: preg_match(self::END, $chunk, $end) ? $end[1] : null,
                licensePlate: preg_match(self::PLATE, $chunk, $plate) ? trim($plate[1]) : null,
            );
        }

        return $sessions;
    }

    /**
     * "Bergisch Gladbach, Tarif II" - the city alone repeats across a month of
     * sessions, and the tariff is what tells two of them apart.
     */
    private function location(string $chunk): ?string
    {
        $parts = [];

        foreach ([self::CITY, self::ZONE_NAME] as $pattern) {
            if (preg_match($pattern, $chunk, $m)) {
                $parts[] = trim($m[1]);
            }
        }

        return Value::text(implode(', ', array_filter($parts)), 255);
    }

    private function gross(string $chunk): ?float
    {
        return preg_match(self::AMOUNTS, $chunk, $m) ? $this->amount($m[3]) : null;
    }

    /**
     * The provider's charges, each with the tariff and the days it covers.
     *
     * @return list<array{tariff: string, from: string, to: string, amount: float}>
     */
    private function fees(string $text): array
    {
        if (! preg_match(self::FEES_BLOCK, $text, $block)) {
            return [];
        }

        if (! preg_match_all(self::FEE, $block[1], $rows, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $fees = [];

        foreach ($rows as $row) {
            // The amounts sit right behind the date range this matched.
            $rest = substr($block[1], $row[0][1] + strlen($row[0][0]));

            if (! preg_match(self::AMOUNTS, $rest, $amounts)) {
                continue;
            }

            $amount = $this->amount($amounts[3]);

            if ($amount === null) {
                continue;
            }

            $fees[] = [
                'tariff' => trim($row[1][0]),
                'from' => "{$row[4][0]}-{$row[3][0]}-{$row[2][0]}",
                'to' => "{$row[7][0]}-{$row[6][0]}-{$row[5][0]}",
                'amount' => $amount,
            ];
        }

        return $fees;
    }

    /**
     * Puts each charge on the session it belongs to, matched by tariff and the
     * days it covers. A charge that fits none becomes an entry of its own.
     *
     * @param  list<ExtractedParkingSession>  $sessions
     * @param  list<array{tariff: string, from: string, to: string, amount: float}>  $fees
     * @return array{0: list<ExtractedParkingSession>, 1: list<ExtractedParkingSession>}
     */
    private function applyFees(array $sessions, array $fees): array
    {
        $orphans = [];

        foreach ($fees as $fee) {
            $match = null;

            foreach ($sessions as $index => $session) {
                if ($session->date === null || $session->date < $fee['from'] || $session->date > $fee['to']) {
                    continue;
                }

                // The tariff name is part of the location we built above.
                if ($session->location !== null && ! str_contains($session->location, $fee['tariff'])) {
                    continue;
                }

                $match = $index;

                break;
            }

            if ($match === null) {
                $orphans[] = new ExtractedParkingSession(
                    date: $fee['from'],
                    location: 'EasyPark Gebühr'.($fee['tariff'] === '' ? '' : ' – '.$fee['tariff']),
                    cost: $fee['amount'],
                );

                continue;
            }

            $sessions[$match] = $sessions[$match]->withFee($fee['amount']);
        }

        return [array_values($sessions), $orphans];
    }

    /** German notation: dots group thousands, the comma is the decimal mark. */
    private function amount(string $raw): ?float
    {
        $value = (float) str_replace(',', '.', str_replace('.', '', $raw));

        return $value > 0 ? $value : null;
    }
}
