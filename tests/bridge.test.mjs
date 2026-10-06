import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { resolve } from 'node:path';
async function rejected(env){const child=spawn(process.execPath,[resolve('clients/stdio-bridge.mjs')],{env:{PATH:process.env.PATH,...env},stdio:['pipe','pipe','pipe'],windowsHide:true});child.stdin.end();let out='';child.stdout.on('data',c=>out+=c);const code=await new Promise(r=>child.once('exit',r));assert.notEqual(code,0);assert.equal(out,'');}
test('stdio refuses plaintext, query-string credentials and missing token',async()=>{await rejected({WHMCS_MCP_URL:'http://example.test/mcp',WHMCS_MCP_TOKEN:'htmcp_'+'a'.repeat(64)});await rejected({WHMCS_MCP_URL:'https://example.test/mcp?api_key=bad',WHMCS_MCP_TOKEN:'htmcp_'+'a'.repeat(64)});await rejected({WHMCS_MCP_URL:'https://example.test/mcp'});});
