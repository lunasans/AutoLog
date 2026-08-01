<?php

namespace App\Services\Receipts;

use Anthropic\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Reads a fuel receipt with Claude's vision model and returns structured values.
 *
 * The model never sees the stored file - it gets the freshly uploaded one and
 * nothing is persisted here. Results are suggestions only; the user confirms
 * them in the form before anything is saved.
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

    public function __construct(
        private readonly Client $client,
        private readonly string $model,
    ) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function extract(UploadedFile $file): ExtractedReceipt
    {
        try {
            $response = $this->client->messages->create(
                maxTokens: 1024,
                model: $this->model,
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA]],
                messages: [[
                    'role' => 'user',
                    'content' => [$this->fileBlock($file), ['type' => 'text', 'text' => self::PROMPT]],
                ]],
            );
        } catch (\Throwable $e) {
            // A failed scan must never block the upload - the user types the
            // values instead, exactly as before this feature existed.
            Log::warning('Receipt extraction failed', ['exception' => $e]);

            return ExtractedReceipt::empty();
        }

        return $this->parse($response);
    }

    /**
     * PDFs need a document block, images an image block - the block type must
     * match the file's MIME type or the API rejects the request.
     */
    private function fileBlock(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $data = base64_encode(file_get_contents($file->getRealPath()));

        if ($mime === 'application/pdf') {
            return [
                'type' => 'document',
                'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data],
            ];
        }

        return [
            'type' => 'image',
            'source' => ['type' => 'base64', 'mediaType' => $mime, 'data' => $data],
        ];
    }

    private function parse(object $response): ExtractedReceipt
    {
        if ($response->stopReason === 'refusal') {
            Log::warning('Receipt extraction refused', ['details' => $response->stopDetails]);

            return ExtractedReceipt::empty();
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            Log::warning('Receipt extraction returned unparseable output', ['output' => $text]);

            return ExtractedReceipt::empty();
        }

        return new ExtractedReceipt(
            date: $this->asDate($data['date'] ?? null),
            liters: $this->asPositiveFloat($data['liters'] ?? null),
            priceTotal: $this->asPositiveFloat($data['price_total'] ?? null),
            odometerReading: $this->asPositiveInt($data['odometer_reading'] ?? null),
        );
    }

    private function asDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function asPositiveFloat(mixed $value): ?float
    {
        return is_numeric($value) && $value > 0 ? (float) $value : null;
    }

    private function asPositiveInt(mixed $value): ?int
    {
        return is_numeric($value) && $value > 0 ? (int) $value : null;
    }
}
