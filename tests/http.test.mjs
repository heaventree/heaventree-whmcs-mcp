import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtempSync, cpSync, copyFileSync, mkdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { createServer } from 'node:net';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
const root=resolve(import.meta.dirname,'..');const temp=mkdtempSync(join(tmpdir(),'htmcp-test-'));
mkdirSync(join(temp,'tests'));cpSync(join(root,'modules'),join(temp,'modules'),{recursive:true});copyFileSync(join(root,'tests/whmcs-init.php'),join(temp,'init.php'));copyFileSync(join(root,'tests/http-router.php'),join(temp,'tests/http-router.php'));
const portServer=createServer();await new Promise(r=>portServer.listen(0,'127.0.0.1',r));const port=portServer.address().port;await new Promise(r=>portServer.close(r));
const php=spawn(process.env.PHP_BINARY??'php',['-S',`127.0.0.1:${port}`,join(temp,'tests/http-router.php')],{cwd:temp,stdio:['ignore','ignore','pipe'],windowsHide:true});let stderr='';php.stderr.on('data',chunk=>{stderr+=chunk.toString();});
const base=`http://127.0.0.1:${port}`;const headers={Authorization:'Bearer htmcp_'+'a'.repeat(64),Accept:'application/json, text/event-stream','Content-Type':'application/json'};
for(let n=0;n<50;n++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}if(n===49)throw new Error('Fixture startup failed: '+stderr.slice(-300));}
after(async()=>{php.kill();await new Promise(r=>php.once('exit',r));const target=resolve(temp);assert.ok(target.startsWith(resolve(tmpdir())+requireSeparator())&&target.includes('htmcp-test-'));rmSync(target,{recursive:true,force:true});});
function requireSeparator(){return process.platform==='win32'?'\\':'/';}
const post=(body,extra={})=>fetch(base,{method:'POST',headers:{...headers,...extra},body:typeof body==='string'?body:JSON.stringify(body)});
test('real PHP endpoint negotiates native MCP JSON',async()=>{const response=await post({jsonrpc:'2.0',id:1,method:'initialize',params:{protocolVersion:'2025-06-18',capabilities:{},clientInfo:{name:'fixture',version:'1'}}});assert.equal(response.status,200);assert.equal((await response.json()).result.protocolVersion,'2025-06-18');assert.match(response.headers.get('content-type'),/application\/json/);});
test('authorization, HTTPS, Origin and Accept guards',async()=>{assert.equal((await post({},{Authorization:'Bearer invalid'})).status,401);assert.equal((await post({},{Origin:'https://evil.example'})).status,403);assert.equal((await post({},{Accept:'application/json'})).status,406);assert.equal((await fetch(base+'/plaintext',{method:'POST',headers,body:'{}'})).status,426);assert.equal((await fetch(base,{headers})).status,405);});
test('strict JSON, protocol and body size guards',async()=>{assert.equal((await post('not json')).status,400);assert.equal((await post('[]')).status,400);assert.equal((await post(' '.repeat(65537))).status,413);assert.equal((await post({},{'MCP-Protocol-Version':'unsupported'})).status,400);});
test('notifications accepted without body',async()=>{const reply=await post({jsonrpc:'2.0',method:'notifications/initialized'});assert.equal(reply.status,202);assert.equal(await reply.text(),'');});
test('read-only token catalog paginates and denies provider writes',async()=>{let cursor;const names=[];do{const reply=await post({jsonrpc:'2.0',id:2,method:'tools/list',params:cursor?{cursor}:{}});const rpc=await reply.json();assert.ok(rpc.result,JSON.stringify(rpc));names.push(...rpc.result.tools.map(t=>t.name));cursor=rpc.result.nextCursor;}while(cursor);assert.equal(names.length,69);assert.ok(!names.includes('whmcs_domain_renew'));const response=await post({jsonrpc:'2.0',id:3,method:'tools/call',params:{name:'whmcs_domain_renew',arguments:{domainid:1}}});assert.equal((await response.json()).result.isError,true);});
test('read invokes WHMCS adapter and redacts secrets',async()=>{const response=await post({jsonrpc:'2.0',id:4,method:'tools/call',params:{name:'whmcs_get_clients',arguments:{limitnum:2}}});const reply=await response.json();assert.equal(reply.result.structuredContent.action,'GetClients');assert.equal(reply.result.structuredContent.password,'[REDACTED]');assert.equal(reply.result.isError,false);});
test('supported MCP protocol header remains valid HTTP',async()=>{const response=await post({jsonrpc:'2.0',id:5,method:'tools/list',params:{}},{'MCP-Protocol-Version':'2025-06-18'});assert.equal(response.status,200);assert.equal((await response.json()).result.tools.length,50);});
test('official MCP SDK completes discovery, tool call, prompts and resources',async()=>{
  const client=new Client({name:'independent-sdk-fixture',version:'1'},{capabilities:{}});
  const transport=new StreamableHTTPClientTransport(new URL(base),{requestInit:{headers:{Authorization:headers.Authorization}},fetch:async(url,init)=>{
    const response=await fetch(url,init);
    return response;
  }});
  try {
    await client.connect(transport);
    const tools=await client.listTools();assert.equal(tools.tools.length,50);assert.ok(tools.nextCursor);
    const called=await client.callTool({name:'whmcs_get_clients',arguments:{limitnum:1}});assert.equal(called.isError,false);
    assert.equal((await client.listPrompts()).prompts.length,4);
    assert.equal((await client.getPrompt({name:'cashflow_review'})).messages.length,1);
    assert.equal((await client.listResources()).resources.length,1);
    const capabilities=await client.readResource({uri:'whmcs://capabilities'});assert.equal(JSON.parse(capabilities.contents[0].text).registrarLiveExpiryVerified,false);
  } finally {await client.close();}
});
