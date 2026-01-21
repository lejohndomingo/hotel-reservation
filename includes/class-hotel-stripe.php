
<?php
if (!defined('ABSPATH')) { exit; }

class HR_Stripe {
    public static function minor_amount($currency, $amount) {
        $currency = strtolower($currency);
        $zero = ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf'];
        if (in_array($currency, $zero, true)) { return (int) round($amount, 0); }
        return (int) round($amount * 100);
    }

    public static function create_payment_intent($secret, $args) {
        $endpoint = 'https://api.stripe.com/v1/payment_intents';
        $headers = [ 'Authorization' => 'Bearer ' . $secret ];
        $body = self::form_encode($args);
        $res = wp_remote_post($endpoint, [ 'headers' => $headers, 'body' => $body, 'timeout' => 45 ]);
        return $res;
    }

    public static function retrieve_payment_intent($secret, $pi_id) {
        $endpoint = 'https://api.stripe.com/v1/payment_intents/' . rawurlencode($pi_id);
        $headers = [ 'Authorization' => 'Bearer ' . $secret ];
        $res = wp_remote_get($endpoint, [ 'headers' => $headers, 'timeout' => 30 ]);
        return $res;
    }

    private static function form_encode($params, $prefix = '') {
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($prefix) { $key = $prefix . '[' . $key . ']'; }
            if (is_array($value)) { $pairs[] = self::form_encode($value, $key); }
            else { $pairs[] = rawurlencode($key) . '=' . rawurlencode($value); }
        }
        return implode('&', $pairs);
    }

    public static function verify_webhook($secret, $payload, $sig_header) {
        if (!$secret || !$sig_header) return false;
        $parts = [];
        foreach (explode(',', $sig_header) as $kv) {
            $kvp = explode('=', $kv, 2);
            if (count($kvp) === 2) { $parts[trim($kvp[0])] = trim($kvp[1]); }
        }
        if (empty($parts['t']) || empty($parts['v1'])) return false;
        $signed_payload = $parts['t'] . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $secret);
        if (function_exists('hash_equals')) { return hash_equals($expected, $parts['v1']); }
        return $expected === $parts['v1'];
    }
}
