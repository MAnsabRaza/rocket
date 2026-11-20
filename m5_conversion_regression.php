<?php
/* ---------------------------------------------------------------------------
 * M5 — conversion regression against the deployed production code.  READ ONLY.
 *
 *     cd /var/www/vhosts/dctrd.us/httpdocs
 *     php m5_conversion_regression.php
 *
 * M2's acceptance criterion is that conversions return equivalent results after
 * the refactor. The M1 audit captured a 354 row before-state fixture from the
 * live service on 20 August 2026, before any code was moved. This replays every
 * one of those rows against the service now deployed in production and reports
 * three outcomes per row:
 *
 *   MATCH       identical to the pre-refactor value
 *   APPROVED    differs, and the difference is one of the 54 corrections the
 *               project owner approved under decision 4 on 20 August 2026
 *   UNEXPLAINED differs for any other reason
 *
 * UNEXPLAINED must be zero. Anything else is a regression in production.
 *
 * It also enumerates every call site of the conversion service in the codebase,
 * so the report states which screens the conversion path actually reaches
 * rather than leaving that as an assumption.
 *
 * Nothing is written to the database. One file is written: the report.
 * ------------------------------------------------------------------------- */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UnitConversionService;

$BASELINE    = __DIR__ . '/M2_before_state_fixture.csv';
$CORRECTIONS = __DIR__ . '/unit_conversion_approved_corrections.csv';
$OUT         = __DIR__ . '/m5_conversion_regression.md';

foreach ([$BASELINE, $CORRECTIONS] as $f) {
    if (! file_exists($f)) { fwrite(STDERR, "Missing " . basename($f) . " in " . __DIR__ . "\n"); exit(1); }
}

/** Reads a CSV with a header row, tolerating a UTF-8 byte order mark. */
function readCsv(string $path): array
{
    $rows = [];
    $fh = fopen($path, 'r');
    $head = fgetcsv($fh);
    if ($head && isset($head[0])) { $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]); }
    while (($r = fgetcsv($fh)) !== false) {
        if ($r === [null] || $r === false) { continue; }
        $rows[] = array_combine($head, array_pad(array_slice($r, 0, count($head)), count($head), ''));
    }
    fclose($fh);
    return $rows;
}

function keyOf(string $cat, string $from, string $to, $input): string
{
    return $cat . '|' . $from . '|' . $to . '|' . rtrim(rtrim(number_format((float) $input, 6, '.', ''), '0'), '.');
}

function same($a, $b): bool
{
    if ($a === null || $b === null) { return false; }
    $x = (float) $a; $y = (float) $b;
    if ($x === $y) { return true; }
    $scale = max(abs($x), abs($y), 1e-12);
    return abs($x - $y) <= $scale * 1e-9;
}

$baseline    = readCsv($BASELINE);
$corrections = readCsv($CORRECTIONS);

$approved = [];
foreach ($corrections as $c) {
    $approved[keyOf($c['category'], $c['from_unit'], $c['to_unit'], $c['input'])] = $c['approved_output'];
}

$svc = app(UnitConversionService::class);

$match = 0; $approvedHit = 0; $unexplained = []; $errors = []; $approvedUsed = [];

foreach ($baseline as $row) {
    $cat  = $row['category'];
    $from = $row['from_unit'];
    $to   = $row['to_unit'];
    $in   = (float) $row['input'];
    $want = $row['baseline_output'];

    try {
        $got = $svc->convert($in, $cat, $from, $to);
    } catch (Throwable $e) {
        $errors[] = [$cat, $from, $to, $in, get_class($e) . ': ' . $e->getMessage()];
        continue;
    }

    if (same($got, $want)) { $match++; continue; }

    $k = keyOf($cat, $from, $to, $in);
    if (isset($approved[$k]) && same($got, $approved[$k])) {
        $approvedHit++; $approvedUsed[$k] = true; continue;
    }

    $unexplained[] = [$cat, $from, $to, $in, $want, $got, isset($approved[$k]) ? $approved[$k] : '—'];
}

$approvedUnused = array_diff(array_keys($approved), array_keys($approvedUsed));

/* ----------------------------------------------------------- call sites */

function callSites(string $root): array
{
    $patterns = ['UnitConversionService', 'convertForUser', 'formatForUser', 'getPreferredUnit', 'getAvailableUnits'];
    $dirs = ['app', 'resources/views', 'routes', 'config'];
    $hits = [];

    foreach ($dirs as $d) {
        $base = $root . '/' . $d;
        if (! is_dir($base)) { continue; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! $file->isFile()) { continue; }
            $path = $file->getPathname();
            if (! preg_match('/\.(php|blade\.php)$/', $path)) { continue; }
            $src = @file_get_contents($path);
            if ($src === false) { continue; }
            $found = [];
            foreach ($patterns as $p) {
                $n = substr_count($src, $p);
                if ($n > 0) { $found[$p] = $n; }
            }
            if ($found !== []) { $hits[str_replace($root . '/', '', $path)] = $found; }
        }
    }
    ksort($hits);
    return $hits;
}

$sites = callSites(__DIR__);

/* --------------------------------------------------------------- report */

$md = [];
$md[] = '# M5 — Conversion Regression, Production';
$md[] = '';
$md[] = '| | |';
$md[] = '|---|---|';
$md[] = '| Prepared by | Developer |';
$md[] = '| Generated | ' . date('j F Y H:i') . ' UTC |';
$md[] = '| Environment | production, ' . config('app.url') . ' |';
$md[] = '| Baseline | 354 rows captured 20 August 2026, before any refactor |';
$md[] = '| Approved corrections | ' . count($approved) . ', decision 4, 20 August 2026 |';
$md[] = '';
$md[] = '---';
$md[] = '';
$md[] = '## 1. Result';
$md[] = '';
$md[] = '| Outcome | Rows |';
$md[] = '|---|---:|';
$md[] = '| Identical to the pre-refactor value | ' . $match . ' |';
$md[] = '| Differs, and the difference is an approved correction | ' . $approvedHit . ' |';
$md[] = '| **Differs for any other reason** | **' . count($unexplained) . '** |';
$md[] = '| Raised an exception | ' . count($errors) . ' |';
$md[] = '| Total replayed | ' . count($baseline) . ' |';
$md[] = '';

if ($unexplained === [] && $errors === [] && $approvedUnused === []) {
    $md[] = 'Every one of the ' . count($baseline) . ' captured conversions either returns the';
    $md[] = 'identical pre-refactor value or differs only by a correction the project owner';
    $md[] = 'approved. **No unexplained change in conversion behaviour exists in production.**';
} else {
    $md[] = '**' . count($unexplained) . ' row(s) differ without an approved reason and ' . count($errors) . ' raised an exception.**';
    $md[] = 'Each needs a cause before M5 can be submitted.';
}

if ($approvedUnused !== []) {
    $md[] = '';
    $md[] = '### Approved corrections not observed';
    $md[] = '';
    $md[] = count($approvedUnused) . ' of the ' . count($approved) . ' approved corrections did not appear.';
    $md[] = 'Either the fixture does not contain that row or the correction is not applied in production.';
    $md[] = '';
    foreach ($approvedUnused as $k) { $md[] = '- `' . $k . '`'; }
}

if ($unexplained !== []) {
    $md[] = '';
    $md[] = '### Unexplained differences';
    $md[] = '';
    $md[] = '| Category | From | To | Input | Baseline | Production | Approved value |';
    $md[] = '|---|---|---|---:|---:|---:|---:|';
    foreach ($unexplained as $u) {
        $md[] = '| ' . implode(' | ', array_map('strval', $u)) . ' |';
    }
}

if ($errors !== []) {
    $md[] = '';
    $md[] = '### Exceptions';
    $md[] = '';
    $md[] = '| Category | From | To | Input | Exception |';
    $md[] = '|---|---|---|---:|---|';
    foreach ($errors as $e) { $md[] = '| ' . implode(' | ', array_map('strval', $e)) . ' |'; }
}

$md[] = '';
$md[] = '---';
$md[] = '';
$md[] = '## 2. Where the conversion path is reached from';
$md[] = '';
$md[] = 'Every file in the deployed application that references the conversion';
$md[] = 'service or a preference helper. This is the scope of the screens the';
$md[] = 'result above covers.';
$md[] = '';
$md[] = '| File | References |';
$md[] = '|---|---|';
foreach ($sites as $file => $found) {
    $parts = [];
    foreach ($found as $p => $n) { $parts[] = $p . ' ×' . $n; }
    $md[] = '| `' . $file . '` | ' . implode(', ', $parts) . ' |';
}
$md[] = '';
$md[] = 'Files under `app/Services/UnitConversion` are the implementation itself and';
$md[] = 'are listed for completeness. Blade files are the rendering surfaces, and';
$md[] = 'controllers are the entry points.';

file_put_contents($OUT, implode("\n", $md) . "\n");

/* -------------------------------------------------------------- console */

echo 'M5 CONVERSION REGRESSION, PRODUCTION', PHP_EOL;
echo 'environment      : ', config('app.env'), '  ', config('app.url'), PHP_EOL;
echo 'baseline rows    : ', count($baseline), PHP_EOL;
echo 'approved corr.   : ', count($approved), PHP_EOL, PHP_EOL;
echo 'identical        : ', $match, PHP_EOL;
echo 'approved change  : ', $approvedHit, PHP_EOL;
echo 'UNEXPLAINED      : ', count($unexplained), PHP_EOL;
echo 'exceptions       : ', count($errors), PHP_EOL;
if ($approvedUnused !== []) { echo 'approved unused  : ', count($approvedUnused), PHP_EOL; }
echo PHP_EOL;
foreach (array_slice($unexplained, 0, 8) as $u) {
    echo '  DIFF ', str_pad($u[0], 15), $u[1], '->', $u[2], ' in=', $u[3], ' was=', $u[4], ' now=', $u[5], PHP_EOL;
}
foreach (array_slice($errors, 0, 5) as $e) {
    echo '  ERR  ', str_pad($e[0], 15), $e[1], '->', $e[2], ' ', $e[4], PHP_EOL;
}
echo PHP_EOL, 'call sites found : ', count($sites), PHP_EOL;
echo 'report written to ', $OUT, PHP_EOL;
if ($approvedUnused !== []) {
    echo PHP_EOL, 'FAIL  ', count($approvedUnused), ' of ', count($approved),
         ' approved corrections were not observed.', PHP_EOL,
         '      Production is returning pre-correction values. This is a regression,', PHP_EOL,
         '      not a pass, even though no row differs unexplainably.', PHP_EOL;
}
echo PHP_EOL, ($unexplained === [] && $errors === [] && $approvedUnused === []
    ? 'PASS  no unexplained change in production conversion behaviour.'
    : 'REVIEW REQUIRED  see the report.'), PHP_EOL;
