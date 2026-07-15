<?php

namespace Laravel\Doctor\Tests;

use Laravel\AgentDetector\AgentDetector;
use Laravel\Doctor\DoctorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->forgetAgentEnvironment();
    }

    protected function getPackageProviders($app): array
    {
        return [
            DoctorServiceProvider::class,
        ];
    }

    /**
     * Clear agent environment variables so output format detection stays
     * deterministic when the suite itself runs inside an AI agent.
     */
    protected function forgetAgentEnvironment(): void
    {
        putenv('AI_AGENT');

        foreach (array_keys(AgentDetector::AGENT_ENV_VARS) as $variable) {
            putenv($variable);
        }
    }
}
