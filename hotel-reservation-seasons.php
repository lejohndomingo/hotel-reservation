
<?php
/**
 * Plugin Name: Hotel Reservation – Room Types, Quantity & Seasonal Pricing (Stripe Ready)
 * Description: Room types with quantity, seasonal pricing, availability checks, Stripe card payments, and shortcodes for search & booking.
 * Version: 1.3.0
 * Author: M365 Copilot
 * License: GPL-2.0-or-later
 * Text Domain: hotel-reservation-lite
 */

if (!defined('ABSPATH')) { exit; }

// Constants
if (!defined('HRT_PLUGIN_VERSION')) define('HRT_PLUGIN_VERSION', '1.3.0');
if (!defined('HRT_PLUGIN_FILE'))    define('HRT_PLUGIN_FILE', __FILE__);
if (!defined('HRT_PLUGIN_PATH'))    define('HRT_PLUGIN_PATH', plugin_dir_path(__FILE__));
if (!defined('HRT_PLUGIN_URL'))     define('HRT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Includes
require_once HRT_PLUGIN_PATH . 'includes/helpers.php';
require_once HRT_PLUGIN_PATH . 'includes/class-post-types.php';
require_once HRT_PLUGIN_PATH . 'includes/class-ajax.php';
require_once HRT_PLUGIN_PATH . 'includes/class-settings.php';
require_once HRT_PLUGIN_PATH . 'includes/class-stripe.php';

class HRT_Plugin {
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'plugins_loaded']);
        register_activation_hook(HRT_PLUGIN_FILE, ['HRT_Plugin', 'activate']);
        register_deactivation_hook(HRT_PLUGIN_FILE, ['HRT_Plugin', 'deactivate']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_shortcode('hotel_search', [$this, 'shortcode_search']);
        add_shortcode('hotel_booking', [$this, 'shortcode_booking']);
    }

    public static function activate() {
        HRT_Post_Types::register();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function init() {
        HRT_Post_Types::register();
    }

    public function plugins_loaded() {
        load_plugin_textdomain('hotel-reservation-lite', false, dirname(plugin_basename(__FILE__)) . '/languages');
        HRT_Settings::init();
        HRT_Ajax::init();
    }

    public function enqueue_assets() {
        wp_register_style('hrt-frontend', HRT_PLUGIN_URL . 'assets/css/frontend.css', [], HRT_PLUGIN_VERSION);
        wp_enqueue_style('hrt-frontend');

        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);

        wp_register_script('hrt-frontend', HRT_PLUGIN_URL . 'assets/js/frontend.js', ['jquery','stripe-js'], HRT_PLUGIN_VERSION, true);
        $local = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('hrt_ajax_nonce'),
            'stripe_pk'=> get_option('hr_stripe_pk', ''),
            'currency' => strtolower(get_option('hr_currency', 'PHP')),
            'payments_enabled' => (bool) get_option('hr_stripe_sk', ''),
        ];
        wp_localize_script('hrt-frontend', 'HRT_Ajax', $local);
        wp_enqueue_script('hrt-frontend');
    }

    /* ================== Shortcodes ================== */
    public function shortcode_search($atts = [], $content = '') {
        $q = new WP_Query([
            'post_type' => 'hrt_room_type',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        ob_start();
        ?>
        <div class="hrt-search">
            <form class="hrt-inline-form" onsubmit="return false;">
                <label>
                    <?php esc_html_e('Check-in', 'hotel-reservation-lite'); ?>
                    <input type="date" class="hrt-checkin" min="<?php echo esc_attr(date('Y-m-d')); ?>" />
                </label>
                <label>
                    <?php esc_html_e('Check-out', 'hotel-reservation-lite'); ?>
                    <input type="date" class="hrt-checkout" min="<?php echo esc_attr(date('Y-m-d', strtotime('+1 day'))); ?>" />
                </label>
                <button type="button" class="hrt-search-btn"><?php esc_html_e('Search Availability', 'hotel-reservation-lite'); ?></button>
                <span class="hrt-hint"><?php esc_html_e('Select dates to check each room type.', 'hotel-reservation-lite'); ?></span>
            </form>
            <div class="hrt-type-list">
                <?php if ($q->have_posts()) : while ($q->have_posts()) : $q->the_post();
                    $price = get_post_meta(get_the_ID(), 'hr_price_per_night', true);
                    $capacity = get_post_meta(get_the_ID(), 'hr_capacity', true);
                    $qty = get_post_meta(get_the_ID(), 'hr_quantity', true);
                ?>
                <div class="hrt-type" data-room-type-id="<?php echo esc_attr(get_the_ID()); ?>">
                    <div class="hrt-type-thumb"><?php echo get_the_post_thumbnail(get_the_ID(), 'medium'); ?></div>
                    <div class="hrt-type-body">
                        <h3 class="hrt-type-title"><?php the_title(); ?></h3>
                        <div class="hrt-type-excerpt"><?php the_excerpt(); ?></div>
                        <div class="hrt-type-meta">
                            <span class="hrt-price"><?php echo esc_html(hrt_format_price($price)); ?> / <?php esc_html_e('night (base)', 'hotel-reservation-lite'); ?></span>
                            <span class="hrt-capacity">👤 x <?php echo esc_html($capacity ? $capacity : '1'); ?></span>
                            <span class="hrt-qty">× <?php echo esc_html($qty ? $qty : '1'); ?> <?php esc_html_e('rooms', 'hotel-reservation-lite'); ?></span>
                        </div>
                        <div class="hrt-actions">
                            <button class="hrt-check-btn" type="button"><?php esc_html_e('Check availability', 'hotel-reservation-lite'); ?></button>
                            <a class="hrt-book-link" href="#" data-room-type-id="<?php echo esc_attr(get_the_ID()); ?>"><?php esc_html_e('Book now', 'hotel-reservation-lite'); ?></a>
                            <span class="hrt-availability-status"></span>
                        </div>
                    </div>
                </div>
                <?php endwhile; wp_reset_postdata(); else: ?>
                    <p><?php esc_html_e('No room types found.', 'hotel-reservation-lite'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function shortcode_booking($atts = [], $content = '') {
        $atts = shortcode_atts([
            'room_type_id' => 0,
        ], $atts, 'hotel_booking');
        $room_type_id = absint($atts['room_type_id']);
        if (!$room_type_id) { $room_type_id = get_the_ID(); }
        if (!$room_type_id || get_post_type($room_type_id) !== 'hrt_room_type') {
            return '<p>' . esc_html__('Invalid room type.', 'hotel-reservation-lite') . '</p>';
        }
        $price = get_post_meta($room_type_id, 'hr_price_per_night', true);
        ob_start();
        ?>
        <div class="hrt-booking" data-room-type-id="<?php echo esc_attr($room_type_id); ?>">
            <h3><?php echo esc_html(get_the_title($room_type_id)); ?></h3>
            <form class="hrt-booking-form" onsubmit="return false;">
                <div class="hrt-form-grid">
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
                    <div class="hrt-total">
                        <strong><?php esc_html_e('Estimated total:', 'hotel-reservation-lite'); ?></strong>
                        <span class="hrt-total-amount" data-price="<?php echo esc_attr($price); ?>"><?php echo esc_html(hrt_format_price($price)); ?></span>
                    </div>
                </div>
                <div class="hrt-payment">
                    <div class="hrt-pay-card" style="display:none;">
                        <label><strong><?php esc_html_e('Card details', 'hotel-reservation-lite'); ?></strong></label>
                        <div class="hrt-card-element" style="padding:10px;border:1px solid #ddd;border-radius:6px;background:#fff;"></div>
                    </div>
                </div>
                <div class="hrt-actions">
                    <button type="button" class="hrt-check-availability-btn"><?php esc_html_e('Check Availability', 'hotel-reservation-lite'); ?></button>
                    <button type="button" class="hrt-create-booking-btn" disabled><?php esc_html_e('Reserve (No Payment)', 'hotel-reservation-lite'); ?></button>
                    <button type="button" class="hrt-pay-reserve-btn" disabled style="display:none; background:#0ea5e9;color:#fff;border-color:#0284c7;">
                        <?php esc_html_e('Pay & Reserve (Card)', 'hotel-reservation-lite'); ?>
                    </button>
                    <span class="hrt-status"></span>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

new HRT_Plugin();
