<?php

namespace Tests\Feature;

use Anthropic\Client;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Messages\Message;
use App\Services\Receipts\ClaudeDocumentReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Not every model accepts output_config.effort - Haiku 4.5 rejects the whole
 * request with a 400. Rather than making the operator keep a config flag in
 * step with the model they chose, the reader drops the parameter and
 * remembers, so any model works without being told.
 *
 * The SDK's message service is final and cannot be doubled, so these stand in
 * at the reader's own send() seam instead.
 */
class EffortFallbackTest extends TestCase
{
    /**
     * Records the effort of every attempt and raises the API's 400 when asked.
     *
     * @param  \Throwable|null  $failWith  raised on every attempt, whatever the effort
     */
    private function reader(
        bool $rejectEffort = false,
        ?string $effort = 'low',
        ?\Throwable $failWith = null,
    ): ClaudeDocumentReader {
        return new class($this->createStub(Client::class), 'claude-haiku-4-5', $effort, $rejectEffort, $failWith, fn (string $m) => $this->badRequest($m)) extends ClaudeDocumentReader
        {
            public array $attempts = [];

            public function __construct(
                Client $client,
                string $model,
                ?string $effort,
                private bool $rejectEffort,
                private ?\Throwable $failWith,
                private $badRequest,
            ) {
                parent::__construct($client, $model, $effort);
            }

            protected function send(UploadedFile $file, string $prompt, array $schema, ?string $effort): Message
            {
                $this->attempts[] = $effort;

                if ($this->failWith) {
                    throw $this->failWith;
                }

                if ($this->rejectEffort && $effort !== null) {
                    throw ($this->badRequest)('This model does not support the effort parameter.');
                }

                // Only the attempts are under test; the caller handles the rest.
                throw new \RuntimeException('reached the model');
            }
        };
    }

    /**
     * The SDK builds its exceptions from the PSR request/response pair, so a
     * realistic 400 has to be assembled the same way.
     */
    private function badRequest(string $apiMessage): BadRequestException
    {
        $response = new \GuzzleHttp\Psr7\Response(
            400, [], json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => $apiMessage]])
        );

        return new BadRequestException(new \GuzzleHttp\Psr7\Request('POST', '/v1/messages'), $response);
    }

    private function read(ClaudeDocumentReader $reader): ?array
    {
        return $reader->read(
            UploadedFile::fake()->create('beleg.pdf', 10, 'application/pdf'),
            'Lies das',
            ['type' => 'object', 'properties' => [], 'additionalProperties' => false],
        );
    }

    public function test_it_retries_without_effort_when_the_model_rejects_it(): void
    {
        $reader = $this->reader(rejectEffort: true);

        $this->read($reader);

        $this->assertSame(['low', null], $reader->attempts);
    }

    public function test_it_remembers_the_rejection_for_that_model(): void
    {
        $this->read($this->reader(rejectEffort: true));

        // A second document must not pay for the doomed first attempt again.
        $reader = $this->reader(rejectEffort: true);
        $this->read($reader);

        $this->assertSame([null], $reader->attempts);
    }

    public function test_a_model_that_accepts_effort_keeps_using_it(): void
    {
        $reader = $this->reader(rejectEffort: false);

        $this->read($reader);

        $this->assertSame(['low'], $reader->attempts);
        $this->assertFalse(Cache::has('anthropic:effort-unsupported:claude-haiku-4-5'));
    }

    public function test_an_unrelated_bad_request_is_not_retried(): void
    {
        $reader = $this->reader(
            failWith: $this->badRequest('messages: at least one message is required')
        );

        $this->assertNull($this->read($reader));
        $this->assertSame(['low'], $reader->attempts, 'a failure unrelated to effort must not be retried');
    }

    public function test_nothing_is_retried_when_no_effort_was_sent(): void
    {
        $reader = $this->reader(rejectEffort: true, effort: null);

        $this->read($reader);

        $this->assertSame([null], $reader->attempts);
    }
}
