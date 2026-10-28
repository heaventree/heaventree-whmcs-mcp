<?php
declare(strict_types=1);
// Tells a mirror (for example Heaventree Control) which ticket changed so it can refresh at once.
// Sends only the ticket id and event name, signed with the configured secret. Events are collected during the
// request and delivered once at shutdown, after the response has gone to the browser where PHP-FPM allows it.
use WHMCS\Database\Capsule;
if (!defined('WHMCS')) die('Direct access denied.');

function htmcp_notify_ticket(string $event, $ticketId): void
{
    static $queue = null;
    $id = (int) $ticketId;
    if ($id < 1) return;
    if ($queue === null) {
        $queue = [];
        register_shutdown_function(function () use (&$queue) {
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            foreach ($queue as $item) htmcp_deliver_ticket_event($item[0], $item[1]);
        });
    }
    $queue[$event.':'.$id] = [$event, $id];
}

function htmcp_deliver_ticket_event(string $event, int $id): void
{
    try {
        $settings = Capsule::table('tbladdonmodules')->where('module', 'heaventree_mcp')->pluck('value', 'setting')->all();
        $url = trim((string) ($settings['notify_url'] ?? ''));
        $secret = (string) ($settings['notify_secret'] ?? '');
        if ($secret === '' || !preg_match('#^https://[^\s]+$#D', $url)) return;
        $body = json_encode(['event' => $event, 'ticketid' => $id, 'sent' => time()], JSON_THROW_ON_ERROR);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_WRITEFUNCTION => fn ($handle, string $chunk): int => strlen($chunk), // discard the reply
            CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Htmcp-Signature: sha256='.hash_hmac('sha256', $body, $secret)],
        ]);
        curl_exec($curl);
        curl_close($curl);
    } catch (Throwable $e) {
        // A mirror being down must never break ticket handling in WHMCS; it catches up on its next sync.
    }
}

foreach (['TicketOpen', 'TicketOpenAdmin', 'TicketUserReply', 'TicketAdminReply', 'TicketAddNote', 'TicketStatusChange', 'TicketClose',
    'TicketDepartmentChange', 'TicketPriorityChange', 'TicketSubjectChange', 'TicketFlagged', 'TicketDelete'] as $event) {
    add_hook($event, 1, function (array $vars) use ($event) {
        htmcp_notify_ticket($event, $vars['ticketid'] ?? $vars['ticketId'] ?? 0);
    });
}
