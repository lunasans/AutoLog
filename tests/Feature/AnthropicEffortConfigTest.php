<?php

namespace Tests\Feature;

use App\Services\Receipts\ClaudeDocumentReader;
use Tests\TestCase;

/**
 * Not every model accepts output_config.effort - Haiku 4.5 rejects the whole
 * request with a 400 rather than ignoring it. Leaving ANTHROPIC_EFFORT empty
 * has to drop the parameter, not send it as an empty value.
 */
class AnthropicEffortConfigTest extends TestCase
{
    private function effortOfResolvedReader(): ?string
    {
        $reader = $this->app->make(ClaudeDocumentReader::class);

        $property = (new \ReflectionClass($reader))->getProperty('effort');
        $property->setAccessible(true);

        return $property->getValue($reader);
    }

    public function test_a_blank_effort_setting_is_dropped(): void
    {
        config(['services.anthropic.key' => 'sk-ant-test', 'services.anthropic.effort' => '']);

        $this->assertNull($this->effortOfResolvedReader());
    }

    public function test_a_configured_effort_is_passed_through(): void
    {
        config(['services.anthropic.key' => 'sk-ant-test', 'services.anthropic.effort' => 'low']);

        $this->assertSame('low', $this->effortOfResolvedReader());
    }

    public function test_no_reader_is_built_without_an_api_key(): void
    {
        config(['services.anthropic.key' => null]);

        $this->assertNull($this->app->make(ClaudeDocumentReader::class));
    }
}
