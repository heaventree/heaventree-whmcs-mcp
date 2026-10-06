// Dependency-free Node 20+ bridge. Token stays in environment, never URL/argv/logs.
import { createInterface } from 'node:readline';
const url = new URL(process.env.WHMCS_MCP_URL ?? '');
if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash) throw new Error('WHMCS_MCP_URL must be a plain HTTPS endpoint URL.');
const token = process.env.WHMCS_MCP_TOKEN;
if (!/^htmcp_[a-f0-9]{64}$/.test(token ?? '')) throw new Error('WHMCS_MCP_TOKEN required.');
let protocol = '2025-06-18';
const lines = createInterface({ input: process.stdin, crlfDelay: Infinity });
for await (const line of lines) {
  let request;
  try {
    if (Buffer.byteLength(line)>65536) throw new Error('Oversized input');
    request=JSON.parse(line);
    const response=await fetch(url, { method:'POST', redirect:'error', signal:AbortSignal.timeout(30000), headers:{ Authorization:`Bearer ${token}`, Accept:'application/json, text/event-stream', 'Content-Type':'application/json', 'MCP-Protocol-Version':protocol }, body:JSON.stringify(request) });
    if (response.status===202) continue;
    if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Upstream unavailable');
    const reader=response.body.getReader(); const chunks=[];let size=0;
    while(true){const part=await reader.read();if(part.done)break;size+=part.value.byteLength;if(size>1100000){await reader.cancel();throw new Error('Oversized response');}chunks.push(part.value);}
    const result=JSON.parse(Buffer.concat(chunks).toString('utf8'));
    if (request.method==='initialize'&&result.result?.protocolVersion)protocol=result.result.protocolVersion;
    process.stdout.write(JSON.stringify(result)+'\n');
  } catch {
    if (request && Object.hasOwn(request,'id')) process.stdout.write(JSON.stringify({jsonrpc:'2.0',id:request.id,error:{code:-32603,message:'WHMCS MCP connection failed. Check HTTPS URL, token, permissions and server health; do not automatically retry writes.'}})+'\n');
    else process.stderr.write('Invalid input or unavailable upstream.\n');
  }
}
