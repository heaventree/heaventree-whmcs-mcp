# Usage

## Direct HTTP MCP

Endpoint: `https://YOUR-WHMCS/modules/addons/heaventree_mcp/endpoint.php`.

Store the bearer token in your client's secret manager. Send headers `Authorization: Bearer ...`, `Content-Type: application/json`, `Accept: application/json, text/event-stream`. Initialize with protocol `2025-06-18`, then send `MCP-Protocol-Version: 2025-06-18`. No session IDs, cookies, API key query parameters or SSE are needed. The server supports pagination for tools/list; keep following nextCursor.

```json
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"my-assistant","version":"1"}}}
```

```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"whmcs_get_clients","arguments":{"limitnum":50,"limitstart":0,"sorting":"DESC"}}}
```

```json
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"whmcs_bi_billing_gaps","arguments":{"since":"2026-01-01","as_of":"2026-10-06","limit":50}}}
```

```json
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"whmcs_bi_domain_renewals","arguments":{"as_of":"2026-10-06","days":30,"limit":50}}}
```

Tools and schemas are listed in [API-TOOLS.md](API-TOOLS.md). The tools are WHMCS operations, not generic SQL or shell access. Tool error results contain `isError: true`; HTTP 200 does not imply the operation succeeded. Current v0.1 reports currency amounts with two decimals; no currency conversion is performed.

## Local stdio client

Run `node clients/stdio-bridge.mjs` with `WHMCS_MCP_URL` and `WHMCS_MCP_TOKEN` supplied through the client's protected environment settings. Do not commit tokens or paste them into shared config files. The bridge supports Node 20+, has no npm dependencies, refuses non-HTTPS URLs and redirects, bounds responses and times out after 30 seconds. It does not retry writes.

Example structure, with secrets configured separately:

```json
{"mcpServers":{"heaventree-whmcs":{"command":"node","args":["/ABSOLUTE/PATH/clients/stdio-bridge.mjs"]}}}
```

## Approved writes

1. A write-capable token calls a write tool with its normal parameters. `dry_run` defaults to true. This creates an encrypted, token-bound preview and returns an approvalId. It does not modify billing/client/domain data.
2. An administrator reviews that exact request in the addon dashboard and approves or rejects it. Approvals expire after five minutes.
3. Execution calls the **same tool with identical arguments**, plus `dry_run: false` and `approval_id`. The addon-wide write switch must also be ON.
4. The approval is atomically consumed before the local API call. Different arguments, a different token, expiry or replay cannot reuse it. If a call times out or throws after consumption, independently inspect WHMCS before preparing a new request: execution outcome may be unknown.

## Instant ticket writes

An administrator can mark a write-scoped token for instant ticket writes, either when creating it or later with **Allow instant ticket writes** in the token list. That token then runs `whmcs_add_ticket_reply`, `whmcs_add_ticket_note`, `whmcs_update_ticket` and `whmcs_block_ticket_sender` (without `delete`) straight away with `dry_run: false` and no `approval_id`. Every other write, including blocking a sender with `delete: true`, ticket deletes, merges, billing and domains, still needs an approved request. The addon-wide write switch still applies, and every call is still audited. **Require approval for tickets** switches it back off.

## Ticket change notifications

Set **Ticket change webhook URL** (HTTPS) and **Ticket webhook signing secret** in the addon settings to have WHMCS tell a mirror whenever a ticket is opened, replied to, noted, flagged, deleted, or has its status, department, priority or subject changed. The POST body is `{"event":"TicketUserReply","ticketid":123,"sent":1730000000}` with header `X-Htmcp-Signature: sha256=<HMAC-SHA256 of the body>`. No ticket content is sent; the mirror reads the ticket through this MCP endpoint. Events are collected during the request and sent once at shutdown, after the response has been flushed to the browser where PHP-FPM allows it (otherwise the request can finish up to 3 seconds later). Delivery is best effort with a 3-second timeout, and a failed delivery never interrupts ticket handling, so mirrors should keep a periodic sync as a fallback.

Examples of preview-only tasks: prepare a client plan update; prepare a support reply; prepare a domain renewal. These are materially different risks. Never treat an expired WHMCS record alone as authorization to renew a domain or charge a card.

## BI interpretation

MRR covers active recurring **hosting services** only, excluding domains/addons/free/one-time services. It is expected revenue, not payments received. Service snapshots are capped at 10,000 and disclose partial coverage. Revenue/top clients/cost breakdown summarize recorded ledger amounts and fees, not provider settlement or full hosting cost accounting. Historical changes in client currency require reconciliation. Churn lists recorded service exits; it does not invent a cohort denominator. Billing gaps and zero invoices are review candidates, not automatically errors. Plan audit uses exact plan names and needs owner classification of discounts/legacy/internal accounts.
