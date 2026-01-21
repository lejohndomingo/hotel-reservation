
<?php
if (!defined('ABSPATH')) { exit; }

class HRT_Post_Types {
    public static function register() {
        self::register_room_types();
        self::register_bookings();
    }

    private static function register_room_types() {
        $labels = [
            'name' => __('Room Types', 'hotel-reservation-lite'),
            'singular_name' => __('Room Type', 'hotel-reservation-lite'),
            'add_new' => __('Add New', 'hotel-reservation-lite'),
            'add_new_item' => __('Add New Room Type', 'hotel-reservation-lite'),
            'edit_item' => __('Edit Room Type', 'hotel-reservation-lite'),
            'new_item' => __('New Room Type', 'hotel-reservation-lite'),
            'view_item' => __('View Room Type', 'hotel-reservation-lite'),
            'search_items' => __('Search Room Types', 'hotel-reservation-lite'),
            'not_found' => __('No room types found', 'hotel-reservation-lite'),
            'menu_name' => __('Room Types', 'hotel-reservation-lite'),
        ];
        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-building',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest' => true,
        ];
        register_post_type('hrt_room_type', $args);

        // Meta boxes
        add_action('add_meta_boxes', function() {
            add_meta_box('hrt_type_meta', __('Room Type Details', 'hotel-reservation-lite'), ['HRT_Post_Types', 'render_type_meta'], 'hrt_room_type', 'side', 'default');
        });
        add_action('save_post_hrt_room_type', ['HRT_Post_Types', 'save_type_meta']);
    }

    public static function render_type_meta($post) {
        wp_nonce_field('hrt_type_meta', 'hrt_type_meta_nonce');
        $price = get_post_meta($post->ID, 'hr_price_per_night', true);
        $capacity = get_post_meta($post->ID, 'hr_capacity', true);
        $quantity = get_post_meta($post->ID, 'hr_quantity', true);
        ?>
        <p>
            <label for="hr_price_per_night"><strong><?php esc_html_e('Price per night', 'hotel-reservation-lite'); ?></strong></label>
            <input type="number" min="0" step="0.01" id="hr_price_per_night" name="hr_price_per_night" value="<?php echo esc_attr($price); ?>" class="widefat" />
        </p>
        <p>
            <label for="hr_capacity"><strong><?php esc_html_e('Capacity', 'hotel-reservation-lite'); ?></strong></label>
            <input type="number" min="1" step="1" id="hr_capacity" name="hr_capacity" value="<?php echo esc_attr($capacity ? $capacity : 1); ?>" class="widefat" />
        </p>
        <p>
            <label for="hr_quantity"><strong><?php esc_html_e('Quantity (number of rooms of this type)', 'hotel-reservation-lite'); ?></strong></label>
            <input type="number" min="1" step="1" id="hr_quantity" name="hr_quantity" value="<?php echo esc_attr($quantity ? $quantity : 1); ?>" class="widefat" />
        </p>
        <?php
    }

    public static function save_type_meta($post_id) {
        if (!isset($_POST['hrt_type_meta_nonce']) || !wp_verify_nonce($_POST['hrt_type_meta_nonce'], 'hrt_type_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $price = isset($_POST['hr_price_per_night']) ? floatval($_POST['hr_price_per_night']) : 0;
        $cap = isset($_POST['hr_capacity']) ? intval($_POST['hr_capacity']) : 1;
        $qty = isset($_POST['hr_quantity']) ? intval($_POST['hr_quantity']) : 1;
        update_post_meta($post_id, 'hr_price_per_night', $price);
        update_post_meta($post_id, 'hr_capacity', max(1, $cap));
        update_post_meta($post_id, 'hr_quantity', max(1, $qty));
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
        register_post_type('hrt_booking', $args);

        add_action('add_meta_boxes', function() {
            add_meta_box('hrt_booking_meta', __('Booking Details', 'hotel-reservation-lite'), ['HRT_Post_Types', 'render_booking_meta'], 'hrt_booking', 'normal', 'default');
        });
        add_action('save_post_hrt_booking', ['HRT_Post_Types', 'save_booking_meta']);
    }

    public static function render_booking_meta($post) {
        wp_nonce_field('hrt_booking_meta', 'hrt_booking_meta_nonce');
        $type_id  = get_post_meta($post->ID, 'hr_room_type_id', true);
        $checkin  = get_post_meta($post->ID, 'hr_checkin', true);
        $checkout = get_post_meta($post->ID, 'hr_checkout', true);
        $guest    = get_post_meta($post->ID, 'hr_guest_name', true);
        $email    = get_post_meta($post->ID, 'hr_guest_email', true);
        $phone    = get_post_meta($post->ID, 'hr_guest_phone', true);
        $total    = get_post_meta($post->ID, 'hr_total', true);
        $status   = get_post_meta($post->ID, 'hr_status', true);
        ?>
        <p>
            <label><strong><?php esc_html_e('Room Type', 'hotel-reservation-lite'); ?></strong></label>
            <?php
            $room_types = get_posts(['post_type' => 'hrt_room_type', 'numberposts' => -1]);
            echo '<select name="hr_room_type_id">';
            echo '<option value="0">' . esc_html__('— Select Room Type —', 'hotel-reservation-lite') . '</option>';
            foreach ($room_types as $rt) {
                printf('<option value="%d" %s>%s</option>', $rt->ID, selected($type_id, $rt->ID, false), esc_html($rt->post_title));
            }
            echo '</select>';
            ?>
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
        if (!isset($_POST['hrt_booking_meta_nonce']) || !wp_verify_nonce($_POST['hrt_booking_meta_nonce'], 'hrt_booking_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $fields = [
            'hr_room_type_id' => 'absint',
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
        if (get_post_type($post_id) === 'hrt_booking') {
            wp_update_post(['ID' => $post_id, 'post_title' => 'Booking #' . $post_id]);
        }
    }
}
