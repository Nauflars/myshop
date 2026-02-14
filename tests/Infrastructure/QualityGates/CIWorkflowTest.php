<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\QualityGates;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * CI Workflow Test
 *
 * Validates GitHub Actions workflow configuration for quality gates
 * Tests for User Story 2: Pull Request Automated Validation
 */
class CIWorkflowTest extends TestCase
{
    private const WORKFLOW_FILE = __DIR__ . '/../../../.github/workflows/quality-gates.yml';

    public function testWorkflowFileExists(): void
    {
        $this->assertFileExists(
            self::WORKFLOW_FILE,
            'GitHub Actions workflow file must exist at .github/workflows/quality-gates.yml'
        );
    }

    public function testWorkflowHasValidYamlSyntax(): void
    {
        $this->assertFileExists(self::WORKFLOW_FILE);

        $content = file_get_contents(self::WORKFLOW_FILE);
        $this->assertNotEmpty($content, 'Workflow file must not be empty');

        try {
            $workflow = Yaml::parse($content);
            $this->assertIsArray($workflow, 'Workflow must be valid YAML');
        } catch (\Exception $e) {
            $this->fail('Workflow YAML syntax is invalid: ' . $e->getMessage());
        }
    }

    public function testWorkflowHasRequiredTriggers(): void
    {
        $workflow = $this->loadWorkflow();

        $this->assertArrayHasKey('on', $workflow, 'Workflow must have "on" trigger definition');

        $triggers = $workflow['on'];
        $this->assertNotEmpty($triggers, 'Workflow must have at least one trigger');

        // Should trigger on pull requests
        $this->assertArrayHasKey(
            'pull_request',
            $triggers,
            'Workflow must trigger on pull_request events'
        );

        // Should trigger on push (for main/develop branches)
        $this->assertArrayHasKey(
            'push',
            $triggers,
            'Workflow must trigger on push events'
        );
    }

    public function testWorkflowHasQualityGatesJob(): void
    {
        $workflow = $this->loadWorkflow();

        $this->assertArrayHasKey('jobs', $workflow, 'Workflow must have jobs defined');
        $this->assertArrayHasKey(
            'quality-gates',
            $workflow['jobs'],
            'Workflow must have a "quality-gates" job'
        );
    }

    public function testWorkflowUsesUbuntuRunner(): void
    {
        $workflow = $this->loadWorkflow();
        $job = $workflow['jobs']['quality-gates'];

        $this->assertArrayHasKey('runs-on', $job, 'Job must specify runs-on');
        $this->assertStringContainsString(
            'ubuntu',
            $job['runs-on'],
            'Job must run on Ubuntu runner'
        );
    }

    public function testWorkflowHasRequiredServices(): void
    {
        $workflow = $this->loadWorkflow();
        $job = $workflow['jobs']['quality-gates'];

        $this->assertArrayHasKey('services', $job, 'Job must define services');

        $requiredServices = ['mysql', 'mongodb', 'redis', 'rabbitmq'];
        foreach ($requiredServices as $service) {
            $this->assertArrayHasKey(
                $service,
                $job['services'],
                "Job must have {$service} service configured"
            );
        }
    }

    public function testWorkflowHasRequiredSteps(): void
    {
        $workflow = $this->loadWorkflow();
        $job = $workflow['jobs']['quality-gates'];

        $this->assertArrayHasKey('steps', $job, 'Job must have steps defined');
        $steps = $job['steps'];

        // Extract step names
        $stepNames = array_map(
            fn (array $step) => $step['name'] ?? '',
            $steps
        );

        $requiredSteps = [
            'Checkout code',
            'Setup PHP',
            'Install dependencies',
            'Run Quality Gates',
        ];

        foreach ($requiredSteps as $requiredStep) {
            $found = false;
            foreach ($stepNames as $stepName) {
                if (str_contains($stepName, $requiredStep)) {
                    $found = true;

                    break;
                }
            }

            $this->assertTrue(
                $found,
                "Workflow must have a step containing '{$requiredStep}'"
            );
        }
    }

    public function testWorkflowRunsQualityGatesScript(): void
    {
        $workflow = $this->loadWorkflow();
        $job = $workflow['jobs']['quality-gates'];
        $steps = $job['steps'];

        // Find step that runs quality gates
        $qualityGatesStep = null;
        foreach ($steps as $step) {
            if (isset($step['run']) && str_contains($step['run'], 'quality-gates.sh')) {
                $qualityGatesStep = $step;

                break;
            }
        }

        $this->assertNotNull(
            $qualityGatesStep,
            'Workflow must have a step that runs scripts/quality-gates.sh'
        );
    }

    private function loadWorkflow(): array
    {
        $this->assertFileExists(self::WORKFLOW_FILE);
        $content = file_get_contents(self::WORKFLOW_FILE);

        return Yaml::parse($content);
    }
}
