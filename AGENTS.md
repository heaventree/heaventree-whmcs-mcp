# Repository checks

Scope is this original MIT addon only. Never copy encrypted/proprietary plugin code or publish credentials, customer data, private logs or live WHMCS config. Do not deploy to production as part of code publication. Native local API operations must be allowlisted; writes need exact admin-approved single-use requests, with one owner-approved exception (28 Oct 2026): a token an administrator marks for instant ticket writes may run only `Server::INSTANT_TOOLS` (ticket reply, note, update, block sender) without a per-request approval, so the hub and WHMCS mirror each other. Never widen that list to billing, domain, delete or merge tools. Preserve read-first defaults and source coverage limitations.

Required checks before release/push:

- PHP syntax-check every `modules/**/*.php` file.
- `php tests/run.php`.
- `npm ci` then `npm test` with `PHP_BINARY` pointing to PHP8.1+.
- Build a fresh immutable ZIP via `scripts/package.ps1`; verify portable paths, expected addon root, MIT license and no private/vendor payloads.
- Compare feature claims with `docs/PARITY.md`; synthetic tests are not WHMCS/Maxer production acceptance.

Keep tests synthetic. PHP fixtures must never appear under modules or in install ZIPs. No force pushes or hook bypasses. Generated catalog changes require reviewing official input metadata and explicitly classifying reads; unknown actions default write.
