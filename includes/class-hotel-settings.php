
<?php
if (!defined('ABSPATH')) { exit; }

class HR_Settings {
    public static function init() {
        add_action('admin_menu', ['HR_Settings', 'menu']);
        add_action('admin_init', ['HR_Settings', 'settings']);
    }

    public static function menu() {
        add_options_page(
            __('Hotel Reservation', 'hotel-reservation-lite'),
            __('Hotel Reservation', 'hotel-reservation-lite'),
            'manage_options',
            'hr-settings',
            ['HR_Settings', 'render']
        );
    }

    public static function settings() {
        register_setting('hr_settings_group', 'hr_currency', [ 'type' => 'string', 'default' => 'PHP', 'sanitize_callback' => 'sanitize_text_field' ]);
        register_setting('hr_settings_group', 'hr_price_decimals', [ 'type' => 'integer', 'default' => 2, 'sanitize_callback' => 'absint' ]);
        register_setting('hr_settings_group', 'hr_stripe_pk', [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ]);
        register_setting('hr_settings_group', 'hr_stripe_sk', [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ]);
        register_setting('hr_settings_group', 'hr_stripe_webhook_secret', [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ]);

        add_settings_section('hr_main', __('General', 'hotel-reservation-lite'), function() {
            echo '<p>' . esc_html__('Configure currency and formatting.', 'hotel-reservation-lite') . '</p>';
        }, 'hr-settings');

        add_settings_field('hr_currency_field', __('Currency', 'hotel-reservation-lite'), function() {
            $val = get_option('hr_currency', 'PHP');
            ?>
            <select name="hr_currency">
                <?php foreach (['PHP','USD','EUR','GBP','JPY'] as $c) { printf('<option value="%s" %s>%s</option>', esc_attr($c), selected($val, $c, false), esc_html($c)); } ?>
            </select>
            <?php
        }, 'hr-settings', 'hr_main');

        add_settings_field('hr_price_decimals_field', __('Price decimals', 'hotel-reservation-lite'), function() {
            $val = get_option('hr_price_decimals', 2);
            printf('<input type="number" name="hr_price_decimals" min="0" max="4" value="%d" />', (int) $val);
        }, 'hr-settings', 'hr_main');

        add_settings_section('hr_pay', __('Stripe Payments', 'hotel-reservation-lite'), function(){
            echo '<p>' . esc_html__('Enable card payments with Stripe. Use test keys in development.', 'hotel-reservation-lite') . '</p>';
        }, 'hr-settings');

        add_settings_field('hr_stripe_pk_field', __('Publishable key', 'hotel-reservation-lite'), function(){
            $val = get_option('hr_stripe_pk', '');
            printf('<input type="text" name="hr_stripe_pk" value="%s" class="regular-text" placeholder="pk_live_... or pk_test_..." />', esc_attr($val));
        }, 'hr-settings', 'hr_pay');

        add_settings_field('hr_stripe_sk_field', __('Secret key', 'hotel-reservation-lite'), function(){
            $val = get_option('hr_stripe_sk', '');
            printf('<input type="password" name="hr_stripe_sk" value="%s" class="regular-text" placeholder="sk_live_... or sk_test_..." />', esc_attr($val));
        }, 'hr-settings', 'hr_pay');

        add_settings_field('hr_stripe_webhook_secret_field', __('Webhook signing secret (optional)', 'hotel-reservation-lite'), function(){
            $val = get_option('hr_stripe_webhook_secret', '');
            printf('<input type="password" name="hr_stripe_webhook_secret" value="%s" class="regular-text" placeholder="whsec_..." />', esc_attr($val));
            echo '<p class="description">' . esc_html__('Set your webhook to /wp-json/hr/v1/stripe/webhook', 'hotel-reservation-lite') . '</p>';
        }, 'hr-settings', 'hr_pay');
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Hotel Reservation Settings', 'hotel-reservation-lite') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('hr_settings_group');
        do_settings_sections('hr-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
