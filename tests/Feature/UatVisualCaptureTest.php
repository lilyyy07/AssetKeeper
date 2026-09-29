<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Asset,MaintenanceRecord};
class UatVisualCaptureTest extends TestCase
{
 use RefreshDatabase;
 private function capture(string $name,string $html):void {
  file_put_contents(storage_path('app/uat-visual/'.$name.'.html'),str_replace('http://localhost/','file:///C:/xampp/htdocs/AssetKeeper/public/',$html));
 }
 public function test_capture_isolated_pages():void {
  $this->seed();$u=User::where('role','ICT Administrator')->first();
  $this->actingAs($u)->withSession(['email_mfa_user'=>$u->id]);
  $m=MaintenanceRecord::first();$m->update(['remarks'=>'Inspect the network connections and retain the service history for the next technician.']);
  $done=MaintenanceRecord::create(['asset_id'=>$m->asset_id,'issue'=>'Completed preventive service','type'=>'Preventive','priority'=>'Low','status'=>'Completed','date_reported'=>'2026-01-01 09:00:00','reported_time_recorded'=>true,'problem_attended_at'=>'2026-01-01 09:25:00','repair_started_at'=>'2026-01-01 10:00:00','actual_completion_date'=>'2026-01-01 12:10:00','completion_time_recorded'=>true,'created_by'=>$u->id]);
  foreach(['dashboard'=>'/dashboard','assets'=>'/assets','asset-details'=>'/assets/'.Asset::first()->id,'allocations'=>'/allocations','inventory'=>'/inventory','maintenance'=>'/maintenance','maintenance-details'=>'/maintenance/'.$m->id,'maintenance-completed'=>'/maintenance/'.$done->id,'reports'=>'/reports?type=maintenance','allocation-report'=>'/reports?type=allocation','users'=>'/users','profile'=>'/profile'] as $name=>$url){$this->capture($name,$this->get($url)->assertOk()->getContent());}
  foreach(['technician'=>'ICT Staff / Technician','management'=>'Management (HOD)'] as $name=>$role){$this->post('/logout');$user=User::where('role',$role)->first();$this->actingAs($user)->withSession(['email_mfa_user'=>$user->id]);$this->capture($name,$this->get('/dashboard')->assertOk()->getContent());}
  $this->post('/logout');$this->capture('login',$this->get('/login')->assertOk()->getContent());
  $this->capture('login-errors',$this->withSession(['errors'=>(new \Illuminate\Support\ViewErrorBag)->put('default',new \Illuminate\Support\MessageBag(['email'=>'The supplied credentials are invalid or the account is unavailable.']))])->get('/login')->assertOk()->getContent());
  $this->capture('otp',view('auth.mfa',['maskedEmail'=>'u***@example.test'])->render());
 }
}