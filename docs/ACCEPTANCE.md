# Acceptance and verification

Local checks use synthetic data and fake WHMCS adapters. They verify MCP wire behavior, routing for every catalog operation, input guards, token scoping, output redaction, write preview/approval binding, replay rejection, revocation, rate limits and money normalization. They **do not** verify a real WHMCS installation, every billing action's semantics, a MySQL concurrency stress test or Maxer live data.

Before production use, test on a staging WHMCS copy:

1. Activate addon; verify only the three `mod_htmcp_*` tables were created.
2. Create narrow read token; initialize and paginate tools/list. No write tools should be present.
3. Read clients, invoices, services, domains and tickets with small page sizes. Compare IDs/statuses/totals to WHMCS admin. Returned password/credential fields must be redacted.
4. Revoke the token and independently confirm401; confirm expiration and rate limit429.
5. Compare MRR with manual service normalization per currency. Verify annual/free/one-time exclusions and >10000 partial marker; do not compare MRR directly to actual payments.
6. For January2026 cashflow review, inspect billing-gap, zero-invoice and exact-plan audit candidates. Reconcile legitimate credits, annual plans, discounts, internal services and deferrals before marking errors.
7. For renewals, compare WHMCS records with a separate registrar read. Live registrar/payment verification must stay false until independently verified.
8. With writes OFF, create a write-capable token on staging and preview a harmless approved change. No customer/provider mutation should occur; execution must fail with switch OFF.
9. Enable writes only in staging. Approve one precise harmless request; execute once, check WHMCS directly, then prove duplicate/changed-argument/different-token/expired requests are rejected. Test localAPI permission denials. Never use real charges or production domains for this exercise.
10. Test timeout/exception/unknown outcomes and record manual reconciliation steps. Revoke staging tokens before copying configuration or publishing logs.

## Read-only evaluation scenarios

Use a fixed, anonymized staging snapshot. Determine answers independently and keep expected values outside production data. Ten acceptance questions: active annual-service normalized MRR per currency; active clients without an invoice since a fixed start; legitimate credited zero-total invoice count; unmatched exact plan names; top collected-revenue client by currency in a fixed window; oldest overdue unpaid invoice; cancelled service exits in a fixed window; registrar-unverified expiring domain count; recorded fee total by currency; active recurring services intentionally excluded from invoice generation. Do not call mutation tools to answer these.

## Publication gates

An installable ZIP and green synthetic tests are a release candidate, not a production-readiness or encrypted-vendor parity claim. Maxer review and staged WHMCS comparison must be recorded separately. Do not silently replace JOSH's active connector or deploy to the live WHMCS server as part of packaging.
