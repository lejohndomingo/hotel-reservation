
<?php
if (!defined('ABSPATH')) { exit; }

function hrt_currency_symbol() {
    $cur = get_option('hr_currency', 'PHP');
    switch (strtoupper($cur)) {
        case 'USD': return '$';
        case 'EUR': return '€';
        case 'GBP': return '£';
        case 'JPY': return '¥';
        case 'PHP': default: return '₱';
    }
}

function hrt_format_price($amount) {
    $symbol = hrt_currency_symbol();
    $dec = get_option('hr_price_decimals', 2);
    return $symbol . number_format((float)$amount, (int)$dec);
}

class HRT_Helpers {
    public static function valid_dates($checkin, $checkout) {
        $ci = strtotime($checkin);
        $co = strtotime($checkout);
        if (!$ci || !$co) return false;
        $today = strtotime(date('Y-m-d'));
        if ($ci < $today) return false;
        if ($co <= $ci) return false;
        $nights = self::count_nights($checkin, $checkout);
        return ($nights > 0 && $nights <= 90); // Allow up to 90 nights when using monthly rates
    }

    public static function count_nights($checkin, $checkout) {
        $ci = new DateTime($checkin);
        $co = new DateTime($checkout);
        return (int) $ci->diff($co)->format('%a');
    }

    public static function room_type_availability($type_id, $checkin, $checkout) {
        $qty = (int) get_post_meta($type_id, 'hr_quantity', true);
        if ($qty < 1) $qty = 1;
        $q = new WP_Query([
            'post_type' => 'hrt_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => 'hr_room_type_id', 'value' => $type_id, 'compare' => '=' ],
                [ 'key' => 'hr_status', 'value' => ['pending','pending_payment','confirmed'], 'compare' => 'IN' ],
            ]
        ]);
        $booked = 0;
        if ($q->posts) {
            foreach ($q->posts as $bid) {
                $b_ci = get_post_meta($bid, 'hr_checkin', true);
                $b_co = get_post_meta($bid, 'hr_checkout', true);
                if (self::dates_overlap($checkin, $checkout, $b_ci, $b_co)) { $booked++; }
            }
        }
        $remaining = max(0, $qty - $booked);
        return [ 'available' => ($remaining > 0), 'remaining' => $remaining, 'booked' => $booked ];
    }

    public static function dates_overlap($start1, $end1, $start2, $end2) {
        $s1 = strtotime($start1); $e1 = strtotime($end1);
        $s2 = strtotime($start2); $e2 = strtotime($end2);
        return ($s1 < $e2) && ($s2 < $e1);
    }

    /**
     * Best-rate total using seasonal nightly, weekly (7), and monthly (30) blocks via DP.
     */
    public static function calculate_total_best_rate($type_id, $checkin, $checkout) {
        $n = self::count_nights($checkin, $checkout);
        if ($n <= 0) return 0.0;
        $base = (float) get_post_meta($type_id, 'hr_price_per_night', true);
        $seasons = get_post_meta($type_id, 'hr_seasons', true);
        if (!is_array($seasons)) { $seasons = []; }
        $weekly_rate = (float) get_post_meta($type_id, 'hr_weekly_rate', true);
        $monthly_rate = (float) get_post_meta($type_id, 'hr_monthly_rate', true);
        $has_week = $weekly_rate > 0; $has_month = $monthly_rate > 0;

        // nightly array
        $dates = [];
        $cur = new DateTime($checkin); $end = new DateTime($checkout);
        while ($cur < $end) { $dates[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }
        $nightly = [];
        foreach ($dates as $d) {
            $price = $base;
            foreach ($seasons as $row) {
                $s = $row['start'] ?? ''; $e = $row['end'] ?? '';
                $p = isset($row['price']) ? (float)$row['price'] : 0.0;
                if ($s && $e && $p > 0 && $d >= $s && $d < $e) { $price = $p; break; }
            }
            $nightly[] = $price;
        }

        // prefix sums
        $pref = [0]; foreach ($nightly as $i=>$v) { $pref[] = $pref[$i] + (float)$v; }
        $sum = function($l,$r) use ($pref) { return $pref[$r] - $pref[$l]; };

        $dp = array_fill(0, $n+1, 0.0);
        for ($i = $n-1; $i >= 0; $i--) {
            $best = $nightly[$i] + $dp[$i+1];
            if ($has_week && $i+7 <= $n) {
                $best = min($best, min($weekly_rate, $sum($i,$i+7)) + $dp[$i+7]);
            }
            if ($has_month && $i+30 <= $n) {
                $best = min($best, min($monthly_rate, $sum($i,$i+30)) + $dp[$i+30]);
            }
            $dp[$i] = $best;
        }
        return (float) $dp[0];
    }
}


/**
 * Enforce min nights per season.
 * Mode (option hr_min_nights_mode):
 *  - 'arrival_only' (default): If check-in night falls inside a season with min>0, total stay nights must be >= min.
 *  - 'cover_all': For every season overlapped by the stay, the number of nights that fall in that season must be 0 or >= min.
 * Returns ['ok'=>bool, 'message'=>string]
 */
public static function season_min_nights_check($type_id, $checkin, $checkout) {
    $seasons = get_post_meta($type_id, 'hr_seasons', true);
    if (!is_array($seasons)) { $seasons = []; }
    $mode = get_option('hr_min_nights_mode', 'arrival_only');
    $nights = self::count_nights($checkin, $checkout);
    if ($nights <= 0) {
        return [ 'ok' => false, 'message' => __('Invalid dates.', 'hotel-reservation-lite') ];
    }
    // Helper to count nights in intersection with a season
    $count_overlap_nights = function($s, $e) use ($checkin, $checkout) {
        $start = max(strtotime($s), strtotime($checkin));
        $end   = min(strtotime($e), strtotime($checkout));
        if ($start >= $end) return 0;
        $d1 = new DateTime(date('Y-m-d', $start));
        $d2 = new DateTime(date('Y-m-d', $end));
        return (int)$d1->diff($d2)->format('%a');
    };

    if ($mode === 'arrival_only') {
        $d = $checkin; // first night
        foreach ($seasons as $row) {
            $s = $row['start'] ?? ''; $e = $row['end'] ?? '';
            $min = isset($row['min']) ? (int)$row['min'] : 0;
            if ($min > 0 && $s && $e && $d >= $s && $d < $e) {
                if ($nights < $min) {
                    return [ 'ok' => false, 'message' => sprintf(__('Minimum %d nights apply for selected dates.', 'hotel-reservation-lite'), $min) ];
                }
                break; // first matching season governs arrival rule
            }
        }
        return [ 'ok' => true, 'message' => '' ];
    }
    // cover_all mode
    foreach ($seasons as $row) {
        $s = $row['start'] ?? ''; $e = $row['end'] ?? '';
        $min = isset($row['min']) ? (int)$row['min'] : 0;
        if ($min > 0 && $s && $e) {
            $k = $count_overlap_nights($s, $e);
            if ($k > 0 && $k < $min) {
                return [ 'ok' => false, 'message' => sprintf(__('Nights inside %s require at least %d nights (you selected %d).', 'hotel-reservation-lite'), esc_html($row['label'] ?? __('season', 'hotel-reservation-lite')), $min, $k) ];
            }
        }
    }
    return [ 'ok' => true, 'message' => '' ];
}
