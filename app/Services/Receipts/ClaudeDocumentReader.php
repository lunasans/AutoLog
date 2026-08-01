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
    ) {}

    public function read(UploadedFile $file, string $prompt, array $schema): ?array
    {
        try {
            $response = $this->client->messages->create(
                maxTokens: 1024,
                model: $this->model,
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
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
            Log::warning('Document reading returned unparseable output', ['output' => $text]);

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
