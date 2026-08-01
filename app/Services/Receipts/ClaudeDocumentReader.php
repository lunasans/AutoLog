<?php

namespace App\Services\Receipts;

use Anthropic\Client;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Messages\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
     * Thinking is on by default and counts against maxTokens together with the
     * answer, so this has to cover reading the document as well as writing the
     * result. A multi-page scan needs far more room than a till receipt, and
     * running out truncates the JSON rather than failing loudly.
     */
    private const MAX_TOKENS = 8192;

    private function outputConfig(array $schema, ?string $effort): array
    {
        $config = ['format' => ['type' => 'json_schema', 'schema' => $schema]];

        return $effort === null ? $config : ['effort' => $effort] + $config;
    }

    /** Remembered per model, so switching models re-tests rather than assumes. */
    private function effortRejectedKey(): string
    {
        return 'anthropic:effort-unsupported:'.$this->model;
    }

    private function effortToSend(): ?string
    {
        return Cache::get($this->effortRejectedKey()) ? null : $this->effort;
    }

    public function read(UploadedFile $file, string $prompt, array $schema): ?array
    {
        $effort = $this->effortToSend();

        try {
            $response = $this->send($file, $prompt, $schema, $effort);
        } catch (BadRequestException $e) {
            // Not every model accepts effort - Haiku 4.5 rejects the request
            // outright instead of ignoring it. Rather than making the operator
            // keep a config flag in step with the model they picked, drop the
            // parameter and remember that this model won't take it.
            if ($effort === null || ! str_contains($e->getMessage(), 'effort')) {
                Log::warning('Document could not be read', ['exception' => $e]);

                return null;
            }

            Cache::forever($this->effortRejectedKey(), true);
            Log::info('Model rejected the effort parameter, retrying without it', ['model' => $this->model]);

            try {
                $response = $this->send($file, $prompt, $schema, null);
            } catch (\Throwable $retry) {
                Log::warning('Document could not be read', ['exception' => $retry]);

                return null;
            }
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

    /** Protected rather than private so tests can stand in for the SDK, which is final. */
    protected function send(UploadedFile $file, string $prompt, array $schema, ?string $effort): Message
    {
        return $this->client->messages->create(
            maxTokens: self::MAX_TOKENS,
            model: $this->model,
            outputConfig: $this->outputConfig($schema, $effort),
            messages: [[
                'role' => 'user',
                'content' => [$this->fileBlock($file), ['type' => 'text', 'text' => $prompt]],
            ]],
        );
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
