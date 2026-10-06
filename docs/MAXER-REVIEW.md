# Maxer registrar review

This is a review handoff, not a claim that a new Maxer API is integrated. No proprietary Maxer endpoint, access credential or renewal automation is invented.

Existing WHMCS registrar integration is accessible through documented local API tools: DomainGetNameservers, DomainGetLockingStatus, DomainGetWhoisInfo, DomainWhois, and administrator-approved DomainRenew/DomainRegister/DomainTransfer. WHMCS routes supported operations to the domain's configured registrar module. Whether the installed Maxer module implements each function must be tested with Maxer.

Review the original code in `modules/addons/heaventree_mcp/lib/Server.php`, `Reports.php`, `Store.php`, the catalog, and endpoint. We need Maxer to confirm:

- Supported registrar account authentication and customer/account scoping.
- Domain identifier mapping and pagination.
- Live registry/registrar expiry, renewal deadline, grace/redemption status and timezone semantics.
- Auto-renew setting, whether a renewal is actually queued/paid, renewal cost and currency.
- Read-only account/domain status calls, provider rate limits, retry rules and request correlation IDs.
- Renewal mutation idempotency, independent verification and failure/unknown-outcome behavior.
- Which fields can be shared with the customer's assistant, without secrets or other customers' data.

Target evidence envelope: `source`, `observedAt`, `providerDomainId`, `domain`, `expiryAt`, `renewalDeadlineAt`, `autoRenew`, `renewalState`, `currency`, `renewalCost`, `coverage`, `verificationStatus`. Unknown fields stay null/unknown, never silently filled from WHMCS dates. Provider IDs are operational data, not authentication.

For now `whmcs_bi_domain_renewals` returns WHMCS dates with `registrarLiveExpiryVerified=false` and `renewalPaymentVerified=false`. Installing this addon alone must not turn those markers green. A future reviewed Maxer adapter must be independent of the commercial MCP plugin and explicitly authorized for read-only access first.
