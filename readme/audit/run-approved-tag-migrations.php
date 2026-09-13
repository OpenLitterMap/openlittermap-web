<?php

// - Rehearsal-only runner. Production execution is a separate approved operation.
// - Usage: DB_DATABASE=olm_rehearsal_NAME php readme/audit/run-approved-tag-migrations.php --database=olm_rehearsal_NAME --source=plasticBags [--apply]
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
});
$options = getopt('', ['database:', 'source:', 'apply', 'file:']);
$database = $options['database'] ?? '';
if (! preg_match('/^olm_rehearsal_[a-zA-Z0-9_]+$/D', $database)
    || Illuminate\Support\Facades\DB::selectOne('select database() as name')->name !== $database
    || config('database.redis.default.database') != 3 || config('database.redis.cache.database') != 4) {
    throw new RuntimeException('Use an explicit disposable olm_rehearsal_* database and isolated Redis databases 3/4.');
}
$reviewFile = $options['file'] ?? __DIR__ . '/TagCleanupReview-2026-09-12.csv';
$file = fopen($reviewFile, 'r');
$header = fgetcsv($file);
$rows = [];
while (($row = fgetcsv($file)) !== false) {
    if (count($row) !== count($header)) throw new RuntimeException('Malformed approval record.');
    $rows[] = array_combine($header, $row);
}
fclose($file);
$source = $options['source'] ?? 'plasticBags';
// - The plan refuses a review that omits a category the prepared database holds for this object.
$prepared = App\Models\Litter\Tags\LitterObject::where('key', $source)->sole()->categories()->pluck('categories.key')->all();
$plan = App\Services\Tags\ApprovedTagMigrationPlan::commands($rows, $source, $prepared);
$args = $plan['arguments'];
if (array_key_exists('apply', $options)) $args['--apply'] = true;
$start = microtime(true);
echo json_encode(['database' => $database, 'arguments' => $args, 'review_sha256' => hash_file('sha256', $reviewFile)]) . PHP_EOL;
$status = Illuminate\Support\Facades\Artisan::call('olm:migrate-tag', $args);
echo Illuminate\Support\Facades\Artisan::output();
echo json_encode(['exit' => $status, 'seconds' => round(microtime(true) - $start, 3)]) . PHP_EOL;
exit($status);
