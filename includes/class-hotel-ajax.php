
<?php
if (!defined('ABSPATH')) { exit; }

class HR_Ajax {
    public static function init() {
        add_action('wp_ajax_hr_check_availability', ['HR_Ajax', 'check_availability']);
        add_action('wp_ajax_nopriv_hr_check_availability', ['HR_Ajax', 'check_availability']);

        add_action('wp_ajax_hr_create_booking', ['HR_Ajax', 'create_booking']);
        add_action('wp_ajax_nopriv_hr_create_booking', ['HR_Ajax', 'create_booking']);
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
        // Save meta
        update_post_meta($booking_id, 'hr_room_id', $room_id);
        update_post_meta($booking_id, 'hr_checkin', $checkin);
        update_post_meta($booking_id, 'hr_checkout', $checkout);
        update_post_meta($booking_id, 'hr_guest_name', $guest);
        update_post_meta($booking_id, 'hr_guest_email', $email);
        update_post_meta($booking_id, 'hr_guest_phone', $phone);
        update_post_meta($booking_id, 'hr_total', $total);
        update_post_meta($booking_id, 'hr_status', 'pending');

        wp_update_post([
            'ID' => $booking_id,
            'post_title' => 'Booking #' . $booking_id,
        ]);

        // Email notifications (basic)
        $admin_email = get_option('admin_email');
        $subject_admin = sprintf(__('New booking: #%d', 'hotel-reservation-lite'), $booking_id);
        $message_admin = sprintf("Room: %s
Dates: %s to %s (%d nights)
Guest: %s (%s)
Total: %s
", get_the_title($room_id), $checkin, $checkout, $nights, $guest, $email, hr_format_price($total));
        wp_mail($admin_email, $subject_admin, $message_admin);

        $subject_guest = sprintf(__('Your reservation request #%d', 'hotel-reservation-lite'), $booking_id);
        $message_guest = sprintf(__('Thank you, %s! Your reservation request for %s from %s to %s has been received. We will confirm shortly. Total: %s', 'hotel-reservation-lite'), $guest, get_the_title($room_id), $checkin, $checkout, hr_format_price($total));
        wp_mail($email, $subject_guest, $message_guest);

        wp_send_json_success([
            'booking_id' => $booking_id,
            'total' => $total,
            'formatted_total' => hr_format_price($total),
            'message' => __('Reservation created! Check your email for confirmation.', 'hotel-reservation-lite'),
        ]);
    }
}
