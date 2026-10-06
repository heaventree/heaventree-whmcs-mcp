<?php
declare(strict_types=1);
// Loopback fixture simulates TLS termination; not production code or ZIP content.
if (parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)!=='/plaintext') $_SERVER['HTTPS']='on';
require __DIR__.'/../modules/addons/heaventree_mcp/endpoint.php';
