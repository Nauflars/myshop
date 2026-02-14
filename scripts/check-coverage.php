#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Coverage Threshold Checker
 *
 * Validates test coverage meets minimum thresholds required by Constitution v1.1.0
 *
 * Usage:
 *   php scripts/check-coverage.php [clover-file] [min-line-coverage] [min-method-coverage]
 *
 * Example:
 *   php scripts/check-coverage.php var/coverage/clover.xml 80 75
 *
 * Exit codes:
 *   0 - Coverage meets thresholds
 *   1 - Coverage below thresholds
 *   2 - Error reading coverage file
 */

// Colors for output
const COLOR_RED = "\033[0;31m";
const COLOR_GREEN = "\033[0;32m";
const COLOR_YELLOW = "\033[1;33m";
const COLOR_NC = "\033[0m"; // No Color

// Default thresholds (Constitution v1.1.0 requirements)
const DEFAULT_MIN_LINE_COVERAGE = 80;
const DEFAULT_MIN_METHOD_COVERAGE = 75;

// Parse arguments
$cloverFile = $argv[1] ?? 'var/coverage/clover.xml';
$minLineCoverage = (int) ($argv[2] ?? DEFAULT_MIN_LINE_COVERAGE);
$minMethodCoverage = (int) ($argv[3] ?? DEFAULT_MIN_METHOD_COVERAGE);

echo "\n";
echo "🔍 Coverage Threshold Checker\n";
echo "══════════════════════════════════════════════\n";
echo "Constitution v1.1.0: Minimum Requirements\n";
echo "  - Line Coverage: {$minLineCoverage}%\n";
echo "  - Method Coverage: {$minMethodCoverage}%\n";
echo "══════════════════════════════════════════════\n\n";

// Check if clover file exists
if (!file_exists($cloverFile)) {
    echo COLOR_RED . "✗ FAIL: Coverage file not found: {$cloverFile}" . COLOR_NC . "\n";
    echo COLOR_YELLOW . "  Run: php vendor/bin/phpunit --coverage-clover=var/coverage/clover.xml" . COLOR_NC . "\n\n";
    exit(2);
}

// Parse XML
try {
    $xml = new SimpleXMLElement(file_get_contents($cloverFile));
} catch (Exception $e) {
    echo COLOR_RED . "✗ FAIL: Error parsing coverage file: {$e->getMessage()}" . COLOR_NC . "\n\n";
    exit(2);
}

// Extract metrics from clover XML
$metrics = $xml->project->metrics;
if (!$metrics) {
    echo COLOR_RED . "✗ FAIL: Invalid clover file format" . COLOR_NC . "\n\n";
    exit(2);
}

$totalElements = (int) $metrics['elements'];
$coveredElements = (int) $metrics['coveredelements'];
$totalStatements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];
$totalMethods = (int) $metrics['methods'];
$coveredMethods = (int) $metrics['coveredmethods'];

// Calculate percentages
$elementCoverage = $totalElements > 0 ? ($coveredElements / $totalElements) * 100 : 0;
$lineCoverage = $totalStatements > 0 ? ($coveredStatements / $totalStatements) * 100 : 0;
$methodCoverage = $totalMethods > 0 ? ($coveredMethods / $totalMethods) * 100 : 0;

// Display results
echo "📊 Coverage Report:\n";
echo "──────────────────────────────────────────────\n";
printf("  Elements:  %5d / %5d (%6.2f%%)\n", $coveredElements, $totalElements, $elementCoverage);
printf("  Lines:     %5d / %5d (%6.2f%%) %s\n",
    $coveredStatements,
    $totalStatements,
    $lineCoverage,
    $lineCoverage >= $minLineCoverage ? COLOR_GREEN . '✓' . COLOR_NC : COLOR_RED . '✗' . COLOR_NC
);
printf("  Methods:   %5d / %5d (%6.2f%%) %s\n",
    $coveredMethods,
    $totalMethods,
    $methodCoverage,
    $methodCoverage >= $minMethodCoverage ? COLOR_GREEN . '✓' . COLOR_NC : COLOR_RED . '✗' . COLOR_NC
);
echo "──────────────────────────────────────────────\n\n";

// Check thresholds
$passed = true;
$failures = [];

if ($lineCoverage < $minLineCoverage) {
    $passed = false;
    $failures[] = sprintf(
        'Line coverage %.2f%% is below minimum %d%%',
        $lineCoverage,
        $minLineCoverage
    );
}

if ($methodCoverage < $minMethodCoverage) {
    $passed = false;
    $failures[] = sprintf(
        'Method coverage %.2f%% is below minimum %d%%',
        $methodCoverage,
        $minMethodCoverage
    );
}

// Output result
if ($passed) {
    echo COLOR_GREEN . "✓ PASS: Coverage meets minimum thresholds" . COLOR_NC . "\n";
    echo "  Constitution v1.1.0 compliance: ✓\n\n";
    exit(0);
} else {
    echo COLOR_RED . "✗ FAIL: Coverage below minimum thresholds" . COLOR_NC . "\n";
    foreach ($failures as $failure) {
        echo COLOR_RED . "  - {$failure}" . COLOR_NC . "\n";
    }
    echo "\n";
    echo COLOR_YELLOW . "📖 Improvement Tips:" . COLOR_NC . "\n";
    echo "  1. Write unit tests for uncovered methods\n";
    echo "  2. Add integration tests for complex workflows\n";
    echo "  3. Test edge cases and error conditions\n";
    echo "  4. Review coverage report: var/coverage/index.html\n\n";
    exit(1);
}
