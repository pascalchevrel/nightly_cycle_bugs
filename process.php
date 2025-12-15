#!/usr/bin/env php
<?php
use \ReleaseInsights\Bugzilla;
use \ReleaseInsights\Utils;

require __DIR__ . '/Bugzilla.php';
require __DIR__ . '/Json.php';
require __DIR__ . '/URL.php';
require __DIR__ . '/Utils.php';

/**
 * Simple color helper
 */
function use_colors(): bool {
    static $use = null;
    if ($use !== null) {
        return $use;
    }
    $use = true;
    if (function_exists('posix_isatty') && defined('STDOUT')) {
        $use = posix_isatty(STDOUT);
    }
    return $use;
}

function color(string $text, string $style): string {
    if (!use_colors()) {
        return $text;
    }
    $styles = [
        'red'     => '0;31',
        'green'   => '0;32',
        'yellow'  => '1;33',
        'blue'    => '0;34',
        'magenta' => '0;35',
        'cyan'    => '0;36',
        'bold'    => '1;37',
    ];
    if (!isset($styles[$style])) {
        return $text;
    }
    return "\033[" . $styles[$style] . "m" . $text . "\033[0m";
}

function eprintln(string $msg, string $style = null): void {
    if ($style !== null) {
        $msg = color($msg, $style);
    }
    fwrite(STDERR, $msg . PHP_EOL);
}

/**
 * CLI argument parsing
 *
 * Usage:
 *   ./process <release_number> [--dry-run|-n]
 *
 * Example:
 *   ./process 147
 *   ./process 147 --dry-run
 */

$dryRun = false;
$releaseNumber = null;

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];

    if ($arg === '--dry-run' || $arg === '-n') {
        $dryRun = true;
        continue;
    }

    if ($releaseNumber === null) {
        $releaseNumber = $arg;
        continue;
    }

    eprintln("Unexpected argument: {$arg}", 'red');
    eprintln("Usage: {$argv[0]} <release_number> [--dry-run|-n]");
    exit(1);
}

if ($releaseNumber === null) {
    eprintln("Usage: {$argv[0]} <release_number> [--dry-run|-n]", 'yellow');
    eprintln("Example: {$argv[0]} 147", 'yellow');
    exit(1);
}

if (!ctype_digit($releaseNumber)) {
    eprintln("Error: <release_number> must be numeric. Got '{$releaseNumber}'.", 'red');
    exit(1);
}

$releaseNumber = (int) $releaseNumber;
if ($releaseNumber < 2) {
    eprintln("Error: release_number must be >= 2 (we need the previous Nightly tag).", 'red');
    exit(1);
}

$previousRelease = $releaseNumber - 1;

/*
 * Header
 */
echo str_repeat('-', 70) . PHP_EOL;
echo color("Firefox Nightly Bug Extraction Tool", 'cyan') . PHP_EOL;
echo "  - Fetches hg json-pushes for Nightly cycle" . PHP_EOL;
echo "  - Extracts bug IDs from pushlog" . PHP_EOL;
echo "  - Queries Bugzilla in batches" . PHP_EOL;
echo "  - Produces a single JSON file" . PHP_EOL;
echo str_repeat('-', 70) . PHP_EOL;

echo "Nightly release: " . color((string) $releaseNumber, 'bold') . PHP_EOL;
if ($dryRun) {
    echo color("Mode: DRY-RUN (no Bugzilla queries, no final JSON output)\n", 'yellow');
}

/*
 * Build hg json-pushes URL
 * Example for 147:
 *   https://hg.mozilla.org/mozilla-central/json-pushes
 *     ?fromchange=FIREFOX_NIGHTLY_146_END
 *     &tochange=FIREFOX_NIGHTLY_147_END
 *     &full
 *     &version=2
 */

$fromTag = "FIREFOX_NIGHTLY_{$previousRelease}_END";
$toTag   = "FIREFOX_NIGHTLY_{$releaseNumber}_END";

$hgBaseUrl = 'https://hg.mozilla.org/mozilla-central/json-pushes';
$hgUrl = $hgBaseUrl
    . '?fromchange=' . urlencode($fromTag)
    . '&tochange='   . urlencode($toTag)
    . '&full&version=2';

echo "From tag: " . color($fromTag, 'blue') . PHP_EOL;
echo "To tag:   " . color($toTag, 'blue') . PHP_EOL;
echo "Fetching hg pushes JSON from:\n  " . color($hgUrl, 'magenta') . PHP_EOL;

/*
 * Fetch hg json-pushes
 */
$ch = curl_init($hgUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$hgResponse = curl_exec($ch);

if ($hgResponse === false) {
    eprintln('cURL error while fetching hg json-pushes: ' . curl_error($ch), 'red');
    curl_close($ch);
    exit(1);
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300) {
    eprintln("Error: hg.mozilla.org returned HTTP {$httpCode}", 'red');
    exit(1);
}

/*
 * Save hg JSON to a local file (so we can reuse Bugzilla::getBugsFromHgWeb)
 */
$inputFileName  = "json-pushes-nightly{$releaseNumber}.json";
$log_source     = __DIR__ . "/data/{$inputFileName}";

if (!is_dir(dirname($log_source))) {
    mkdir(dirname($log_source), 0777, true);
}

file_put_contents($log_source, $hgResponse);
echo "Saved hg log to: " . color($log_source, 'green') . PHP_EOL;

/*
 * Parse bugs from hg log
 */
$parsed = Bugzilla::getBugsFromHgWeb($log_source);
$allIds = $parsed['bug_fixes'] ?? [];

$uniqueIds = array_values(array_unique($allIds));
$totalBugs = count($uniqueIds);

echo "Log parsing done. Found " . color((string)$totalBugs, 'bold') . " unique bug IDs.\n";

if ($dryRun) {
    echo color("DRY-RUN: stopping before Bugzilla queries and JSON export.", 'yellow') . PHP_EOL;
    if ($totalBugs > 0) {
        $preview = array_slice($uniqueIds, 0, 10);
        echo "Sample bug IDs: " . implode(', ', $preview)
           . ($totalBugs > 10 ? " …" : "") . PHP_EOL;
    }
    exit(0);
}

$chunks = array_chunk($uniqueIds, 150);
$allBugs = [];

echo color("Start querying Bugzilla (" . count($chunks) . " chunks)…", 'cyan') . PHP_EOL;

$step = 0;
foreach ($chunks as $chunk) {
    $step++;
    $idParam = implode(',', $chunk);

    $url = 'https://bugzilla.mozilla.org/rest/bug'
         . '?id=' . urlencode($idParam);

    echo "  Chunk {$step}/" . count($chunks) . " → "
       . "requesting " . count($chunk) . " bugs…" . PHP_EOL;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if ($response === false) {
        throw new \RuntimeException('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \RuntimeException(
            'JSON decode error: ' . json_last_error_msg() . "\nRaw response:\n" . $response
        );
    }

    if (!empty($data['bugs'])) {
        $allBugs = array_merge($allBugs, $data['bugs']);
    }

    echo "    " . color("OK", 'green')
         . " – total bugs collected so far: " . count($allBugs) . PHP_EOL;
    sleep(2); // be nice to Bugzilla
}

/*
 * Write output file
 */
$outputFileName = "json-bugs-nightly{$releaseNumber}.json";
$bz_dest        = __DIR__ . "/data/output/{$outputFileName}";

if (!is_dir(dirname($bz_dest))) {
    mkdir(dirname($bz_dest), 0777, true);
}

file_put_contents($bz_dest, json_encode($allBugs));

echo color("Exported JSON: {$bz_dest}", 'green') . PHP_EOL;
echo color("Done.", 'cyan') . PHP_EOL;
