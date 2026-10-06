# Build verification — 6 October 2026

Original source at D:\heaventree-whmcs-mcp. No paid PHP implementation/ionCube assets were copied. Catalog generated from official WHMCS/developer-docs commit2e75f454aa248b791948e14ad7b33fa9f0ded761:159API operations (59read/100write) plus10original BI tools.

Local PHP8.3.35 syntax checks pass. PHP unit/security suite:198checks pass with synthetic data; every catalog operation routes to the expected allowlisted dispatcher. HTTP and stdio guard/read tests pass. The official MCP SDK round-trip test detects invalid HTTP chunk framing on this machine when a valid MCP-Protocol-Version header is present.

Independent reproduction: a tiny Node HTTP server sending only `{}` with Content-Length2 works without that header; the same server/request with MCP-Protocol-Version2025-06-18 receives Transfer-Encodingchunked without matching chunk framing. No WHMCS/addon/SDK code is required to reproduce it. This establishes a machine/network-path problem but does not establish which installed filter causes it. Antivirus/firewall/TLS checks were not disabled. These failing tests remain enabled; they are not skipped to claim green.

The independent SDK development dependency was upgraded to1.32.1 after npm audit identified an OAuth advisory in1.30.0. Current audit:0vulnerabilities. The deployed PHP addon and dependency-free stdio bridge do not ship this development SDK.

GitHub CI runs PHP8.1/8.2/8.3/8.4 on Linux and Windows, including the official SDK native-tool/prompt/resource round-trip, and is the independent clean-machine check. Its final run/result is recorded in the release receipt after completion. Green synthetic checks are not live WHMCS SQL/API validation, Maxer integration verification or proof of exact encrypted-vendor parity; see ACCEPTANCE.md and PARITY.md.
