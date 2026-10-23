<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/websocket.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$lock = fopen(sys_get_temp_dir().'/imgtr-check-relays.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Relay check is already running.\n");
    exit(0);
}

/** Retrieve optional NIP-11 relay information without sending image data. */
function relay_information(string $relayUrl): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $parts = parse_url($relayUrl);
    if (!$parts || empty($parts['host'])) {
        return null;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? '')) === 'wss' ? 'https' : 'http';
    $port = isset($parts['port']) ? ':'.(int)$parts['port'] : '';
    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) ? '?'.$parts['query'] : '';
    $url = $scheme.'://'.$parts['host'].$port.$path.$query;

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Accept: application/nostr+json'],
        CURLOPT_USERAGENT => 'IMGTR-Relay-Health/2.0',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($body) || $status < 200 || $status >= 300 || strlen($body) > 1048576) {
        return null;
    }
    $information = json_decode($body, true);
    return is_array($information) ? $information : null;
}

/** Return false only when a relay explicitly declares it cannot accept IMGTR events. */
function relay_accepts_imgtr(?array $information): bool
{
    if ($information === null) {
        return true;
    }
    $limits = is_array($information['limitation'] ?? null) ? $information['limitation'] : [];
    $contentLength = (int)ceil(NOSTR_CHUNK_SIZE * 4 / 3);
    $messageLength = $contentLength + 2500;

    if (!empty($limits['auth_required']) || !empty($limits['payment_required']) || !empty($limits['restricted_writes'])) {
        return false;
    }
    if (isset($limits['max_content_length']) && (int)$limits['max_content_length'] < $contentLength) {
        return false;
    }
    if (isset($limits['max_message_length']) && (int)$limits['max_message_length'] < $messageLength) {
        return false;
    }
    return true;
}

$relays = db()->query('SELECT id,url FROM relays WHERE enabled=1')->fetchAll();
$update = db()->prepare('UPDATE relays SET status=?,latency=?,last_checked=UTC_TIMESTAMP() WHERE id=?');

foreach ($relays as $relay) {
    $started = microtime(true);
    $socket = relay_open((string)$relay['url'], 6);
    $latency = (int)round((microtime(true) - $started) * 1000);
    $status = 'offline';

    if (is_resource($socket)) {
        fclose($socket);
        $information = relay_information((string)$relay['url']);
        if (relay_accepts_imgtr($information)) {
            $status = $latency > 1800 ? 'slow' : 'healthy';
        }
    }

    $update->execute([$status, min($latency, 999999), (int)$relay['id']]);
    echo $relay['url'].' '.$status.' '.$latency."ms\n";
}

flock($lock, LOCK_UN);
fclose($lock);
