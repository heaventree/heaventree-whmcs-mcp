# Install Heaventree WHMCS MCP

Requires an existing licensed WHMCS installation, PHP 8.1–8.4 and HTTPS. This addon is original MIT code; WHMCS itself is not redistributed. No Composer, Node, Docker, remote database credentials, WHMCS remote API identifier/secret or WHMCS API IP allowlist is required on the hosting server.

1. Back up the WHMCS installation and database. Test on a staging copy first.
2. Extract the release ZIP. Upload **only** `modules/addons/heaventree_mcp` into the existing WHMCS `modules/addons` directory. The result must be `modules/addons/heaventree_mcp/heaventree_mcp.php`.
3. In WHMCS **System Settings > Addon Modules**, activate **Heaventree WHMCS MCP**. It creates three module-owned tables, not changes to client or billing tables.
4. Configure the username of an existing enabled WHMCS administrator. Use a dedicated least-privilege role with the required local API permissions. This is **not** a new remote API user/key and does not require an IP allowlist. No admin password is entered into this addon.
5. Leave **Enable approved writes** OFF. Grant addon access only to trusted administrator roles. Set a per-token request limit (default 60/minute).
6. Open **Addons > Heaventree WHMCS MCP**. Create a read-only token; optionally restrict it to named tools. Copy the token immediately into your client's protected secret store. Only its SHA-256 hash is stored by the addon.
7. Connect the client to `https://YOUR-WHMCS/modules/addons/heaventree_mcp/endpoint.php` with `Authorization: Bearer YOUR_TOKEN`. Never put tokens in the URL or Git.
8. Run the read-only checks in [ACCEPTANCE.md](ACCEPTANCE.md). Do not enable writes until those pass against staging and the configured role has been reviewed.

## Hosting configuration

PHP must see the connection as HTTPS. Do not trust arbitrary `X-Forwarded-Proto` from the internet; configure the trusted reverse proxy/web server to set PHP's HTTPS environment correctly. WHMCS SystemURL must use the exact public HTTPS origin. Browser Origin headers other than that origin are rejected. Non-browser MCP clients can omit Origin.

Some Apache/PHP configurations strip Authorization. Configure the vhost to preserve it (Apache example: `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`). Keep HTTPS and authentication checks enabled. Server/firewall rules may still restrict network reachability; the addon does not bypass the hosting firewall.

## Upgrade / rollback

Do not overwrite the old commercial addon or JOSH bridge. The new directory is `heaventree_mcp`, not `whmcs_mcp` or `josh_connector`. Replace only this addon's files for upgrades, preserve its tables, and rerun acceptance. To stop access, revoke tokens or deactivate this addon. Deactivation revokes every addon token and retains audit/approval data; it never deletes WHMCS business data. For rollback, restore the previous addon's files and create fresh tokens deliberately. Never reactivate old bearer tokens automatically.

## Troubleshooting

- 401: missing, expired or revoked token. Tokens cannot be recovered; create a fresh one.
- 403: browser Origin mismatch. Correct SystemURL/client deployment; do not disable Origin validation.
- 405 on GET: normal; this release uses stateless JSON Streamable HTTP, not SSE.
- 406: client must send `Accept: application/json, text/event-stream`.
- 426: PHP did not observe HTTPS. Correct the trusted server TLS setup.
- 429: per-token rate limit reached. Wait for the next minute; no automatic write retry.
- 503: check addon activation, configured enabled administrator, own table creation and WHMCS/PHP compatibility. Do not send configuration.php or credentials to support.

Optional local stdio bridge needs Node 20+ on the client only; see [USAGE.md](USAGE.md).
