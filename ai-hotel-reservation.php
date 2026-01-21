
<?php
/**
 * Plugin Name: Hotel Room Reservation (Lite)
 * Description: A simple hotel room reservation plugin with Rooms & Bookings, availability checks, and shortcodes. No payment gateway (offline/confirm-by-email).
 * Version: 1.0.0
 * Author: M365 Copilot
 * License: GPL-2.0-or-later
 * Text Domain: hotel-reservation-lite
 */

if (!defined('ABSPATH')) { exit; }

// Constants
if (!defined('HR_PLUGIN_VERSION')) define('HR_PLUGIN_VERSION', '1.0.0');
if (!defined('HR_PLUGIN_FILE'))    define('HR_PLUGIN_FILE', __FILE__);
if (!defined('HR_PLUGIN_PATH'))    define('HR_PLUGIN_PATH', plugin_dir_path(__FILE__));
if (!defined('HR_PLUGIN_URL'))     define('HR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Includes
require_once HR_PLUGIN_PATH . 'includes/helpers.php';
require_once HR_PLUGIN_PATH . 'includes/class-hotel-post-types.php';
require_once HR_PLUGIN_PATH . 'includes/class-hotel-ajax.php';
require_once HR_PLUGIN_PATH . 'includes/class-hotel-settings.php';

class HR_Plugin {
    public function __construct() {
        // Init hooks
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'plugins_loaded']);
        register_activation_hook(HR_PLUGIN_FILE, ['HR_Plugin', 'activate']);
        register_deactivation_hook(HR_PLUGIN_FILE, ['HR_Plugin', 'deactivate']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Shortcodes
        add_shortcode('hotel_search', [$this, 'shortcode_search']);
        add_shortcode('hotel_booking', [$this, 'shortcode_booking']);
    }

    public static function activate() {
        // Register CPTs before flushing.
        HR_Post_Types::register();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function init() {
        HR_Post_Types::register();
    }

    public function plugins_loaded() {
        load_plugin_textdomain('hotel-reservation-lite', false, dirname(plugin_basename(__FILE__)) . '/languages');
        HR_Settings::init();
        HR_Ajax::init();
    }

    public function enqueue_assets() {
        wp_register_style('hr-frontend', HR_PLUGIN_URL . 'assets/css/frontend.css', [], HR_PLUGIN_VERSION);
        wp_enqueue_style('hr-frontend');

        wp_register_script('hr-frontend', HR_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], HR_PLUGIN_VERSION, true);
        $local = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('hr_ajax_nonce'),
        ];
        wp_localize_script('hr-frontend', 'HR_Ajax', $local);
        wp_enqueue_script('hr-frontend');
    }

    /* ================== Shortcodes ================== */
    public function shortcode_search($atts = [], $content = '') {
        // Simple room list with booking form links.
        $q = new WP_Query([
            'post_type' => 'hr_room',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        ob_start();
        ?>
        <div class="hr-search">
            <form class="hr-inline-form" onsubmit="return false;">
                <label>
                    <?php esc_html_e('Check-in', 'hotel-reservation-lite'); ?>
                    <input type="date" class="hr-checkin" min="<?php echo esc_attr(date('Y-m-d')); ?>" />
                </label>
                <label>
                    <?php esc_html_e('Check-out', 'hotel-reservation-lite'); ?>
                    <input type="date" class="hr-checkout" min="<?php echo esc_attr(date('Y-m-d', strtotime('+1 day'))); ?>" />
                </label>
                <button type="button" class="hr-search-btn"><?php esc_html_e('Search Availability', 'hotel-reservation-lite'); ?></button>
                <span class="hr-hint"><?php esc_html_e('Select dates to check each room.', 'hotel-reservation-lite'); ?></span>
            </form>
            <div class="hr-room-list">
                <?php if ($q->have_posts()) : while ($q->have_posts()) : $q->the_post();
                    $price = get_post_meta(get_the_ID(), 'hr_price_per_night', true);
                    $capacity = get_post_meta(get_the_ID(), 'hr_capacity', true);
                ?>
                <div class="hr-room" data-room-id="<?php echo esc_attr(get_the_ID()); ?>">
                    <div class="hr-room-thumb"><?php echo get_the_post_thumbnail(get_the_ID(), 'medium'); ?></div>
                    <div class="hr-room-body">
                        <h3 class="hr-room-title"><?php the_title(); ?></h3>
                        <div class="hr-room-excerpt"><?php the_excerpt(); ?></div>
                        <div class="hr-room-meta">
                            <span class="hr-price"><?php echo esc_html(hr_format_price($price)); ?> / <?php esc_html_e('night', 'hotel-reservation-lite'); ?></span>
                            <span class="hr-capacity">👤 x <?php echo esc_html($capacity ? $capacity : '1'); ?></span>
                        </div>
                        <div class="hr-actions">
                            <button class="hr-check-btn" type="button"><?php esc_html_e('Check availability', 'hotel-reservation-lite'); ?></button>
                            <a class="hr-book-link" href="#" data-room-id="<?php echo esc_attr(get_the_ID()); ?>"><?php esc_html_e('Book now', 'hotel-reservation-lite'); ?></a>
                            <span class="hr-availability-status"></span>
                        </div>
                    </div>
                </div>
                <?php endwhile; wp_reset_postdata(); else: ?>
                    <p><?php esc_html_e('No rooms found.', 'hotel-reservation-lite'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function shortcode_booking($atts = [], $content = '') {
        $atts = shortcode_atts([
            'room_id' => 0,
        ], $atts, 'hotel_booking');
        $room_id = absint($atts['room_id']);
        if (!$room_id) { $room_id = get_the_ID(); }
        if (!$room_id || get_post_type($room_id) !== 'hr_room') {
            return '<p>' . esc_html__('Invalid room.', 'hotel-reservation-lite') . '</p>';
        }
        $price = get_post_meta($room_id, 'hr_price_per_night', true);
        ob_start();
        ?>
        <div class="hr-booking" data-room-id="<?php echo esc_attr($room_id); ?>">
            <h3><?php echo esc_html(get_the_title($room_id)); ?></h3>
            <form class="hr-booking-form" onsubmit="return false;">
                <div class="hr-form-grid">
                    <label>
                        <?php esc_html_e('Check-in', 'hotel-reservation-lite'); ?>
                        <input type="date" name="checkin" required min="<?php echo esc_attr(date('Y-m-d')); ?>">
                    </label>
                    <label>
                        <?php esc_html_e('Check-out', 'hotel-reservation-lite'); ?>
                        <input type="date" name="checkout" required min="<?php echo esc_attr(date('Y-m-d', strtotime('+1 day'))); ?>">
                    </label>
                    <label>
                        <?php esc_html_e('Guest name', 'hotel-reservation-lite'); ?>
                        <input type="text" name="guest_name" required>
                    </label>
                    <label>
                        <?php esc_html_e('Email', 'hotel-reservation-lite'); ?>
                        <input type="email" name="guest_email" required>
                    </label>
                    <label>
                        <?php esc_html_e('Phone (optional)', 'hotel-reservation-lite'); ?>
                        <input type="tel" name="guest_phone">
                    </label>
                    <div class="hr-total">
                        <strong><?php esc_html_e('Estimated total:', 'hotel-reservation-lite'); ?></strong>
                        <span class="hr-total-amount" data-price="<?php echo esc_attr($price); ?>"><?php echo esc_html(hr_format_price($price)); ?></span>
                    </div>
                </div>
                <div class="hr-actions">
                    <button type="button" class="hr-check-availability-btn"><?php esc_html_e('Check Availability', 'hotel-reservation-lite'); ?></button>
                    <button type="button" class="hr-create-booking-btn" disabled><?php esc_html_e('Reserve (No Payment)', 'hotel-reservation-lite'); ?></button>
                    <span class="hr-status"></span>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

new HR_Plugin();
