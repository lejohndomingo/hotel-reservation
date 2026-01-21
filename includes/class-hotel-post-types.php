
<?php
if (!defined('ABSPATH')) { exit; }

class HR_Post_Types {
    public static function register() {
        self::register_rooms();
        self::register_bookings();
    }

    private static function register_rooms() {
        $labels = [
            'name' => __('Rooms', 'hotel-reservation-lite'),
            'singular_name' => __('Room', 'hotel-reservation-lite'),
            'add_new' => __('Add New', 'hotel-reservation-lite'),
            'add_new_item' => __('Add New Room', 'hotel-reservation-lite'),
            'edit_item' => __('Edit Room', 'hotel-reservation-lite'),
            'new_item' => __('New Room', 'hotel-reservation-lite'),
            'view_item' => __('View Room', 'hotel-reservation-lite'),
            'search_items' => __('Search Rooms', 'hotel-reservation-lite'),
            'not_found' => __('No rooms found', 'hotel-reservation-lite'),
            'menu_name' => __('Rooms', 'hotel-reservation-lite'),
        ];
        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-admin-home',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest' => true,
        ];
        register_post_type('hr_room', $args);

        add_action('add_meta_boxes', function() {
            add_meta_box('hr_room_meta', __('Room Details', 'hotel-reservation-lite'), ['HR_Post_Types', 'render_room_meta'], 'hr_room', 'side', 'default');
        });
        add_action('save_post_hr_room', ['HR_Post_Types', 'save_room_meta']);
    }

    public static function render_room_meta($post) {
        wp_nonce_field('hr_room_meta', 'hr_room_meta_nonce');
        $price = get_post_meta($post->ID, 'hr_price_per_night', true);
        $capacity = get_post_meta($post->ID, 'hr_capacity', true);
        ?>
        <p>
            <label for="hr_price_per_night"><strong><?php esc_html_e('Price per night', 'hotel-reservation-lite'); ?></strong></label>
            <input type="number" min="0" step="0.01" id="hr_price_per_night" name="hr_price_per_night" value="<?php echo esc_attr($price); ?>" class="widefat" />
        </p>
        <p>
            <label for="hr_capacity"><strong><?php esc_html_e('Capacity', 'hotel-reservation-lite'); ?></strong></label>
            <input type="number" min="1" step="1" id="hr_capacity" name="hr_capacity" value="<?php echo esc_attr($capacity ? $capacity : 1); ?>" class="widefat" />
        </p>
        <?php
    }

    public static function save_room_meta($post_id) {
        if (!isset($_POST['hr_room_meta_nonce']) || !wp_verify_nonce($_POST['hr_room_meta_nonce'], 'hr_room_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $price = isset($_POST['hr_price_per_night']) ? floatval($_POST['hr_price_per_night']) : 0;
        $cap = isset($_POST['hr_capacity']) ? intval($_POST['hr_capacity']) : 1;
        update_post_meta($post_id, 'hr_price_per_night', $price);
        update_post_meta($post_id, 'hr_capacity', max(1, $cap));
    }

    private static function register_bookings() {
        $labels = [
            'name' => __('Bookings', 'hotel-reservation-lite'),
            'singular_name' => __('Booking', 'hotel-reservation-lite'),
            'add_new_item' => __('Add Booking', 'hotel-reservation-lite'),
            'edit_item' => __('Edit Booking', 'hotel-reservation-lite'),
            'menu_name' => __('Bookings', 'hotel-reservation-lite'),
        ];
        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-clipboard',
            'supports' => ['title'],
        ];
        register_post_type('hr_booking', $args);

        add_action('add_meta_boxes', function() {
            add_meta_box('hr_booking_meta', __('Booking Details', 'hotel-reservation-lite'), ['HR_Post_Types', 'render_booking_meta'], 'hr_booking', 'normal', 'default');
        });
        add_action('save_post_hr_booking', ['HR_Post_Types', 'save_booking_meta']);
    }

    public static function render_booking_meta($post) {
        wp_nonce_field('hr_booking_meta', 'hr_booking_meta_nonce');
        $room_id   = get_post_meta($post->ID, 'hr_room_id', true);
        $checkin   = get_post_meta($post->ID, 'hr_checkin', true);
        $checkout  = get_post_meta($post->ID, 'hr_checkout', true);
        $guest     = get_post_meta($post->ID, 'hr_guest_name', true);
        $email     = get_post_meta($post->ID, 'hr_guest_email', true);
        $phone     = get_post_meta($post->ID, 'hr_guest_phone', true);
        $total     = get_post_meta($post->ID, 'hr_total', true);
        $status    = get_post_meta($post->ID, 'hr_status', true);
        ?>
        <p>
            <label><strong><?php esc_html_e('Room', 'hotel-reservation-lite'); ?></strong></label>
            <?php wp_dropdown_pages([
                'post_type' => 'hr_room',
                'name' => 'hr_room_id',
                'selected' => $room_id,
                'show_option_none' => __('— Select Room —', 'hotel-reservation-lite'),
                'option_none_value' => 0,
            ]); ?>
        </p>
        <p>
            <label><strong><?php esc_html_e('Check-in', 'hotel-reservation-lite'); ?></strong></label>
            <input type="date" name="hr_checkin" value="<?php echo esc_attr($checkin); ?>" />
            <label><strong><?php esc_html_e('Check-out', 'hotel-reservation-lite'); ?></strong></label>
            <input type="date" name="hr_checkout" value="<?php echo esc_attr($checkout); ?>" />
        </p>
        <p>
            <label><strong><?php esc_html_e('Guest', 'hotel-reservation-lite'); ?></strong></label>
            <input class="regular-text" type="text" name="hr_guest_name" value="<?php echo esc_attr($guest); ?>" placeholder="<?php esc_attr_e('Guest name', 'hotel-reservation-lite'); ?>" />
            <input class="regular-text" type="email" name="hr_guest_email" value="<?php echo esc_attr($email); ?>" placeholder="guest@example.com" />
            <input class="regular-text" type="tel" name="hr_guest_phone" value="<?php echo esc_attr($phone); ?>" placeholder="+63..." />
        </p>
        <p>
            <label><strong><?php esc_html_e('Total', 'hotel-reservation-lite'); ?></strong></label>
            <input type="number" step="0.01" name="hr_total" value="<?php echo esc_attr($total); ?>" />
            <label><strong><?php esc_html_e('Status', 'hotel-reservation-lite'); ?></strong></label>
            <select name="hr_status">
                <?php $statuses = ['pending' => __('Pending', 'hotel-reservation-lite'), 'pending_payment' => __('Pending Payment', 'hotel-reservation-lite'), 'confirmed' => __('Confirmed', 'hotel-reservation-lite'), 'cancelled' => __('Cancelled', 'hotel-reservation-lite')];
                foreach ($statuses as $k => $lbl) {
                    printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($status, $k, false), esc_html($lbl));
                } ?>
            </select>
        </p>
        <?php
    }

    public static function save_booking_meta($post_id) {
        if (!isset($_POST['hr_booking_meta_nonce']) || !wp_verify_nonce($_POST['hr_booking_meta_nonce'], 'hr_booking_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $fields = [
            'hr_room_id' => 'absint',
            'hr_checkin' => 'sanitize_text_field',
            'hr_checkout'=> 'sanitize_text_field',
            'hr_guest_name' => 'sanitize_text_field',
            'hr_guest_email'=> 'sanitize_email',
            'hr_guest_phone'=> 'sanitize_text_field',
            'hr_total'      => 'floatval',
            'hr_status'     => 'sanitize_text_field',
        ];
        foreach ($fields as $k => $cb) {
            if (isset($_POST[$k])) {
                $val = call_user_func($cb, $_POST[$k]);
                update_post_meta($post_id, $k, $val);
            }
        }
        if (get_post_type($post_id) === 'hr_booking') {
            wp_update_post([
                'ID' => $post_id,
                'post_title' => 'Booking #' . $post_id,
            ]);
        }
    }
}
