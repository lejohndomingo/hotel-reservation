
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
        register_setting('hr_settings_group', 'hr_currency', [
            'type' => 'string', 'default' => 'PHP', 'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('hr_settings_group', 'hr_price_decimals', [
            'type' => 'integer', 'default' => 2, 'sanitize_callback' => 'absint'
        ]);

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
