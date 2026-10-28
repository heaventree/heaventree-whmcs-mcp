# Heaventree WHMCS MCP

Original, MIT-licensed WHMCS addon exposing documented local API tools, operational BI reports and guarded administration over Model Context Protocol. Upload one addon folder to WHMCS; no Docker, remote database login or WHMCS remote API IP allowlist.

**Release candidate 0.1.0.** Local protocol/security tests are provided. Production WHMCS staging acceptance and Maxer provider verification are separate gates. Exact parity with the encrypted paid addon is not yet verified; see [coverage](docs/PARITY.md). The existing commercial addon and JOSH bridges are not modified by this build.

- [Install](docs/INSTALL.md)
- [Usage and MCP clients](docs/USAGE.md)
- [API tool catalog](docs/API-TOOLS.md)
- [Security](SECURITY.md)
- [Staging acceptance](docs/ACCEPTANCE.md)
- [Maxer code review](docs/MAXER-REVIEW.md)
- [Feature coverage](docs/PARITY.md)

Default access is read-only. Write execution requires a write-scoped token, the addon write switch, and a five-minute administrator-approved request bound to the exact tool, parameters and token. Approvals are single-use; unknown execution outcomes require independent verification, not automatic retries. An administrator can mark one token for instant ticket writes (reply, note, update, block sender only), and can set a signed webhook that tells a mirror which ticket changed; see `docs/USAGE.md`.

Business data remains on the WHMCS server until an authorized MCP client reads it. That client's model provider may receive returned data: self-hosting the addon does **not** guarantee data stays on-server when using a cloud AI client. Responses redact credential-like fields; token/audit tables do not store raw customer response bodies. Deployers must handle customer data lawfully and restrict access appropriately.

## Development

PHP8.1+; no runtime Composer dependencies. Run `php tests/run.php`, `node --test tests/bridge.test.mjs`, and syntax-check every PHP file. Build an immutable ZIP with `pwsh -NoProfile -File scripts/package.ps1`. ZIP entries use `/` separators and include no secrets or vendor code.

Catalog regeneration: `node scripts/generate-catalog.mjs /PATH/TO/OFFICIAL/WHMCS/developer-docs`. The initial catalog uses documentation commit `2e75f454aa248b791948e14ad7b33fa9f0ded761`. Regeneration is a reviewed build step, not runtime endpoint discovery; newly added operations default to write scope unless explicitly classified read-only.

## License and independence

Copyright2026 Heaventree. All original code and documentation in this repository are MIT-licensed. WHMCS is separately licensed and not included. API names and parameter types are interoperability facts from WHMCS's official documentation. No paid plugin source, ionCube decoder, license bypass, copied assets, production database or credential is distributed.
