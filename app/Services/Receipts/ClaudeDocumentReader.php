<?php

namespace App\Services\Receipts;

use Anthropic\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Asks Claude to read an uploaded document and answer with JSON matching a
 * given schema. Knows nothing about receipts - callers supply the prompt and
 * the shape they expect.
 *
 * Every failure path returns null rather than throwing: a document we cannot
 * read must never block the upload it belongs to.
 */
class ClaudeDocumentReader
{
    public function __construct(
        private readonly Client $client,
        private readonly string $model,
        private readonly ?string $effort = null,
    ) {}

    /**
     * Effort is model-specific: Haiku 4.5 rejects the whole request with a 400
     * rather than ignoring it, so it has to be omitted rather than defaulted.
     */
    private function outputConfig(array $schema): array
    {
        $config = ['format' => ['type' => 'json_schema', 'schema' => $schema]];

        return $this->effort === null ? $config : ['effort' => $this->effort] + $config;
    }

    /**
     * Thinking is on by default and counts against maxTokens together with the
     * answer, so this has to cover reading the document as well as writing the
     * result. A multi-page scan needs far more room than a till receipt, and
     * running out truncates the JSON rather than failing loudly.
     */
    private const MAX_TOKENS = 8192;

    public function read(UploadedFile $file, string $prompt, array $schema): ?array
    {
        try {
            $response = $this->client->messages->create(
                maxTokens: self::MAX_TOKENS,
                model: $this->model,
                outputConfig: $this->outputConfig($schema),
                messages: [[
                    'role' => 'user',
                    'content' => [$this->fileBlock($file), ['type' => 'text', 'text' => $prompt]],
                ]],
            );
        } catch (\Throwable $e) {
            Log::warning('Document could not be read', ['exception' => $e]);

            return null;
        }

        if ($response->stopReason === 'refusal') {
            Log::warning('Document reading refused', ['details' => $response->stopDetails]);

            return null;
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            // stopReason distinguishes a truncated answer (max_tokens) from
            // genuinely malformed output - without it this is guesswork.
            Log::warning('Document reading returned unparseable output', [
                'stop_reason' => $response->stopReason,
                'output_tokens' => $response->usage->outputTokens,
                'output' => $text,
            ]);

            return null;
        }

        return $data;
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
}
