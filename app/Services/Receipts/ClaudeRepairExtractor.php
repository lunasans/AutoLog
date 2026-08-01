<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Reads a scanned workshop invoice with Claude's vision model.
 *
 * There is no free path here: invoices are scanned by hand and every workshop
 * lays them out differently, which is exactly the case a model handles well and
 * a pattern does not. Visits are rare enough that the cost stays negligible.
 */
class ClaudeRepairExtractor implements RepairExtractor
{
    /**
     * The summary is the real gain - condensing a line-item list into
     * something readable in the service log is tedious to do by hand.
     */
    private const PROMPT = <<<'TXT'
        Dies ist eine Werkstattrechnung für ein Fahrzeug. Lies die folgenden Werte ab
        und gib sie als JSON zurück:

        - date: Rechnungsdatum im Format YYYY-MM-DD
        - description: knappe Zusammenfassung der ausgeführten Arbeiten, höchstens 120
          Zeichen, auf Deutsch. Fasse die Positionen zu den durchgeführten Arbeiten
          zusammen, z.B. "Ölwechsel, Bremsbeläge vorne, HU". Keine Ersatzteilnummern,
          keine Preise, keine Arbeitszeiten.
        - cost: Rechnungsendbetrag in Euro, brutto (inkl. MwSt.)
        - odometer_reading: Kilometerstand des Fahrzeugs, falls auf der Rechnung vermerkt

        Setze ein Feld auf null, wenn du den Wert nicht zweifelsfrei lesen kannst.
        Rate nicht - ein null ist besser als eine falsche Zahl.
        TXT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'date' => ['anyOf' => [['type' => 'string', 'format' => 'date'], ['type' => 'null']]],
            'description' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
            'cost' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
            'odometer_reading' => ['anyOf' => [['type' => 'integer'], ['type' => 'null']]],
        ],
        'required' => ['date', 'description', 'cost', 'odometer_reading'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly ClaudeDocumentReader $reader) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function extract(UploadedFile $file): ExtractedRepair
    {
        $data = $this->reader->read($file, self::PROMPT, self::SCHEMA);

        if ($data === null) {
            return ExtractedRepair::empty();
        }

        return new ExtractedRepair(
            date: Value::date($data['date'] ?? null),
            // The column is a VARCHAR(255); the prompt asks for far less.
            description: Value::text($data['description'] ?? null, 255),
            cost: Value::positiveFloat($data['cost'] ?? null),
            odometerReading: Value::positiveInt($data['odometer_reading'] ?? null),
        );
    }
}
