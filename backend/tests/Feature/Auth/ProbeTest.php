<?php
namespace Tests\Feature\Auth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ProbeTest extends TestCase {
    use RefreshDatabase;
    public function test_probe(): void {
        $user = User::factory()->create(['password' => 'Passw0rd!long']);
        $token = $this->postJson('/api/v1/auth/login', ['email'=>$user->email,'password'=>'Passw0rd!long'])->json('data.token');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $me = $this->app['router']->getMiddlewareGroups() ? null : null;
        // emulate: call guard directly with request carrying the token
        $req = \Illuminate\Http\Request::create('/api/v1/auth/me','GET',['_token'=>null],[],[],['HTTP_AUTHORIZATION'=>"Bearer $token"]);
        $this->app->instance('request',$req);
        $u = auth()->guard('sanctum')->setRequest($req)->user();
        dump(['guard_user_after_revoke'=> $u?->email]);
        dump(['raw_token_table'=>\DB::table('personal_access_tokens')->toSql()]);
        dump(['count'=>\DB::table('personal_access_tokens')->count()]);
        $this->assertTrue(true);
    }
}
