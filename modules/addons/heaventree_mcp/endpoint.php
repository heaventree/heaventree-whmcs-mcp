<?php
declare(strict_types=1);
use Heaventree\WhmcsMcp\{Server,Reports,Store,McpException};

ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function htmcp_reply(int $status,?array $body): never { $encoded=$body===null?'':json_encode($body,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);http_response_code($status);header('Content-Length: '.strlen($encoded));if($encoded!=='')echo $encoded;exit; }
if (($_SERVER['HTTPS']??'')!=='on' && ($_SERVER['HTTPS']??'')!=='1' && (int)($_SERVER['SERVER_PORT']??0)!==443) htmcp_reply(426,['error'=>'HTTPS required. Configure TLS at the WHMCS host.']);
$root=dirname(__DIR__,3);if(!is_file($root.'/init.php'))htmcp_reply(503,['error'=>'WHMCS bootstrap unavailable.']);
require_once $root.'/init.php';
require_once __DIR__.'/lib/Server.php';require_once __DIR__.'/lib/Reports.php';require_once __DIR__.'/lib/Store.php';
try {
    $settings=\WHMCS\Database\Capsule::table('tbladdonmodules')->where('module','heaventree_mcp')->pluck('value','setting')->all();
    if (!$settings) htmcp_reply(503,['error'=>'Module inactive.']);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    $system=\WHMCS\Config\Setting::getValue('SystemURL');$parsed=parse_url($system);$expected=($parsed['scheme']??'').'://'.($parsed['host']??'').(isset($parsed['port'])?':'.$parsed['port']:'');
    if ($origin!==''&&!hash_equals($expected,$origin)) htmcp_reply(403,['error'=>'Origin not allowed.']);
    $header=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
    if (!preg_match('/^Bearer (htmcp_[a-f0-9]{64})$/D',$header,$match)) htmcp_reply(401,['error'=>'Bearer token required.']);
    $token=Store::authenticate($match[1],max(1,min(600,(int)($settings['requests_per_minute']??60))));
    $method=$_SERVER['REQUEST_METHOD']??'';
    if ($method!=='POST') {header('Allow: POST');htmcp_reply(405,['error'=>'Stateless JSON MCP supports POST; no SSE stream or session deletion is offered.']);}
    $accept=$_SERVER['HTTP_ACCEPT']??'';
    if (!str_contains($accept,'application/json')||!str_contains($accept,'text/event-stream'))htmcp_reply(406,['error'=>'Accept must include application/json and text/event-stream.']);
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))htmcp_reply(415,['error'=>'Content-Type must be application/json.']);
    $protocol=$_SERVER['HTTP_MCP_PROTOCOL_VERSION']??null;
    if ($protocol!==null&&!in_array($protocol,Server::PROTOCOLS,true))htmcp_reply(400,['error'=>'Unsupported MCP protocol version.']);
    $body=file_get_contents('php://input',false,null,0,65537);
    if (strlen($body)>65536)htmcp_reply(413,['error'=>'Request exceeds 64 KiB.']);
    try {$request=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException $e){htmcp_reply(400,Server::rpcError(null,-32700,'Invalid JSON.'));}
    if (!is_array($request)||array_is_list($request))htmcp_reply(400,Server::rpcError(null,-32600,'One JSON-RPC object required; batches not supported.'));
    $admin=(string)($settings['admin_username']??'');
    $active=\WHMCS\Database\Capsule::table('tbladmins')->where('username',$admin)->where('disabled',0)->exists();
    if (!$active)htmcp_reply(503,['error'=>'Configured enabled WHMCS administrator required.']);
    $catalog=json_decode(file_get_contents(__DIR__.'/catalog.json'),true,512,JSON_THROW_ON_ERROR);
    $server=new Server($catalog,fn($action,$args)=>localAPI($action,$args,$admin),[Reports::class,'run'],[Store::class,'approval'],($settings['enable_writes']??'')==='on');
    $result=$server->handle($request,$token);
    $outcome=$result===null?'notification':(isset($result['error'])||($result['result']['isError']??false)?'error':'success');
    $auditMethod=in_array($request['method']??'', ['initialize','ping','tools/list','tools/call','prompts/list','prompts/get','resources/list','resources/templates/list','resources/read','notifications/initialized','notifications/cancelled'],true)?$request['method']:'unknown';
    $auditTool=$request['params']['name']??'';
    if (!is_string($auditTool)||!preg_match('/^whmcs_[a-z0-9_]{1,90}$/D',$auditTool)) $auditTool='';
    Store::audit($token['id'],$auditMethod,$auditTool,$outcome);
    htmcp_reply($result===null?202:200,$result);
}catch(McpException $e){$rate=$e->getMessage()==='Rate limit exceeded.';if($rate)header('Retry-After: 60');htmcp_reply($rate?429:401,['error'=>$rate?'Rate limit exceeded.':'Unauthorized.']);}
catch(\Throwable $e){htmcp_reply(503,['error'=>'Module operation unavailable. Check installation and server-side configuration.']);}
