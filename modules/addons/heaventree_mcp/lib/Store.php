<?php
declare(strict_types=1);
namespace Heaventree\WhmcsMcp;
use WHMCS\Database\Capsule;

final class Store
{
    public static function install(): void
    {
        if (!Capsule::schema()->hasTable('mod_htmcp_tokens')) Capsule::schema()->create('mod_htmcp_tokens',function($t){$t->string('id',32)->primary();$t->string('label',100);$t->string('hash',64)->unique();$t->text('scopes');$t->text('tools');$t->dateTime('expires');$t->boolean('revoked')->default(false);$t->integer('bucket')->default(0);$t->integer('used')->default(0);});
        if (!Capsule::schema()->hasTable('mod_htmcp_audit')) Capsule::schema()->create('mod_htmcp_audit',function($t){$t->bigIncrements('id');$t->string('token_id',32);$t->string('method',100);$t->string('tool',100);$t->string('outcome',32);$t->dateTime('created')->index();});
        if (!Capsule::schema()->hasTable('mod_htmcp_approvals')) Capsule::schema()->create('mod_htmcp_approvals',function($t){$t->string('id',32)->primary();$t->string('token_id',32);$t->string('tool',100);$t->string('fingerprint',64);$t->text('encrypted_args');$t->string('state',16);$t->integer('approved_by')->nullable();$t->dateTime('expires')->index();});
    }
    public static function createToken(string $label,array $scopes,array $tools,int $days): array
    {
        if (!$label||strlen($label)>100||$days<1||$days>365||!$scopes||array_diff($scopes,['read','write'])) throw new McpException('Invalid token settings.');
        $token='htmcp_'.bin2hex(random_bytes(32)); $id=bin2hex(random_bytes(16));
        Capsule::table('mod_htmcp_tokens')->insert(['id'=>$id,'label'=>$label,'hash'=>hash('sha256',$token),'scopes'=>json_encode(array_values($scopes),JSON_THROW_ON_ERROR),'tools'=>json_encode(array_values($tools),JSON_THROW_ON_ERROR),'expires'=>date('Y-m-d H:i:s',time()+$days*86400),'revoked'=>false,'bucket'=>0,'used'=>0]);
        return ['id'=>$id,'token'=>$token];
    }
    public static function authenticate(string $bearer,int $rpm): array
    {
        if (!preg_match('/^htmcp_[a-f0-9]{64}$/D',$bearer)) throw new McpException('Unauthorized.');
        return Capsule::connection()->transaction(function()use($bearer,$rpm){
            $row=Capsule::table('mod_htmcp_tokens')->where('hash',hash('sha256',$bearer))->lockForUpdate()->first();
            if (!$row||$row->revoked||strtotime($row->expires)<=time()||!hash_equals($row->hash,hash('sha256',$bearer))) throw new McpException('Unauthorized.');
            $bucket=intdiv(time(),60); $used=(int)$row->bucket===$bucket?(int)$row->used:0;
            if ($used>=$rpm) throw new McpException('Rate limit exceeded.');
            Capsule::table('mod_htmcp_tokens')->where('id',$row->id)->update(['bucket'=>$bucket,'used'=>$used+1]);
            return ['id'=>$row->id,'scopes'=>json_decode($row->scopes,true,512,JSON_THROW_ON_ERROR),'tools'=>json_decode($row->tools,true,512,JSON_THROW_ON_ERROR)];
        });
    }
    public static function fingerprint(string $tool,array $args): string
    {
        $sort=function(array $a)use(&$sort):array {if (!array_is_list($a)) ksort($a);foreach($a as &$v)if(is_array($v))$v=$sort($v);return $a;};
        return hash('sha256',$tool."\0".json_encode($sort($args),JSON_THROW_ON_ERROR));
    }
    public static function approval(string $mode,array $token,string $tool,array $args,?string $id): array
    {
        $hash=self::fingerprint($tool,$args);
        if ($mode==='prepare') {
            $id=bin2hex(random_bytes(16));
            Capsule::table('mod_htmcp_approvals')->insert(['id'=>$id,'token_id'=>$token['id'],'tool'=>$tool,'fingerprint'=>$hash,'encrypted_args'=>encrypt(json_encode($args,JSON_THROW_ON_ERROR)),'state'=>'pending','expires'=>date('Y-m-d H:i:s',time()+300)]);
            return ['dryRun'=>true,'approvalId'=>$id,'tool'=>$tool,'arguments'=>Server::redact($args),'expiresInSeconds'=>300,'nextAction'=>'Administrator must review and approve this exact request in WHMCS Addons > Heaventree MCP. Then call the same tool with identical arguments, dry_run=false and approval_id.'];
        }
        if (!$id||!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new McpException('Administrator-approved approval_id required.');
        $used=Capsule::table('mod_htmcp_approvals')->where('id',$id)->where('token_id',$token['id'])->where('tool',$tool)->where('fingerprint',$hash)->where('state','approved')->where('expires','>',date('Y-m-d H:i:s'))->update(['state'=>'consumed']);
        if ($used!==1) throw new McpException('Approval missing, expired, changed, or already consumed. Do not retry a write automatically.');
        return ['consumed'=>true];
    }
    public static function audit(string $token,string $method,string $tool,string $outcome): void
    {
        Capsule::table('mod_htmcp_audit')->insert(['token_id'=>$token,'method'=>substr($method,0,100),'tool'=>substr($tool,0,100),'outcome'=>$outcome,'created'=>date('Y-m-d H:i:s')]);
    }
}
