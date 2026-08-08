<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Reads parking sessions with Claude.
 *
 * Parking documents are the most varied of the three: a thermal-paper ticket
 * from a machine, a screenshot from a parking app, or a monthly provider
 * invoice listing thirty sessions in a table. No pattern covers that spread,
 * so there is no free path here - without an API key the feature is off.
 */
class ClaudeParkingExtractor implements ParkingExtractor
{
    /**
     * Written for the invoice case, because that is the one that saves real
     * typing. A single ticket simply comes back as a list of one.
     */
    private const PROMPT = <<<'TXT'
        Dies ist ein Beleg über Parkgebühren - entweder ein einzelner Parkschein
        bzw. eine Quittung, oder eine Rechnung eines Park-Anbieters (z.B. EasyPark),
        die mehrere Parkvorgänge auflistet.

        Gib alle abgerechneten Parkvorgänge als JSON-Liste unter "sessions" zurück,
        einen Eintrag je Parkvorgang, mit diesen Feldern:

        - date: Datum des Parkvorgangs im Format YYYY-MM-DD
        - location: Ort bzw. Zone des Parkvorgangs, kurz und lesbar,
          z.B. "Wien Zone 1" oder "Parkhaus Zentrum". Keine internen Nummern.
        - cost: Betrag dieses Parkvorgangs in Euro, brutto (inkl. MwSt.)
        - start_time: Beginn im Format HH:MM, falls angegeben
        - end_time: Ende im Format HH:MM, falls angegeben

        Wichtig:
        - Jeder Parkvorgang kommt genau einmal vor. Übernimm keine Summenzeile,
          keine Gebühren des Anbieters und keine Steuerzeilen als Parkvorgang.
        - Enthält das Dokument nur einen einzigen Parkvorgang, gib genau einen
          Eintrag zurück.
        - Setze ein Feld auf null, wenn du den Wert nicht zweifelsfrei lesen kannst.
          Rate nicht - ein null ist besser als eine falsche Angabe.
        TXT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'sessions' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['anyOf' => [['type' => 'string', 'format' => 'date'], ['type' => 'null']]],
                        'location' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                        'cost' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                        'start_time' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                        'end_time' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                    ],
                    'required' => ['date', 'location', 'cost', 'start_time', 'end_time'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['sessions'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly ClaudeDocumentReader $reader) {}

    public function isAvailable(): bool
    {
        return true;
    }

    /** @return list<ExtractedParkingSession> */
    public function extract(UploadedFile $file): array
    {
        $data = $this->reader->read($file, self::PROMPT, self::SCHEMA);

        if (! is_array($data['sessions'] ?? null)) {
            return [];
        }

        $sessions = [];

        foreach ($data['sessions'] as $session) {
            if (! is_array($session)) {
                continue;
            }

            $sessions[] = new ExtractedParkingSession(
                date: Value::date($session['date'] ?? null),
                // The column is a VARCHAR(255); the prompt asks for far less.
                location: Value::text($session['location'] ?? null, 255),
                cost: Value::positiveFloat($session['cost'] ?? null),
                startTime: Value::time($session['start_time'] ?? null),
                endTime: Value::time($session['end_time'] ?? null),
            );
        }

        return $sessions;
    }
}
