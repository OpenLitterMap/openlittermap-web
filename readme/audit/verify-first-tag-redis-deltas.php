<?php

// - Read-only comparison for a rehearsal that started with empty dedicated Redis databases.
// - Negative source counts are expected deltas here, not a usable production Redis rebuild.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
});
use Illuminate\Support\Facades\{DB, Redis};
use App\Services\Redis\RedisKeys;
use App\Models\Photo;
$db = DB::selectOne('select database() as name')->name;
if (!preg_match('/^olm_rehearsal_[a-zA-Z0-9_]+$/D',$db) || config('database.redis.default.database') != 3) throw new RuntimeException('Disposable rehearsal and Redis 3 required.');
$sourceId = (int) DB::table('litter_objects')->where('key','plasticBags')->value('id');
$targetId = (int) DB::table('litter_objects')->where('key','plastic_bag')->value('id');
$scopes = []; $users = [];
// - The protected baseline is selected from only; every write already happened on the disposable copy.
$photos = DB::table('olm_postmig_6.photos as p')->whereNotNull('p.processed_at')->whereNull('p.deleted_at')
    ->whereExists(fn ($q) => $q->selectRaw('1')->from('olm_postmig_6.photo_tags as pt')->whereColumn('pt.photo_id','p.id')->where('pt.litter_object_id',$sourceId))
    ->get(['p.country_id','p.state_id','p.city_id','p.user_id','p.processed_tags']);
foreach ($photos as $photo) {
    $qty = (int) (json_decode($photo->processed_tags,true)['objects'][$sourceId] ?? 0);
    foreach (RedisKeys::getPhotoScopes((new Photo)->forceFill((array) $photo)) as $scope) {
        $scopes[$scope] = ($scopes[$scope] ?? 0)+$qty;
    }
    if ($photo->user_id !== null) $users[$photo->user_id] = ($users[$photo->user_id] ?? 0)+$qty;
}
$actual = Redis::pipeline(function ($pipe) use ($scopes,$users,$sourceId,$targetId) {
    foreach ($scopes as $scope=>$qty) {
        $pipe->hGet(RedisKeys::objects($scope),(string)$sourceId);
        $pipe->hGet(RedisKeys::objects($scope),(string)$targetId);
        $pipe->zScore(RedisKeys::ranking($scope,'objects'),(string)$sourceId);
        $pipe->zScore(RedisKeys::ranking($scope,'objects'),(string)$targetId);
    }
    foreach ($users as $id=>$qty) {
        $pipe->hGet(RedisKeys::user($id).':tags',"obj:{$sourceId}");
        $pipe->hGet(RedisKeys::user($id).':tags',"obj:{$targetId}");
    }
});
$expected=[];
foreach($scopes as $qty) array_push($expected,-$qty,$qty,-$qty,$qty);
foreach($users as $qty) array_push($expected,-$qty,$qty);
if (array_map('intval',$actual) !== $expected) throw new RuntimeException('Redis delta mismatch; inspect before resuming writes.');
echo json_encode(['verified_location_scopes'=>count($scopes),'verified_users'=>count($users),'global_source_delta'=>-$scopes[RedisKeys::global()],'global_destination_delta'=>$scopes[RedisKeys::global()],'checks'=>count($expected)]) . PHP_EOL;
