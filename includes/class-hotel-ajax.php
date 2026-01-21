
<?php
if (!defined('ABSPATH')) { exit; }

class HR_Ajax {
    public static function init() {
        add_action('wp_ajax_hr_check_availability', ['HR_Ajax', 'check_availability']);
        add_action('wp_ajax_nopriv_hr_check_availability', ['HR_Ajax', 'check_availability']);

        add_action('wp_ajax_hr_create_booking', ['HR_Ajax', 'create_booking']);
        add_action('wp_ajax_nopriv_hr_create_booking', ['HR_Ajax', 'create_booking']);

        // Stripe endpoints
        add_action('wp_ajax_hr_create_payment_intent', ['HR_Ajax', 'create_payment_intent']);
        add_action('wp_ajax_nopriv_hr_create_payment_intent', ['HR_Ajax', 'create_payment_intent']);
        add_action('wp_ajax_hr_mark_booking_paid', ['HR_Ajax', 'mark_booking_paid']);
        add_action('wp_ajax_nopriv_hr_mark_booking_paid', ['HR_Ajax', 'mark_booking_paid']);

        add_action('rest_api_init', ['HR_Ajax', 'register_webhook_route']);
    }

    public static function check_availability() {
        check_ajax_referer('hr_ajax_nonce', 'nonce');
        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;
        $checkin = isset($_POST['checkin']) ? sanitize_text_field($_POST['checkin']) : '';
        $checkout= isset($_POST['checkout']) ? sanitize_text_field($_POST['checkout']) : '';
        if (!$room_id || !$checkin || !$checkout) {
            wp_send_json_error(['message' => __('Missing data', 'hotel-reservation-lite')], 400);
        }
        if (!HR_Helpers::valid_dates($checkin, $checkout)) {
            wp_send_json_error(['message' => __('Invalid dates', 'hotel-reservation-lite')], 400);
        }
        $available = HR_Helpers::room_available($room_id, $checkin, $checkout);
        $nights = HR_Helpers::count_nights($checkin, $checkout);
        $price = (float) get_post_meta($room_id, 'hr_price_per_night', true);
        $total = $nights * $price;
        wp_send_json_success([
            'available' => (bool) $available,
            'nights'    => $nights,
            'price_per_night' => $price,
            'total'     => $total,
            'formatted_total' => hr_format_price($total),
        ]);
    }

    public static function create_booking() {
        check_ajax_referer('hr_ajax_nonce', 'nonce');
        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;
        $checkin = isset($_POST['checkin']) ? sanitize_text_field($_POST['checkin']) : '';
        $checkout= isset($_POST['checkout']) ? sanitize_text_field($_POST['checkout']) : '';
        $guest   = isset($_POST['guest_name']) ? sanitize_text_field($_POST['guest_name']) : '';
        $email   = isset($_POST['guest_email']) ? sanitize_email($_POST['guest_email']) : '';
        $phone   = isset($_POST['guest_phone']) ? sanitize_text_field($_POST['guest_phone']) : '';

        if (!$room_id || !$checkin || !$checkout || !$guest || !$email) {
            wp_send_json_error(['message' => __('Please complete all required fields.', 'hotel-reservation-lite')], 400);
        }
        if (!HR_Helpers::valid_dates($checkin, $checkout)) {
            wp_send_json_error(['message' => __('Invalid dates.', 'hotel-reservation-lite')], 400);
        }
        if (!HR_Helpers::room_available($room_id, $checkin, $checkout)) {
            wp_send_json_error(['message' => __('Room is not available for selected dates.', 'hotel-reservation-lite')], 409);
        }

        $nights = HR_Helpers::count_nights($checkin, $checkout);
        $price  = (float) get_post_meta($room_id, 'hr_price_per_night', true);
        $total  = $nights * $price;

        $booking_id = wp_insert_post([
            'post_type' => 'hr_booking',
            'post_status' => 'publish',
            'post_title' => __('Booking', 'hotel-reservation-lite'),
        ]);
        if (is_wp_error($booking_id) || !$booking_id) {
            wp_send_json_error(['message' => __('Could not create booking.', 'hotel-reservation-lite')], 500);
        }
        update_post_meta($booking_id, 'hr_room_id', $room_id);
        update_post_meta($booking_id, 'hr_checkin', $checkin);
        update_post_meta($booking_id, 'hr_checkout', $checkout);
        update_post_meta($booking_id, 'hr_guest_name', $guest);
        update_post_meta($booking_id, 'hr_guest_email', $email);
        update_post_meta($booking_id, 'hr_guest_phone', $phone);
        update_post_meta($booking_id, 'hr_total', $total);
        update_post_meta($booking_id, 'hr_status', 'pending');

        wp_update_post(['ID' => $booking_id, 'post_title' => 'Booking #' . $booking_id]);

        // Emails
        $admin_email = get_option('admin_email');
        wp_mail($admin_email, sprintf(__('New booking: #%d', 'hotel-reservation-lite'), $booking_id), sprintf("Room: %s
Dates: %s to %s (%d nights)
Guest: %s (%s)
Total: %s
", get_the_title($room_id), $checkin, $checkout, $nights, $guest, $email, hr_format_price($total)));
        wp_mail($email, sprintf(__('Your reservation request #%d', 'hotel-reservation-lite'), $booking_id), sprintf(__('Thank you, %s! Your reservation request for %s from %s to %s has been received. We will confirm shortly. Total: %s', 'hotel-reservation-lite'), $guest, get_the_title($room_id), $checkin, $checkout, hr_format_price($total)));

        wp_send_json_success([
            'booking_id' => $booking_id,
            'total' => $total,
            'formatted_total' => hr_format_price($total),
            'message' => __('Reservation created! Check your email for confirmation.', 'hotel-reservation-lite'),
        ]);
    }

    // ===== Stripe Flow =====
    public static function create_payment_intent() {
        check_ajax_referer('hr_ajax_nonce', 'nonce');
        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;
        $checkin = isset($_POST['checkin']) ? sanitize_text_field($_POST['checkin']) : '';
        $checkout= isset($_POST['checkout']) ? sanitize_text_field($_POST['checkout']) : '';
        $guest   = isset($_POST['guest_name']) ? sanitize_text_field($_POST['guest_name']) : '';
        $email   = isset($_POST['guest_email']) ? sanitize_email($_POST['guest_email']) : '';
        $phone   = isset($_POST['guest_phone']) ? sanitize_text_field($_POST['guest_phone']) : '';

        $sk = get_option('hr_stripe_sk', '');
        if (!$sk) { wp_send_json_error(['message' => __('Payments not configured.', 'hotel-reservation-lite')], 400); }
        if (!$room_id || !$checkin || !$checkout || !$guest || !$email) {
            wp_send_json_error(['message' => __('Please complete all required fields.', 'hotel-reservation-lite')], 400);
        }
        if (!HR_Helpers::valid_dates($checkin, $checkout)) {
            wp_send_json_error(['message' => __('Invalid dates.', 'hotel-reservation-lite')], 400);
        }
        if (!HR_Helpers::room_available($room_id, $checkin, $checkout)) {
            wp_send_json_error(['message' => __('Room is not available for selected dates.', 'hotel-reservation-lite')], 409);
        }

        $nights = HR_Helpers::count_nights($checkin, $checkout);
        $price  = (float) get_post_meta($room_id, 'hr_price_per_night', true);
        $total  = $nights * $price;
        $currency = strtolower(get_option('hr_currency', 'PHP'));
        $amount_minor = HR_Stripe::minor_amount($currency, $total);

        // Create booking as pending_payment
        $booking_id = wp_insert_post([
            'post_type' => 'hr_booking',
            'post_status' => 'publish',
            'post_title' => __('Booking', 'hotel-reservation-lite'),
        ]);
        if (is_wp_error($booking_id) || !$booking_id) {
            wp_send_json_error(['message' => __('Could not create booking.', 'hotel-reservation-lite')], 500);
        }
        update_post_meta($booking_id, 'hr_room_id', $room_id);
        update_post_meta($booking_id, 'hr_checkin', $checkin);
        update_post_meta($booking_id, 'hr_checkout', $checkout);
        update_post_meta($booking_id, 'hr_guest_name', $guest);
        update_post_meta($booking_id, 'hr_guest_email', $email);
        update_post_meta($booking_id, 'hr_guest_phone', $phone);
        update_post_meta($booking_id, 'hr_total', $total);
        update_post_meta($booking_id, 'hr_status', 'pending_payment');
        wp_update_post(['ID' => $booking_id, 'post_title' => 'Booking #' . $booking_id]);

        $args = [
            'amount' => $amount_minor,
            'currency' => $currency,
            'description' => sprintf('Booking #%d – %s', $booking_id, get_the_title($room_id)),
            'receipt_email' => $email,
            'metadata' => [ 'booking_id' => (string)$booking_id, 'site' => home_url() ],
            'automatic_payment_methods' => [ 'enabled' => 'true' ],
        ];
        $res = HR_Stripe::create_payment_intent($sk, $args);
        if (is_wp_error($res)) { wp_send_json_error(['message' => $res->get_error_message()], 500); }
        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if ($code < 200 || $code >= 300 || !is_array($body) || empty($body['client_secret'])) {
            $msg = isset($body['error']['message']) ? $body['error']['message'] : __('Stripe error', 'hotel-reservation-lite');
            wp_send_json_error(['message' => $msg], 500);
        }
        if (!empty($body['id'])) { update_post_meta($booking_id, 'hr_stripe_pi', sanitize_text_field($body['id'])); }

        wp_send_json_success([
            'booking_id' => $booking_id,
            'client_secret' => $body['client_secret'],
            'amount' => $amount_minor,
            'currency' => $currency,
            'message' => __('Payment intent created. Please complete payment.', 'hotel-reservation-lite'),
        ]);
    }

    public static function mark_booking_paid() {
        check_ajax_referer('hr_ajax_nonce', 'nonce');
        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $pi_id      = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';
        if (!$booking_id || !$pi_id) { wp_send_json_error(['message' => __('Missing data', 'hotel-reservation-lite')], 400); }
        $sk = get_option('hr_stripe_sk', '');
        if (!$sk) { wp_send_json_error(['message' => __('Payments not configured.', 'hotel-reservation-lite')], 400); }

        $res = HR_Stripe::retrieve_payment_intent($sk, $pi_id);
        if (is_wp_error($res)) { wp_send_json_error(['message' => $res->get_error_message()], 500); }
        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if ($code < 200 || $code >= 300 || !is_array($body)) {
            wp_send_json_error(['message' => __('Stripe error', 'hotel-reservation-lite')], 500);
        }
        if (!empty($body['status']) && in_array($body['status'], ['succeeded','requires_capture','processing'], true)) {
            update_post_meta($booking_id, 'hr_status', 'confirmed');
            update_post_meta($booking_id, 'hr_stripe_pi', $pi_id);
            if (!empty($body['charges']['data'][0]['id'])) {
                update_post_meta($booking_id, 'hr_stripe_charge', sanitize_text_field($body['charges']['data'][0]['id']));
            }
            // Emails
            $room_id = get_post_meta($booking_id, 'hr_room_id', true);
            $guest   = get_post_meta($booking_id, 'hr_guest_name', true);
            $email   = get_post_meta($booking_id, 'hr_guest_email', true);
            $checkin = get_post_meta($booking_id, 'hr_checkin', true);
            $checkout= get_post_meta($booking_id, 'hr_checkout', true);
            $total   = get_post_meta($booking_id, 'hr_total', true);
            $admin_email = get_option('admin_email');
            wp_mail($admin_email, sprintf(__('Booking paid: #%d', 'hotel-reservation-lite'), $booking_id), sprintf("Room: %s
Dates: %s to %s
Guest: %s (%s)
Total: %s
", get_the_title($room_id), $checkin, $checkout, $guest, $email, hr_format_price($total)));
            wp_mail($email, sprintf(__('Your booking is confirmed #%d', 'hotel-reservation-lite'), $booking_id), sprintf(__('Thanks, %s! Your payment was successful. We look forward to hosting you.', 'hotel-reservation-lite'), $guest));
            wp_send_json_success(['message' => __('Payment confirmed. Booking is now confirmed.', 'hotel-reservation-lite')]);
        }
        wp_send_json_error(['message' => __('Payment not completed.', 'hotel-reservation-lite')], 400);
    }

    public static function register_webhook_route() {
        register_rest_route('hr/v1', '/stripe/webhook', [
            'methods' => 'POST',
            'callback' => ['HR_Ajax', 'stripe_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function stripe_webhook(\WP_REST_Request $request) {
        $secret = get_option('hr_stripe_webhook_secret', '');
        $payload = $request->get_body();
        $sig = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? sanitize_text_field($_SERVER['HTTP_STRIPE_SIGNATURE']) : '';
        if ($secret && !HR_Stripe::verify_webhook($secret, $payload, $sig)) {
            return new \WP_REST_Response(['message' => 'Invalid signature'], 400);
        }
        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            return new \WP_REST_Response(['message' => 'Bad payload'], 400);
        }
        if ($event['type'] === 'payment_intent.succeeded') {
            $pi = $event['data']['object'];
            $booking_id = isset($pi['metadata']['booking_id']) ? absint($pi['metadata']['booking_id']) : 0;
            if ($booking_id) {
                update_post_meta($booking_id, 'hr_status', 'confirmed');
                update_post_meta($booking_id, 'hr_stripe_pi', sanitize_text_field($pi['id']));
            }
        }
        return new \WP_REST_Response(['received' => true], 200);
    }
}
