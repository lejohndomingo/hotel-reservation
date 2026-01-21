
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
        return ($nights > 0 && $nights <= 30);
    }

    public static function count_nights($checkin, $checkout) {
        $ci = new DateTime($checkin);
        $co = new DateTime($checkout);
        return (int) $ci->diff($co)->format('%a');
    }

    /**
     * Availability for a room type with quantity.
     * Returns ['available' => bool, 'remaining' => int, 'booked' => int]
     */
    public static function room_type_availability($type_id, $checkin, $checkout) {
        $qty = (int) get_post_meta($type_id, 'hr_quantity', true);
        if ($qty < 1) $qty = 1;
        $args = [
            'post_type' => 'hrt_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => 'hr_room_type_id', 'value' => $type_id, 'compare' => '=' ],
                [ 'key' => 'hr_status', 'value' => ['pending','pending_payment','confirmed'], 'compare' => 'IN' ],
            ]
        ];
        $q = new WP_Query($args);
        $booked = 0;
        if ($q->posts) {
            foreach ($q->posts as $bid) {
                $b_ci = get_post_meta($bid, 'hr_checkin', true);
                $b_co = get_post_meta($bid, 'hr_checkout', true);
                if (self::dates_overlap($checkin, $checkout, $b_ci, $b_co)) {
                    $booked++;
                }
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
}
