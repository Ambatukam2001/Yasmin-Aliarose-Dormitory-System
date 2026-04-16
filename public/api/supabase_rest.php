<?php
/**
 * Supabase PostgREST helper — no Composer/npm. Uses curl + JSON.
 *
 * Usage:
 *   require_once __DIR__ . '/supabase_config.php'; // or copy from sample
 *   require_once __DIR__ . '/supabase_rest.php';
 *   $rows = supabase_rest_select('my_table', ['id' => 'eq.1']);
 */

if (file_exists(__DIR__ . '/supabase_config.php')) {
    require_once __DIR__ . '/supabase_config.php';
}

if (!function_exists('supabase_rest_request')) {

    function supabase_config_loaded(): bool {
        return defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY');
    }

    /**
     * @param string $method GET|POST|PATCH|DELETE
     * @param string $path e.g. "rooms" or "rpc/my_function" — no leading slash
     * @param array|null $json Request body for POST/PATCH
     * @param bool $serviceRole Use service role key (server-side admin); default anon
     * @return array{ok:bool,status:int,body:mixed,raw:string}
     */
    function supabase_rest_request(string $method, string $path, ?array $json = null, bool $serviceRole = false): array {
        if (!supabase_config_loaded()) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => 'Supabase config not loaded. Copy supabase_config.sample.php to supabase_config.php'];
        }

        $path = ltrim($path, '/');
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $path;

        $key = $serviceRole && defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== ''
            ? SUPABASE_SERVICE_ROLE_KEY
            : SUPABASE_ANON_KEY;

        $headers = [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept: application/json',
            'Prefer: return=representation',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($json !== null && in_array(strtoupper($method), ['POST', 'PATCH', 'PUT'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'body' => null, 'raw' => $err ?: 'curl error'];
        }

        $decoded = json_decode($raw, true);
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $decoded !== null ? $decoded : $raw,
            'raw' => $raw,
        ];
    }

    /**
     * @param array<string,string> $filters Query params e.g. ['id' => 'eq.5']
     */
    function supabase_rest_select(string $table, array $filters = [], array $options = []): array {
        $q = http_build_query(array_merge($filters, $options));
        $path = $table . ($q !== '' ? '?' . $q : '');
        return supabase_rest_request('GET', $path, null, false);
    }
}
