<?php

namespace App\Support\NewsImport;

use RuntimeException;

/**
 * Outbound HTTP for the importer, guarded against SSRF.
 *
 * Only http(s) on ports 80/443, no credentials in the URL, and the host must
 * resolve to public addresses only (literal private/reserved IPs, IPv6-mapped
 * IPv4, numeric host forms, localhost and DNS names pointing inside are all
 * refused). Redirects are followed by hand and every hop is checked again, so
 * a public URL cannot bounce the request to an internal one. The body is
 * capped while it is being read instead of after the download.
 */
final class SafeHttp
{
    private const MAX_REDIRECTS = 3;

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $allowedHosts  empty = any public host; otherwise every hop must stay on these hosts
     */
    public static function fetch(string $url, array $config, ?int $maxBytes = null, array $allowedHosts = []): string
    {
        $request = (array) ($config['request'] ?? []);
        $maxBytes = max(1, (int) ($maxBytes ?? ($request['max_bytes'] ?? 6_000_000)));
        $current = trim($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = self::inspect($current, $allowedHosts);
            [$body, $status, $location] = self::request($target, $request, $maxBytes);

            if ($status >= 200 && $status < 300) {
                return $body;
            }

            if (in_array($status, [301, 302, 303, 307, 308], true) && $location !== '') {
                $current = self::resolveUrl($location, $target['url']);

                continue;
            }

            throw new RuntimeException("আনা যায়নি (HTTP {$status})");
        }

        throw new RuntimeException('অনেক বেশি রিডাইরেক্ট');
    }

    /**
     * Validate one URL and resolve its host to public addresses.
     * Public so the rules can be tested without touching the network.
     *
     * @param  list<string>  $allowedHosts
     * @return array{url: string, host: string, port: int, ips: list<string>}
     */
    public static function inspect(string $url, array $allowedHosts = []): array
    {
        $url = trim($url);

        if (preg_match('/[\x00-\x20\x7F]/', $url)) {
            throw new RuntimeException('ঠিকানায় অবৈধ অক্ষর আছে');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('ঠিকানা সঠিক নয়');
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('শুধু http/https ঠিকানা চলে');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('ঠিকানায় ব্যবহারকারীর তথ্য থাকতে পারবে না');
        }

        $host = self::normalizeHost((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, [80, 443], true)) {
            throw new RuntimeException('শুধু ৮০ ও ৪৪৩ পোর্টে অনুমতি আছে');
        }

        if ($allowedHosts !== []) {
            $allowed = array_map(fn (string $allowed): string => self::normalizeHost($allowed), $allowedHosts);

            if (! in_array($host, $allowed, true)) {
                throw new RuntimeException('অনুমোদিত হোস্ট নয়');
            }
        }

        return [
            'url' => $url,
            'host' => $host,
            'port' => $port,
            'ips' => self::resolveHost($host),
        ];
    }

    /** Resolve a Location header against the URL that produced it. */
    public static function resolveUrl(string $url, string $base): string
    {
        $url = trim($url);

        if ($url === '') {
            return $base;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $parts = parse_url($base);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $root = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = (string) ($parts['path'] ?? '/');

        if (str_starts_with($url, '//')) {
            return $parts['scheme'].':'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $root.$url;
        }

        if (str_starts_with($url, '?')) {
            return $root.$path.$url;
        }

        if (str_starts_with($url, '#')) {
            return $base.$url;
        }

        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $root.($dir === '' ? '' : $dir).'/'.$url;
    }

    /** Lowercase, drop a trailing dot and refuse anything that can reach the machine itself. */
    private static function normalizeHost(string $raw): string
    {
        $host = strtolower(trim($raw));

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $ip = substr($host, 1, -1);

            if (! self::publicIp($ip)) {
                throw new RuntimeException('এই ঠিকানায় অনুমতি নেই');
            }

            return $host;
        }

        $host = rtrim($host, '.');

        if ($host === '' || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            throw new RuntimeException('এই ঠিকানায় অনুমতি নেই');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (! self::publicIp($host)) {
                throw new RuntimeException('এই ঠিকানায় অনুমতি নেই');
            }

            return $host;
        }

        // A public host name needs a dot; single-label names are internal by definition.
        if (! str_contains($host, '.')
            || ! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host)) {
            throw new RuntimeException('হোস্টের নাম সঠিক নয়');
        }

        // 2130706433, 0x7f000001, 0177.0.0.1 — the resolver treats these as 127.0.0.1.
        if (self::numericHost($host)) {
            throw new RuntimeException('এই ঠিকানায় অনুমতি নেই');
        }

        return $host;
    }

    private static function numericHost(string $host): bool
    {
        foreach (explode('.', $host) as $label) {
            if ($label === '' || ! preg_match('/^(0x[0-9a-f]+|[0-9]+)$/i', $label)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private static function resolveHost(string $host): array
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return [substr($host, 1, -1)];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];

        foreach ((array) @dns_get_record($host, DNS_A | DNS_AAAA) as $record) {
            $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');

            if ($ip !== '') {
                $ips[] = $ip;
            }
        }

        if ($ips === []) {
            $ips = (array) @gethostbynamel($host);
        }

        $ips = array_values(array_unique(array_filter($ips, 'is_string')));

        if ($ips === []) {
            throw new RuntimeException('হোস্টের ঠিকানা পাওয়া যায়নি');
        }

        foreach ($ips as $ip) {
            if (! self::publicIp($ip)) {
                throw new RuntimeException('এই হোস্ট ইন্টারনাল ঠিকানায় যায়');
            }
        }

        return $ips;
    }

    private static function publicIp(string $ip): bool
    {
        // filter_var calls ::ffff:127.0.0.1 public, so unwrap the IPv4 first.
        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $match)) {
            $ip = $match[1];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param  array{url: string, host: string, port: int, ips: list<string>}  $target
     * @param  array<string, mixed>  $request
     * @return array{0: string, 1: int, 2: string} body, status, location
     */
    private static function request(array $target, array $request, int $maxBytes): array
    {
        $body = '';
        $location = '';
        $tooBig = false;
        $ch = curl_init($target['url']);

        $options = [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => max(1, (int) ($request['connect_timeout'] ?? 8)),
            CURLOPT_TIMEOUT => max(1, (int) ($request['timeout'] ?? 20)),
            CURLOPT_USERAGENT => (string) ($request['user_agent'] ?? 'NewsBot/1.0'),
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body, &$tooBig, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooBig = true;

                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$location): int {
                if (stripos($line, 'location:') === 0) {
                    $location = trim(substr($line, 9));
                }

                return strlen($line);
            },
        ];

        if (defined('CURLOPT_DISALLOW_USERNAME_IN_URL')) {
            $options[CURLOPT_DISALLOW_USERNAME_IN_URL] = true;
        }

        // Pin the checked addresses so curl cannot re-resolve the name to something internal.
        // IPv4 is preferred: some cURL builds cannot connect when IPv6 entries are mixed in.
        if (! str_starts_with($target['host'], '[') && filter_var($target['host'], FILTER_VALIDATE_IP) === false) {
            $ips = array_values(array_filter($target['ips'], fn (string $ip): bool => ! str_contains($ip, ':')));

            if ($ips === []) {
                $ips = $target['ips'];
            }

            $options[CURLOPT_RESOLVE] = array_map(
                fn (string $ip): string => $target['host'].':'.$target['port'].':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip),
                $ips
            );
        }

        curl_setopt_array($ch, $options);

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $primary = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $error = curl_error($ch);
        curl_close($ch);

        if ($tooBig) {
            throw new RuntimeException('ফাইলটি খুব বড়');
        }

        if ($ok === false) {
            throw new RuntimeException('আনা যায়নি: '.($error !== '' ? $error : 'অজানা ত্রুটি'));
        }

        // Belt and braces: the socket actually connected to a public address.
        if ($primary !== '' && ! self::publicIp($primary)) {
            throw new RuntimeException('এই হোস্ট ইন্টারনাল ঠিকানায় যায়');
        }

        return [$body, $status, $location];
    }
}
