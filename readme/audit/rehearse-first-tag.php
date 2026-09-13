<?php

// - Run only on a fresh disposable copy; refuse the protected baseline and any production name.
// - Record preparation preservation, migration/replay checks and actual CSV output locally.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
});
use Illuminate\Support\Facades\{Artisan, DB};
use App\Models\Litter\Tags\{CategoryObject, LitterObject};
use App\Models\Photo;

$db = DB::selectOne('select database() as name')->name;
if (!preg_match('/^olm_rehearsal_[a-zA-Z0-9_]+$/D', $db) || config('database.redis.default.database') != 3 || config('database.redis.cache.database') != 4) {
    throw new RuntimeException('Require disposable database and Redis 3/4.');
}
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS {$message}\n";
}
function runCommand(string $command, array $args = []): void {
    $start = microtime(true);
    $code = Artisan::call($command, $args);
    echo Artisan::output();
    echo json_encode(['command' => $command, 'arguments' => $args, 'seconds' => round(microtime(true)-$start,3), 'exit' => $code]) . PHP_EOL;
    check($code === 0, $command);
}
function checksums(array $tables): array {
    $values = [];
    foreach ($tables as $table) $values[$table] = DB::selectOne("CHECKSUM TABLE `{$table}`")->Checksum;
    return $values;
}
function digest(string $table, array $columns): string {
    $hash = hash_init('sha256');
    $query = DB::table($table)->select($columns);
    foreach ($table === 'metrics' ? ['timescale','location_type','location_id','user_id','year','month','week','bucket_date'] : ['id'] as $key) $query->orderBy($key);
    if ($table === 'metrics') $query->where(function ($q) {
        foreach (['uploads','tags','litter','brands','materials','custom_tags','xp'] as $column) $q->orWhere($column, '!=', 0);
    });
    foreach ($query->cursor() as $row) hash_update($hash, json_encode($row) . "\n");
    return hash_final($hash);
}
echo json_encode(['database' => $db, 'php' => PHP_VERSION, 'mysql' => DB::selectOne('select version() as v')->v]) . PHP_EOL;
$tables = ['photos','photo_tags','photo_tag_extra_tags','users','user_quick_tags','metrics','achievements','user_achievements'];
$checkpoint = sys_get_temp_dir() . '/' . $db . '-first-tag-checkpoint.json';
$restore = in_array('--resume', $argv, true) || in_array('--verify', $argv, true);
if (!$restore) {
$source = LitterObject::where('key', 'plasticBags')->sole();
check($source->retired_at === null, 'fresh source is not retired');
$beforePreparation = checksums($tables);
runCommand('migrate', ['--force' => true]);
runCommand('db:seed', ['--class' => 'Database\\Seeders\\Tags\\GenerateTagsSeeder', '--force' => true]);
check($beforePreparation === checksums($tables), 'preparation changes no observations, summaries, extras, quick tags, XP, metrics or achievements');
runCommand('db:seed', ['--class' => 'Database\\Seeders\\Tags\\GenerateTagsSeeder', '--force' => true]);
check($beforePreparation === checksums($tables), 'repeat preparation preserves data');
runCommand('olm:verify-tag-integrity');
$target = LitterObject::where('key', 'plastic_bag')->sole();
$sourceRows = DB::table('photo_tags')->where('litter_object_id', $source->id)->count();
$targetRows = DB::table('photo_tags')->where('litter_object_id', $target->id)->count();
$sourceItems = DB::table('photo_tags')->where('litter_object_id', $source->id)->sum('quantity');
$targetItems = DB::table('photo_tags')->where('litter_object_id', $target->id)->sum('quantity');
$tagColumns = ['id','photo_id','quantity','picked_up'];
$tagDigest = digest('photo_tags', $tagColumns);
$photoDigest = digest('photos', ['id','xp','total_tags']);
$userDigest = digest('users', ['id','xp']);
$metricColumns = array_values(array_diff(Illuminate\Support\Facades\Schema::getColumnListing('metrics'), ['created_at','updated_at']));
$metricsDigest = digest('metrics', $metricColumns);
$metricRowCount = DB::table('metrics')->count();
$extrasChecksum = checksums(['photo_tag_extra_tags']);
$quickBefore = DB::table('user_quick_tags')->orderBy('id')->get()->toArray();
$clo = CategoryObject::where('litter_object_id',$source->id)->sole();
$targetClo = CategoryObject::where('category_id',$clo->category_id)->where('litter_object_id',$target->id)->sole();
$oldPhotoIds = DB::table('photo_tags')->where('litter_object_id',$source->id)->distinct()->pluck('photo_id')->all();
    $state = compact('sourceRows','targetRows','sourceItems','targetItems','tagColumns','tagDigest','photoDigest','userDigest','metricColumns','metricsDigest','metricRowCount','extrasChecksum','quickBefore','oldPhotoIds');
    file_put_contents($checkpoint, json_encode($state, JSON_THROW_ON_ERROR));
    chmod($checkpoint, 0600);
} else {
    $state = json_decode(file_get_contents($checkpoint), true, 512, JSON_THROW_ON_ERROR);
    foreach (['sourceRows','targetRows','sourceItems','targetItems','tagColumns','tagDigest','photoDigest','userDigest','metricColumns','metricsDigest','metricRowCount','extrasChecksum','quickBefore','oldPhotoIds'] as $key) {
        if (!array_key_exists($key, $state)) throw new RuntimeException('Incomplete checkpoint.');
    }
    $state['quickBefore'] = array_map(fn ($row) => (object) $row, $state['quickBefore']);
    $source = LitterObject::where('key','plasticBags')->sole();
    $target = LitterObject::where('key','plastic_bag')->sole();
    $clo = CategoryObject::where('litter_object_id',$source->id)->sole();
    $targetClo = CategoryObject::where('category_id',$clo->category_id)->where('litter_object_id',$target->id)->sole();
}
if (!in_array('--verify', $argv, true)) {
runCommand('olm:migrate-tag', ['retired'=>'plasticBags','desired'=>'plastic_bag']);
runCommand('olm:migrate-tag', ['retired'=>'plasticBags','desired'=>'plastic_bag','--apply'=>true]);
}
check(DB::table('photo_tags')->where('litter_object_id',$source->id)->count() === 0, 'source emptied');
check(DB::table('photo_tags')->where('litter_object_id',$target->id)->count() === $state['sourceRows']+$state['targetRows'], 'destination row count');
check((int) DB::table('photo_tags')->where('litter_object_id',$target->id)->sum('quantity') === (int)$state['sourceItems']+(int)$state['targetItems'], 'destination quantity');
check($state['tagDigest'] === digest('photo_tags',$state['tagColumns']), 'every observation ID, photo, quantity and collection state preserved');
check($state['photoDigest'] === digest('photos',['id','xp','total_tags']), 'every photo XP and total preserved');
check($state['userDigest'] === digest('users',['id','xp']), 'every user XP preserved');
check($state['metricsDigest'] === digest('metrics',$state['metricColumns']), 'every nonzero MySQL metric value preserved');
check(DB::table('metrics')->count() >= $state['metricRowCount'], 'no metric rows removed');
echo json_encode(['additional_zero_metric_buckets' => DB::table('metrics')->count()-$state['metricRowCount']]) . PHP_EOL;
check($state['extrasChecksum'] === checksums(['photo_tag_extra_tags']), 'every extra unchanged');
foreach ($state['quickBefore'] as $quick) if ($quick->clo_id === $clo->id) { $quick->clo_id = $targetClo->id; }
$quickAfter = DB::table('user_quick_tags')->orderBy('id')->get()->toArray();
foreach ([$state['quickBefore'],$quickAfter] as $rows) foreach($rows as $row) unset($row->updated_at);
check($state['quickBefore'] == $quickAfter, 'quick tags preserved and redirected');
runCommand('olm:verify-tag-integrity');
$beforeReplay = checksums($tables);
runCommand('olm:migrate-tag', ['retired'=>'plasticBags','desired'=>'plastic_bag','--apply'=>true]);
check($beforeReplay === checksums($tables), 'replay changes no data');
// - Exercise both PDO connections on a committed rehearsal row; no HTTP requests run concurrently here.
$photoId = (int) $state['oldPhotoIds'][0];
config(['database.connections.lock_probe' => config('database.connections.mysql')]);
$second = DB::connection('lock_probe');
$second->statement('SET SESSION innodb_lock_wait_timeout=1');
DB::beginTransaction();
try {
    DB::table('photos')->where('id',$photoId)->lockForUpdate()->first();
    try {
        $second->table('photos')->where('id',$photoId)->lockForUpdate()->first();
        throw new RuntimeException('Second connection unexpectedly acquired the lock.');
    } catch (Illuminate\Database\QueryException $e) {
        check(($e->errorInfo[1] ?? null) === 1205, 'second connection waits on photo row lock');
    }
} finally { DB::rollBack(); DB::purge('lock_probe'); }
$photo = Photo::findOrFail($photoId);
$output = sys_get_temp_dir() . '/' . $db . '-exports';
if (!is_dir($output)) mkdir($output,0700,true);
foreach (['wide','long'] as $layout) {
    $export = new App\Exports\CreateCSVExport(null,null,null,$photo->user_id,[],[],['split','joined'],$layout);
    $csv = Maatwebsite\Excel\Facades\Excel::raw($export, Maatwebsite\Excel\Excel::CSV);
    check(strlen($csv)>0, "{$layout} CSV generated");
    file_put_contents("{$output}/{$layout}.csv",$csv);
    echo json_encode(['export'=>$layout,'bytes'=>strlen($csv),'sha256'=>hash('sha256',$csv)]) . PHP_EOL;
}
echo "Rehearsal checks complete. Production, mounted browsers, mobile, concurrent HTTP and Redis baseline parity remain separate gates.\n";
