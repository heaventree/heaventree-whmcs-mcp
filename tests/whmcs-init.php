<?php
declare(strict_types=1);
// Synthetic WHMCS test double only. Never distribute as a WHMCS init.php.
namespace WHMCS\Database {
final class Capsule {
    public static array $rows=[];
    public static function table(string $name): Query {return new Query($name);}
    public static function connection(): self {return new self();}
    public function transaction(callable $fn){return $fn();}
}
final class Query {
    private string $table;private array $filters=[];
    public function __construct(string $table){$this->table=$table;}
    public function where(string $key,$op,$value=null):self {$this->filters[]=[$key,$value===null?'=':$op,$value===null?$op:$value];return $this;}
    public function lockForUpdate():self{return $this;}
    private function matches(array $row):bool {foreach($this->filters as [$key,$op,$value]){if($op==='='&&($row[$key]??null)!=$value)return false;if($op==='>'&&($row[$key]??null)<=$value)return false;}return true;}
    public function first(){foreach(Capsule::$rows[$this->table]??[] as $row)if($this->matches($row))return (object)$row;return null;}
    public function exists():bool{return $this->first()!==null;}
    public function insert(array $row):void{Capsule::$rows[$this->table][]=$row;}
    public function update(array $changes):int{$count=0;foreach(Capsule::$rows[$this->table] as &$row)if($this->matches($row)){$row=array_merge($row,$changes);$count++;}return $count;}
    public function pluck(string $value,string $key){$out=[];foreach(Capsule::$rows[$this->table]??[] as $row)if($this->matches($row))$out[$row[$key]]=$row[$value];return new Collection($out);}
}
final class Collection {private array $rows;public function __construct(array $rows){$this->rows=$rows;}public function all():array{return $this->rows;}}
}
namespace WHMCS\Config {final class Setting {public static function getValue(string $key):string{return 'https://fixture.example';}}}
namespace {
function encrypt(string $text):string{return base64_encode($text);}
function decrypt(string $text):string{return base64_decode($text,true);}
function localAPI(string $action,array $args,string $admin):array{return ['result'=>'success','action'=>$action,'arguments'=>$args,'fixture'=>true,'password'=>'synthetic-secret-must-be-redacted'];}
\WHMCS\Database\Capsule::$rows['tbladdonmodules']=[['module'=>'heaventree_mcp','setting'=>'admin_username','value'=>'fixture-admin'],['module'=>'heaventree_mcp','setting'=>'requests_per_minute','value'=>'60']];
\WHMCS\Database\Capsule::$rows['tbladmins']=[['id'=>1,'username'=>'fixture-admin','disabled'=>0]];
\WHMCS\Database\Capsule::$rows['mod_htmcp_tokens']=[['id'=>str_repeat('a',32),'label'=>'fixture','hash'=>hash('sha256','htmcp_'.str_repeat('a',64)),'scopes'=>'["read"]','tools'=>'[]','expires'=>date('Y-m-d H:i:s',time()+3600),'revoked'=>false,'bucket'=>'0','used'=>0]];
\WHMCS\Database\Capsule::$rows['mod_htmcp_audit']=[];
\WHMCS\Database\Capsule::$rows['mod_htmcp_approvals']=[];
}
