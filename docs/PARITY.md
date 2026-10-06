# Feature coverage and release boundaries

Independent implementation from documented behavior and official WHMCS local API identifiers/types. The supplied commercial v1.1.0 archive contains ionCube-encrypted PHP. Its internals were not decoded, copied, licensed as MIT or included. The unencrypted installation readme establishes broad transport/security intent but is not an exhaustive runtime tools/list. Exact one-for-one parity with that encrypted release is therefore **not verified**.

| Area | v0.1 implementation | Qualification |
|---|---|---|
| Clients, contacts, users, services, orders, products, addons, tickets, billing, domains, projects, affiliates, administrator reads | Dedicated tools for documented local API operations | See complete generated catalog; WHMCS version/module permissions can reject unsupported operations |
| Charges, updates, renewal, provisioning, messages | Preview plus admin-approved exact one-use execution | OFF by default; never live-tested against production |
| MRR, revenue, top clients, invoice aging, zero invoices, billing gaps, plan audit, renewal review, service exits, ledger fees | 10 original BI tools | Accounting and coverage qualifications in USAGE.md |
| Bearer tokens, expiry, revocation, per-tool allowlist, scoped reads/writes | Original module-owned hashed tokens | No commercial license dependency |
| Rate limit, audit, dashboard | Per-token transaction-bound limiter; payload-free audit; admin token/approval views | Invalid unauthenticated traffic needs hosting-level abuse limits; audit retention is operator-managed |
| Prompts, resources, HTTP and local stdio | Four prompts, capabilities resource, stateless JSON HTTP, dependency-free stdio bridge | Legacy SSE intentionally not implemented; GET returns405 |
| Secrets/password retrieval and login validation | GetClientPassword, DecryptPassword, ValidateLogin excluded | No password dumping/login bypass; sensitive response fields redacted |
| Custom database operations absent from official API | Not fabricated or silently emulated | No arbitrary SQL, admin credential-reset engine, arbitrary report PHP execution or undocumented custom-option CRUD |
| Maxer live expiry/payment verification | Review specification and existing registrar API route | Awaiting reviewed provider contract; not integrated yet |

Public commercial descriptions refer to features beyond the documented local API. A working licensed instance's sanitized tools/list and workflow list are needed to prove exact parity. Do not advertise this release as a verified drop-in replacement for every commercial tool. This is an installable, independently licensed release candidate with explicit scope and staging acceptance steps.
