<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path=__DIR__.'/app/uat-preservation-before.json';
if(($argv[1]??'')==='before'){
 $snapshot=[];
 foreach(['users','departments','asset_categories','assets','allocations','maintenance_records','activity_logs'] as $table){
  $rows=Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get();
  $snapshot[$table]=['count'=>$rows->count(),'columns'=>Illuminate\Support\Facades\Schema::getColumnListing($table),'hashes'=>[]];
  foreach($rows as $row){$data=(array)$row;ksort($data);$snapshot[$table]['hashes'][$row->id]=hash('sha256',json_encode($data));}
 }
 file_put_contents($path,json_encode($snapshot));
 echo json_encode(array_map(fn($s)=>$s['count'],$snapshot));
}else{
 $snapshot=json_decode(file_get_contents($path),true);$result=[];$ok=true;
 foreach($snapshot as $table=>$old){
  $rows=Illuminate\Support\Facades\DB::table($table)->select($old['columns'])->get()->keyBy('id');$unchanged=true;
  foreach($old['hashes'] as $id=>$hash){$data=isset($rows[$id])?(array)$rows[$id]:[];ksort($data);if(hash('sha256',json_encode($data))!==$hash){$unchanged=false;}}
  $result[$table]=['before'=>$old['count'],'after'=>$rows->count(),'original_rows_unchanged'=>$unchanged];$ok=$ok&&$unchanged;
 }
 file_put_contents(__DIR__.'/app/uat-preservation-result.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result,JSON_PRETTY_PRINT);exit($ok?0:1);
}