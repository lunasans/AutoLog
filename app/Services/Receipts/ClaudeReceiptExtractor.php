<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Reads a fuel receipt with Claude's vision model.
 *
 * Only reached for scans and layouts PdfTextReceiptExtractor cannot parse -
 * every call costs money, so the free path runs first. Results are suggestions
 * the user confirms in the form; nothing is saved on their behalf.
 */
class ClaudeReceiptExtractor implements ReceiptExtractor
{
    private const PROMPT = <<<'TXT'
        Dies ist ein Tankbeleg. Lies die folgenden Werte ab und gib sie als JSON zurück:

        - date: Datum des Tankvorgangs im Format YYYY-MM-DD
        - liters: getankte Menge in Litern
        - price_total: Gesamtbetrag in Euro (nicht der Preis pro Liter)
        - odometer_reading: Kilometerstand, falls auf dem Beleg vermerkt

        Setze ein Feld auf null, wenn du den Wert nicht zweifelsfrei lesen kannst.
        Rate nicht - ein null ist besser als eine falsche Zahl.
        TXT;

    /** Every field nullable: partial reads are the normal case, not an error. */
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'date' => ['anyOf' => [['type' => 'string', 'format' => 'date'], ['type' => 'null']]],
            'liters' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
            'price_total' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
            'odometer_reading' => ['anyOf' => [['type' => 'integer'], ['type' => 'null']]],
        ],
        'required' => ['date', 'liters', 'price_total', 'odometer_reading'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly ClaudeDocumentReader $reader) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function extract(UploadedFile $file): ExtractedReceipt
    {
        $data = $this->reader->read($file, self::PROMPT, self::SCHEMA);

        if ($data === null) {
            return ExtractedReceipt::empty();
        }

        return new ExtractedReceipt(
            date: Value::date($data['date'] ?? null),
            liters: Value::positiveFloat($data['liters'] ?? null),
            priceTotal: Value::positiveFloat($data['price_total'] ?? null),
            odometerReading: Value::positiveInt($data['odometer_reading'] ?? null),
        );
    }
}
