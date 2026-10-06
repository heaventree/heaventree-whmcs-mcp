// Mechanical extraction of API identifiers/types from the official WHMCS docs.
// No vendor implementation or WHMCS prose is copied into the distribution.
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
const root = process.argv[2];
if (!root) throw new Error('Usage: node scripts/generate-catalog.mjs OFFICIAL_DOCS_CHECKOUT');
const readActions = new Set('GetOrders GetOrderStatuses GetProducts GetPromotions GetCredits GetInvoice GetInvoices GetPayMethods GetQuotes GetTransactions GetModuleConfigurationParameters GetModuleQueue GetAnnouncements GetActivityLog GetAdminDetails GetAdminUsers GetAutomationLog GetConfigurationValue GetCurrencies GetEmailTemplates GetPaymentMethods GetStaffOnline GetStats GetToDoItems GetToDoItemStatuses WhmcsDetails GetCancelledPackages GetClientGroups GetClients GetClientsAddons GetClientsDetails GetClientsDomains GetClientsProducts GetContacts GetEmails GetProject GetProjects GetPermissionsList GetUserPermissions GetUsers GetAffiliates ListOAuthCredentials DomainGetLockingStatus DomainGetNameservers DomainGetWhoisInfo DomainWhois GetRegistrars GetTLDPricing GetHealthStatus GetServers GetSupportDepartments GetSupportStatuses GetTicket GetTicketAttachment GetTicketCounts GetTicketNotes GetTicketPredefinedCats GetTicketPredefinedReplies GetTickets'.split(' '));
const excluded = new Set(['DecryptPassword', 'GetClientPassword', 'ValidateLogin']);
const tools = [];
for (const name of readdirSync(join(root, 'api-reference')).filter(n => n.endsWith('.md')).sort()) {
  const text = readFileSync(join(root, 'api-reference', name), 'utf8');
  const action = text.match(/^title\s*=\s*"([A-Za-z0-9]+)"/m)?.[1];
  if (!action || excluded.has(action)) continue;
  const table = text.split(/### Request Parameters/i)[1]?.split(/### /)[0] ?? '';
  const properties = {}, required = [];
  for (const line of table.split('\n')) {
    const cells = line.split('|').slice(1, -1).map(c => c.trim());
    if (cells.length < 4 || !/^[a-zA-Z][a-zA-Z0-9_]*$/.test(cells[0]) || ['action', 'Parameter'].includes(cells[0])) continue;
    const [key, type, , requirement] = cells;
    const schema = /bool/i.test(type) ? { type: 'boolean' } : /int/i.test(type) ? { type: 'integer' } : /float|double/i.test(type) ? { type: 'number' } : /array/i.test(type) ? { type: 'array', items: {} } : { type: 'string', maxLength: 16384 };
    if (key === 'limitnum') Object.assign(schema, { type: 'integer', minimum: 1, maximum: 200, default: 50 });
    if (key === 'limitstart') Object.assign(schema, { type: 'integer', minimum: 0, maximum: 1000000, default: 0 });
    properties[key] = schema;
    if (/^Required$/i.test(requirement)) required.push(key);
  }
  const read = readActions.has(action);
  if (action === 'GetConfigurationValue') properties.setting.enum = ['SystemURL', 'CompanyName', 'Version', 'Template', 'Language'];
  if (!read) { properties.dry_run = { type: 'boolean', default: true }; properties.approval_id = { type: 'string', pattern: '^[a-f0-9]{32}$' }; }
  const toolName = 'whmcs_' + action.replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase();
  tools.push({ name: toolName, action, scope: read ? 'read' : 'write', description: `${action}: invoke the documented WHMCS local API operation. ${read ? 'Read-only; returned fields may contain personal business data.' : 'Defaults to preview; execution requires an administrator-approved, token-bound one-use approval.'}`, inputSchema: { type: 'object', properties, required, additionalProperties: false }, annotations: { readOnlyHint: read, destructiveHint: !read, idempotentHint: read, openWorldHint: true }, documentation: `https://developers.whmcs.com/api-reference/${action.toLowerCase()}/` });
}
if (tools.length < 140 || !tools.some(t => t.action === 'DomainRenew')) throw new Error('Incomplete official catalog');
writeFileSync('modules/addons/heaventree_mcp/catalog.json', JSON.stringify({ source: 'WHMCS/developer-docs', sourceCommit: '2e75f454aa248b791948e14ad7b33fa9f0ded761', tools }, null, 2) + '\n');
writeFileSync('docs/API-TOOLS.md', '# WHMCS API tools\n\nIdentifiers and input types derived from the official WHMCS documentation; implementations are original.\n\n| Tool | Action | Scope |\n|---|---|---|\n' + tools.map(t => `| ${t.name} | ${t.action} | ${t.scope} |`).join('\n') + '\n');
console.log(JSON.stringify({ tools: tools.length, read: tools.filter(t => t.scope === 'read').length, write: tools.filter(t => t.scope === 'write').length }));
