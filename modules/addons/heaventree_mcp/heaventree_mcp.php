<?php
declare(strict_types=1);
use WHMCS\Database\Capsule;
use Heaventree\WhmcsMcp\{Store,Server,Reports};
if (!defined('WHMCS')) die('Direct access denied.');
require_once __DIR__.'/lib/Server.php';require_once __DIR__.'/lib/Reports.php';require_once __DIR__.'/lib/Store.php';
function heaventree_mcp_config(): array {
    return ['name'=>'Heaventree WHMCS MCP','description'=>'Original MIT-licensed MCP addon. Native WHMCS local API, business intelligence and administrator-approved writes. No remote database or WHMCS API IP allowlist needed.','author'=>'Heaventree','version'=>'0.2.0','language'=>'english','fields'=>[
        'admin_username'=>['FriendlyName'=>'Existing administrator username','Type'=>'text','Size'=>'40','Description'=>'Enabled WHMCS admin used by localAPI. Use a least-privilege admin role. No password or remote API credentials required.'],
        'enable_writes'=>['FriendlyName'=>'Enable approved writes','Type'=>'yesno','Description'=>'Default OFF. Execution additionally requires a write-scoped token and exact administrator-approved one-use request.'],
        'notify_url'=>['FriendlyName'=>'Ticket change webhook URL','Type'=>'text','Size'=>'80','Description'=>'Optional HTTPS URL told the ticket id whenever a ticket is opened, replied to, noted or changed, so a mirror (such as Heaventree Control) refreshes at once. Only the ticket id and event name are sent.'],
        'notify_secret'=>['FriendlyName'=>'Ticket webhook signing secret','Type'=>'password','Size'=>'64','Description'=>'Shared secret; each notification carries an HMAC-SHA256 signature in X-Htmcp-Signature.'],
        'requests_per_minute'=>['FriendlyName'=>'Requests per minute per token','Type'=>'text','Size'=>'5','Default'=>'60'],
    ]];
}
function heaventree_mcp_activate(): array {try{Store::install();return ['status'=>'success','description'=>'Installed. Configure the existing administrator, grant addon access to trusted roles, then create a read-only token.'];}catch(Throwable $e){return ['status'=>'error','description'=>'Installation failed. Check database schema permissions. No WHMCS billing/client tables were changed.'];}}
function heaventree_mcp_deactivate(): array {Capsule::table('mod_htmcp_tokens')->update(['revoked'=>true]);return ['status'=>'success','description'=>'All module tokens revoked. Module-owned tables retained for audit. No client, invoice or domain data removed.'];}
function htmcp_escape($value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function heaventree_mcp_output(array $vars): void {
    header('Cache-Control: no-store');
    $adminId=(int)($_SESSION['adminid']??0);
    if (!$adminId||!Capsule::table('tbladmins')->where('id',$adminId)->where('disabled',0)->exists()) {echo '<p>Enabled administrator session required.</p>';return;}
    if (!isset($_SESSION['htmcp_csrf'])) $_SESSION['htmcp_csrf']=bin2hex(random_bytes(32));
    $fresh=null;$notice='';
    $catalog=json_decode(file_get_contents(__DIR__.'/catalog.json'),true,512,JSON_THROW_ON_ERROR);
    $names=array_merge(array_column($catalog['tools'],'name'),array_column(Reports::tools(),'name'));
    if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
        if (!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['htmcp_csrf'],$_POST['csrf'])) {echo '<p>Security token rejected. Reload this page.</p>';return;}
        try {
            $action=$_POST['operation']??'';
            if ($action==='create') {
                $scopes=['read'];if (isset($_POST['write']))$scopes[]='write';if (isset($_POST['write'],$_POST['instant']))$scopes[]='instant';
                $tools=array_values(array_filter(array_map('trim',explode(',',(string)($_POST['tools']??'')))));
                if (array_diff($tools,$names)||count($tools)>250)throw new RuntimeException('Unknown tool allowlist entry.');
                $fresh=Store::createToken(trim((string)($_POST['label']??'')),$scopes,$tools,(int)($_POST['days']??30));$notice='Token created. Copy it once; it cannot be recovered.';
            } elseif ($action==='instant_on'||$action==='instant_off') {
                $notice=Store::setInstant((string)($_POST['id']??''),$action==='instant_on')?($action==='instant_on'?'Ticket writes from this token now run immediately.':'Ticket writes from this token need approval again.'):'Token not found or not write-scoped.';
            } elseif ($action==='revoke') {
                Capsule::table('mod_htmcp_tokens')->where('id',(string)($_POST['id']??''))->update(['revoked'=>true]);$notice='Token revoked.';
            } elseif ($action==='approve'||$action==='reject') {
                $updated=Capsule::table('mod_htmcp_approvals')->where('id',(string)($_POST['id']??''))->where('state','pending')->where('expires','>',date('Y-m-d H:i:s'))->update(['state'=>$action==='approve'?'approved':'rejected','approved_by'=>$adminId]);
                $notice=$updated?'Request decision saved.':'Request expired or already decided.';
            }
        } catch(Throwable $e) {$notice='Request failed. Check form inputs and permissions.';}
    }
    $system=\WHMCS\Config\Setting::getValue('SystemURL');$endpoint=rtrim($system,'/').'/modules/addons/heaventree_mcp/endpoint.php';$csrf=htmcp_escape($_SESSION['htmcp_csrf']);
    echo '<h2>Heaventree WHMCS MCP</h2><p>Read-first, MIT-licensed, self-hosted. Writes require approval here, except ticket writes from tokens marked for instant ticket writes.</p><p>Endpoint: <code>'.htmcp_escape($endpoint).'</code></p>';
    if($notice)echo '<div class="alert alert-info">'.htmcp_escape($notice).'</div>';
    if($fresh)echo '<div class="alert alert-warning"><strong>Copy once; keep private:</strong><br><code>'.htmcp_escape($fresh['token']).'</code></div>';
    echo '<h3>Create token</h3><form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="operation" value="create"><label>Name <input name="label" maxlength="100" required></label> <label>Expires in days <input type="number" name="days" min="1" max="365" value="30"></label><p><label><input type="checkbox" name="write"> Allow write previews and approved execution</label></p><p><label><input type="checkbox" name="instant"> Run ticket replies, notes, ticket updates and sender blocks immediately, without approval (needs write)</label></p><label>Optional exact tool allowlist, comma separated <input name="tools" size="100"></label><p><button class="btn btn-primary">Create token</button></p></form>';
    echo '<h3>Tokens</h3><table class="table"><thead><tr><th>Name</th><th>Scopes</th><th>Expires</th><th>Instant ticket writes</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    foreach(Capsule::table('mod_htmcp_tokens')->orderBy('expires','desc')->limit(200)->get() as $row)echo '<tr><td>'.htmcp_escape($row->label).'</td><td>'.htmcp_escape($row->scopes).'</td><td>'.htmcp_escape($row->expires).'</td><td>'.(in_array('instant',(array)json_decode($row->scopes,true),true)?'Yes':'No').'</td><td>'.($row->revoked?'Revoked':'Active').'</td><td><form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="id" value="'.htmcp_escape($row->id).'"><button name="operation" value="revoke">Revoke</button> '.(in_array('write',(array)json_decode($row->scopes,true),true)?(in_array('instant',(array)json_decode($row->scopes,true),true)?'<button name="operation" value="instant_off">Require approval for tickets</button>':'<button name="operation" value="instant_on">Allow instant ticket writes</button>'):'').'</form></td></tr>';
    echo '</tbody></table><h3>Pending write requests — inspect before approving</h3>';
    foreach(Capsule::table('mod_htmcp_approvals')->where('state','pending')->where('expires','>',date('Y-m-d H:i:s'))->orderBy('expires','desc')->limit(100)->get() as $row) {
        try{$args=json_decode(decrypt($row->encrypted_args),true,512,JSON_THROW_ON_ERROR);$preview=json_encode(Server::redact($args),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);}catch(Throwable $e){$preview='Cannot decrypt; reject this request.';}
        echo '<div class="panel panel-default"><div class="panel-heading">'.htmcp_escape($row->tool).' — expires '.htmcp_escape($row->expires).'</div><pre>'.htmcp_escape($preview).'</pre><form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="id" value="'.htmcp_escape($row->id).'"><button name="operation" value="approve" class="btn btn-warning">Approve exact request</button> <button name="operation" value="reject" class="btn btn-default">Reject</button></form></div>';
    }
    echo '<h3>Audit (latest 100)</h3><p>Payloads, bearer tokens, customer content and credentials are never logged here.</p><table class="table"><tr><th>Time</th><th>Method</th><th>Tool</th><th>Outcome</th></tr>';
    foreach(Capsule::table('mod_htmcp_audit')->orderBy('id','desc')->limit(100)->get() as $row)echo '<tr><td>'.htmcp_escape($row->created).'</td><td>'.htmcp_escape($row->method).'</td><td>'.htmcp_escape($row->tool).'</td><td>'.htmcp_escape($row->outcome).'</td></tr>';
    echo '</table><p>Maxer: registrar tools use the existing configured WHMCS registrar module. Live expiry/payment verification requires a reviewed provider mapping; no Maxer API endpoints are guessed.</p>';
}
