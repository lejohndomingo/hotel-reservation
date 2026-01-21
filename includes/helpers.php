
<?php
if (!defined('ABSPATH')) { exit; }

function hr_currency_symbol() {
    $cur = get_option('hr_currency', 'PHP');
    switch (strtoupper($cur)) {
        case 'USD': return '$';
        case 'EUR': return '€';
        case 'GBP': return '£';
        case 'JPY': return '¥';
        case 'PHP': default: return '₱';
    }
}

function hr_format_price($amount) {
    $symbol = hr_currency_symbol();
    $dec = get_option('hr_price_decimals', 2);
    return $symbol . number_format((float)$amount, (int)$dec);
}

class HR_Helpers {
    public static function valid_dates($checkin, $checkout) {
        $ci = strtotime($checkin);
        $co = strtotime($checkout);
        if (!$ci || !$co) return false;
        $today = strtotime(date('Y-m-d'));
        if ($ci < $today) return false;
        if ($co <= $ci) return false;
        // Limit to 30 nights for sanity
        $nights = self::count_nights($checkin, $checkout);
        return ($nights > 0 && $nights <= 30);
    }

    public static function count_nights($checkin, $checkout) {
        $ci = new DateTime($checkin);
        $co = new DateTime($checkout);
        return (int) $ci->diff($co)->format('%a');
    }

    public static function room_available($room_id, $checkin, $checkout) {
        // Availability is calculated by ensuring no overlapping confirmed/pending bookings.
        $args = [
            'post_type' => 'hr_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'hr_room_id',
                    'value' => $room_id,
                    'compare' => '='
                ],
                [
                    'key' => 'hr_status',
                    'value' => ['pending', 'confirmed'],
                    'compare' => 'IN'
                ]
            ]
        ];
        $q = new WP_Query($args);
        $available = true;
        if ($q->have_posts()) {
            while ($q->have_posts()) { $q->the_post();
                $b_ci = get_post_meta(get_the_ID(), 'hr_checkin', true);
                $b_co = get_post_meta(get_the_ID(), 'hr_checkout', true);
                if (self::dates_overlap($checkin, $checkout, $b_ci, $b_co)) {
                    $available = false; break;
                }
            }
            wp_reset_postdata();
        }
        return $available;
    }

    public static function dates_overlap($start1, $end1, $start2, $end2) {
        $s1 = strtotime($start1); $e1 = strtotime($end1);
        $s2 = strtotime($start2); $e2 = strtotime($end2);
        // Overlap if start < other_end and other_start < end
        return ($s1 < $e2) && ($s2 < $e1);
    }
}
