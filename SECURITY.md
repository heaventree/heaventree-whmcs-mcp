# Security policy

Report issues privately to the repository maintainer. Do not attach live tokens, configuration.php, payment data, raw customer messages or database dumps to issues. Public release should configure GitHub private vulnerability reporting.

HTTPS is mandatory. Validate Origin against configured WHMCS SystemURL. Tokens are random256-bit bearers stored only as SHA-256 hashes, expiring within365days and revocable. Tokens authorize read/write scopes and optional exact tool lists. No browser cookies or WHMCS admin passwords authenticate the MCP endpoint. Backend localAPI uses an explicitly configured existing enabled administrator; its WHMCS role permissions remain relevant.

Reads may contain personal customer data. Cloud model use is an external disclosure decision. Never grant broad read tokens to untrusted clients. Secret-like fields are redacted recursively, but free text can contain manually pasted secrets; no generic field-based redactor can guarantee otherwise. Use narrow tool allowlists and avoid tools reading free text if that risk is unacceptable.

Writes default to preview; require module-wide enablement and admin-reviewed encrypted approval. Five-minute token/tool/argument binding, atomic one-use consumption and no automatic retries protect replay and accidental broad action. Preview storage includes encrypted write arguments; audit storage includes only token identifier, method, tool, outcome and timestamp. Logs omit tokens, raw payloads, addresses and IPs. Deactivation revokes tokens but preserves module-owned history. Operator-managed retention/deletion should cover encrypted previews and audit records.

No arbitrary SQL, shell, filesystem reading, API action injection, remote URLs in report arguments, payment-password retrieval or raw card fields. Output limit1MiB, input64KiB, tool pages50, data pages<=200, MRR source cap10000. Rate limit uses an atomic database transaction per token. Apply hosting/WAF limits to unauthenticated requests too. API operation timeouts/partial failures can occur; verify independently before retrying writes.

This release has not been production-approved by WHMCS or Maxer. Staging testing and least-privilege role review are mandatory before deployment. Transport is stateless bearer-authenticated JSON MCP, not an OAuth authorization server. Clients requiring OAuth must use a separately reviewed gateway; do not disable token checks.
