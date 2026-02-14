<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\QualityGates;

use PHPUnit\Framework\TestCase;

/**
 * Validation Script Test
 *
 * Tests the quality gates validation script behavior
 * Tests for User Story 2: Pull Request Automated Validation
 */
class ValidationScriptTest extends TestCase
{
    private const SCRIPT_PATH = __DIR__ . '/../../../scripts/quality-gates.sh';

    public function testScriptExists(): void
    {
        $this->assertFileExists(
            self::SCRIPT_PATH,
            'Quality gates validation script must exist at scripts/quality-gates.sh'
        );
    }

    public function testScriptIsExecutable(): void
    {
        $this->assertFileExists(self::SCRIPT_PATH);

        // Check if file has execute permission (Unix-like systems)
        if (PHP_OS_FAMILY !== 'Windows') {
            $perms = fileperms(self::SCRIPT_PATH);
            $isExecutable = ($perms & 0x0040) || ($perms & 0x0008) || ($perms & 0x0001);

            $this->assertTrue(
                $isExecutable,
                'Quality gates script must be executable. Run: chmod +x scripts/quality-gates.sh'
            );
        } else {
            $this->markTestSkipped('Execute permission check skipped on Windows');
        }
    }

    public function testScriptHasShebang(): void
    {
        $content = file_get_contents(self::SCRIPT_PATH);

        $this->assertStringStartsWith(
            '#!/bin/bash',
            $content,
            'Script must start with proper shebang (#!/bin/bash)'
        );
    }

    public function testScriptContainsRequiredChecks(): void
    {
        $content = file_get_contents(self::SCRIPT_PATH);

        $requiredChecks = [
            'TESTS' => 'PHPUnit tests check',
            'PHPStan' => 'Static analysis check',
            'PHP CS Fixer' => 'Code style check',
            'Composer' => 'Composer validation check',
            'SYMFONY VALIDATION' => 'Symfony lint checks',
        ];

        foreach ($requiredChecks as $pattern => $description) {
            $this->assertStringContainsString(
                $pattern,
                $content,
                "Script must include {$description}"
            );
        }
    }

    public function testScriptHasConstitutionReference(): void
    {
        $content = file_get_contents(self::SCRIPT_PATH);

        $this->assertStringContainsString(
            'Constitution',
            $content,
            'Script must reference Constitution for governance'
        );
    }

    public function testScriptHasExitCodeHandling(): void
    {
        $content = file_get_contents(self::SCRIPT_PATH);

        // Script should handle exit codes properly
        $this->assertMatchesRegularExpression(
            '/exit\s+[01]/',
            $content,
            'Script must have proper exit code handling (exit 0 for success, exit 1 for failure)'
        );
    }

    public function testScriptValidatesConfiguration(): void
    {
        $content = file_get_contents(self::SCRIPT_PATH);

        // Script should check for required configuration files or tools
        $configFiles = [
            'phpstan',
            'phpunit',
            'php-cs-fixer',
        ];

        foreach ($configFiles as $configFile) {
            $this->assertStringContainsString(
                $configFile,
                $content,
                "Script should reference {$configFile} tool or configuration"
            );
        }
    }

    public function testScriptHasColorOutput(): void
    {
        $content = file_get_contents(self::SCRIPT_PATH);

        // Check for ANSI color codes or color variables
        $hasColors = str_contains($content, '\033[') ||
                     str_contains($content, 'COLOR') ||
                     str_contains($content, 'RED') ||
                     str_contains($content, 'GREEN');

        $this->assertTrue(
            $hasColors,
            'Script should have colored output for better readability'
        );
    }

    public function testScriptCountsFailures(): void
    {
        $content = file_get_contents(self::SCRIPT_PATH);

        // Script should track number of failures
        $this->assertMatchesRegularExpression(
            '/FAILED.*=|failures.*=|fail.*count/i',
            $content,
            'Script should count and report number of failures'
        );
    }
}
