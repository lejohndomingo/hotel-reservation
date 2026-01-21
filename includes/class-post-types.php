
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

        add_action('add_meta_boxes', function() {
            add_meta_box('hrt_type_meta', __('Room Type Details', 'hotel-reservation-lite'), ['HRT_Post_Types', 'render_type_meta'], 'hrt_room_type', 'normal', 'default');
        });
        add_action('save_post_hrt_room_type', ['HRT_Post_Types', 'save_type_meta']);
    }

    public static function render_type_meta($post) {
        wp_nonce_field('hrt_type_meta', 'hrt_type_meta_nonce');
        $price = get_post_meta($post->ID, 'hr_price_per_night', true);
        $capacity = get_post_meta($post->ID, 'hr_capacity', true);
        $quantity = get_post_meta($post->ID, 'hr_quantity', true);
        $weekly = get_post_meta($post->ID, 'hr_weekly_rate', true);
        $monthly = get_post_meta($post->ID, 'hr_monthly_rate', true);
        ?>
        <style>.hrt-season-table input, .hrt-closed-table input, .hrt-closed-table select{width:100%;}</style>
        <div class="postbox-inside">
        <p>
            <label for="hr_price_per_night"><strong><?php esc_html_e('Base price per night', 'hotel-reservation-lite'); ?></strong></label>
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
        <p>
            <label for="hr_weekly_rate"><strong><?php esc_html_e('Weekly rate (7 nights)', 'hotel-reservation-lite'); ?></strong></label>
            <input type="number" min="0" step="0.01" id="hr_weekly_rate" name="hr_weekly_rate" value="<?php echo esc_attr($weekly); ?>" class="widefat" />
        </p>
        <p>
            <label for="hr_monthly_rate"><strong><?php esc_html_e('Monthly rate (30 nights)', 'hotel-reservation-lite'); ?></strong></label>
            <input type="number" min="0" step="0.01" id="hr_monthly_rate" name="hr_monthly_rate" value="<?php echo esc_attr($monthly); ?>" class="widefat" />
        </p>
        <hr/>
        <p><strong><?php esc_html_e('Seasonal pricing', 'hotel-reservation-lite'); ?></strong></p>
        <p class="description"><?php esc_html_e('Add date ranges with a custom price per night. These override the base price for nights that fall in the range. End date is exclusive.', 'hotel-reservation-lite'); ?></p>
        <div id="hrt-seasons">
            <?php $seasons = get_post_meta($post->ID, 'hr_seasons', true); if (!is_array($seasons)) { $seasons = []; } ?>
            <table class="widefat fixed striped hrt-season-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Label (optional)', 'hotel-reservation-lite'); ?></th>
                        <th><?php esc_html_e('Start date', 'hotel-reservation-lite'); ?></th>
                        <th><?php esc_html_e('End date', 'hotel-reservation-lite'); ?></th>
                        <th><?php esc_html_e('Price / night', 'hotel-reservation-lite'); ?></th>
                        <th><?php esc_html_e('Min nights', 'hotel-reservation-lite'); ?></th>
                        <th><?php esc_html_e('Max nights', 'hotel-reservation-lite'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="hrt-seasons-body">
                    <?php foreach ($seasons as $idx => $row): ?>
                    <tr>
                        <td><input type="text" name="hr_season_label[]" value="<?php echo esc_attr($row['label'] ?? ''); ?>" /></td>
                        <td><input type="date" name="hr_season_start[]" value="<?php echo esc_attr($row['start'] ?? ''); ?>" /></td>
                        <td><input type="date" name="hr_season_end[]" value="<?php echo esc_attr($row['end'] ?? ''); ?>" /></td>
                        <td><input type="number" step="0.01" min="0" name="hr_season_price[]" value="<?php echo esc_attr($row['price'] ?? ''); ?>" /></td>
                        <td><input type="number" step="1" min="0" name="hr_season_min[]" value="<?php echo esc_attr($row['min'] ?? ''); ?>" /></td>
                        <td><input type="number" step="1" min="0" name="hr_season_max[]" value="<?php echo esc_attr($row['max'] ?? ''); ?>" /></td>
                        <td><button type="button" class="button hrt-remove-row">&times;</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="hrt-add-season"><?php esc_html_e('Add season', 'hotel-reservation-lite'); ?></button></p>
        </div>
        <script>
        (function(){
          var body = document.getElementById('hrt-seasons-body');
          var btn = document.getElementById('hrt-add-season');
          if(btn){ btn.addEventListener('click', function(){
            var tr = document.createElement('tr');
            tr.innerHTML = '              <td><input type="text" name="hr_season_label[]" /></td>              <td><input type="date" name="hr_season_start[]" /></td>              <td><input type="date" name="hr_season_end[]" /></td>              <td><input type="number" step="0.01" min="0" name="hr_season_price[]" /></td>              <td><input type="number" step="1" min="0" name="hr_season_min[]" /></td>              <td><input type="number" step="1" min="0" name="hr_season_max[]" /></td>              <td><button type="button" class="button hrt-remove-row">&times;</button></td>';
            body.appendChild(tr);
          }); }
          document.addEventListener('click', function(e){ if(e.target && e.target.classList.contains('hrt-remove-row')){ var tr = e.target.closest('tr'); if(tr) tr.remove(); } });
        })();
        </script>

        <hr/>
        <p><strong><?php esc_html_e('Closed-out dates', 'hotel-reservation-lite'); ?></strong></p>
        <p class="description"><?php esc_html_e('Block arrivals, departures, or stays for certain date ranges. End date is exclusive.', 'hotel-reservation-lite'); ?></p>
        <div id="hrt-closed">
            <?php $closed = get_post_meta($post->ID, 'hr_closed_out', true); if (!is_array($closed)) { $closed = []; } ?>
            <table class="widefat fixed striped hrt-closed-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Label (optional)', 'hotel-reservation-lite'); ?></th>
                        <th><?php esc_html_e('Start date', 'hotel-reservation-lite'); ?></th>
                        <th><?php esc_html_e('End date', 'hotel-reservation-lite'); ?></th>
                        <th><?php esc_html_e('Rule', 'hotel-reservation-lite'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="hrt-closed-body">
                    <?php foreach ($closed as $idx => $row): ?>
                    <tr>
                        <td><input type="text" name="hr_closed_label[]" value="<?php echo esc_attr($row['label'] ?? ''); ?>" /></td>
                        <td><input type="date" name="hr_closed_start[]" value="<?php echo esc_attr($row['start'] ?? ''); ?>" /></td>
                        <td><input type="date" name="hr_closed_end[]" value="<?php echo esc_attr($row['end'] ?? ''); ?>" /></td>
                        <td>
                            <select name="hr_closed_rule[]">
                                <?php $rule = $row['rule'] ?? 'no_stay'; ?>
                                <option value="no_arrival"  <?php selected($rule, 'no_arrival'); ?>><?php esc_html_e('No arrival', 'hotel-reservation-lite'); ?></option>
                                <option value="no_departure"<?php selected($rule, 'no_departure'); ?>><?php esc_html_e('No departure', 'hotel-reservation-lite'); ?></option>
                                <option value="no_stay"     <?php selected($rule, 'no_stay'); ?>><?php esc_html_e('No stay', 'hotel-reservation-lite'); ?></option>
                            </select>
                        </td>
                        <td><button type="button" class="button hrt-remove-row">&times;</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="hrt-add-closed"><?php esc_html_e('Add closed-out range', 'hotel-reservation-lite'); ?></button></p>
        </div>
        <script>
        (function(){
          var body = document.getElementById('hrt-closed-body');
          var btn = document.getElementById('hrt-add-closed');
          if(btn){ btn.addEventListener('click', function(){
            var tr = document.createElement('tr');
            tr.innerHTML = '              <td><input type="text" name="hr_closed_label[]" /></td>              <td><input type="date" name="hr_closed_start[]" /></td>              <td><input type="date" name="hr_closed_end[]" /></td>              <td>                <select name="hr_closed_rule[]">                  <option value="no_arrival"><?php echo esc_js(__('No arrival', 'hotel-reservation-lite')); ?></option>                  <option value="no_departure"><?php echo esc_js(__('No departure', 'hotel-reservation-lite')); ?></option>                  <option value="no_stay" selected><?php echo esc_js(__('No stay', 'hotel-reservation-lite')); ?></option>                </select>              </td>              <td><button type="button" class="button hrt-remove-row">&times;</button></td>';
            body.appendChild(tr);
          }); }
        })();
        </script>
        </div>
        <?php
    }

    public static function save_type_meta($post_id) {
        if (!isset($_POST['hrt_type_meta_nonce']) || !wp_verify_nonce($_POST['hrt_type_meta_nonce'], 'hrt_type_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $price = isset($_POST['hr_price_per_night']) ? floatval($_POST['hr_price_per_night']) : 0;
        $cap = isset($_POST['hr_capacity']) ? intval($_POST['hr_capacity']) : 1;
        $qty = isset($_POST['hr_quantity']) ? intval($_POST['hr_quantity']) : 1;
        $weekly = isset($_POST['hr_weekly_rate']) ? floatval($_POST['hr_weekly_rate']) : 0;
        $monthly = isset($_POST['hr_monthly_rate']) ? floatval($_POST['hr_monthly_rate']) : 0;
        update_post_meta($post_id, 'hr_price_per_night', $price);
        update_post_meta($post_id, 'hr_capacity', max(1, $cap));
        update_post_meta($post_id, 'hr_quantity', max(1, $qty));
        update_post_meta($post_id, 'hr_weekly_rate', max(0, $weekly));
        update_post_meta($post_id, 'hr_monthly_rate', max(0, $monthly));

        // Save seasons
        $labels = (array)($_POST['hr_season_label'] ?? []);
        $starts = (array)($_POST['hr_season_start'] ?? []);
        $ends   = (array)($_POST['hr_season_end'] ?? []);
        $prices = (array)($_POST['hr_season_price'] ?? []);
        $mins   = (array)($_POST['hr_season_min'] ?? []);
        $maxs   = (array)($_POST['hr_season_max'] ?? []);
        $seasons = [];
        $count = max(count($labels), count($starts), count($ends), count($prices), count($mins), count($maxs));
        for ($i=0; $i<$count; $i++) {
            $label = sanitize_text_field($labels[$i] ?? '');
            $s = sanitize_text_field($starts[$i] ?? '');
            $e = sanitize_text_field($ends[$i] ?? '');
            $p = floatval($prices[$i] ?? 0);
            $mn= intval($mins[$i] ?? 0);
            $mx= intval($maxs[$i] ?? 0);
            if ($s && $e && $p > 0) { $seasons[] = ['label'=>$label, 'start'=>$s, 'end'=>$e, 'price'=>$p, 'min'=>$mn>0?$mn:0, 'max'=>$mx>0?$mx:0]; }
        }
        update_post_meta($post_id, 'hr_seasons', $seasons);

        // Save closed-out
        $c_labels = (array)($_POST['hr_closed_label'] ?? []);
        $c_starts = (array)($_POST['hr_closed_start'] ?? []);
        $c_ends   = (array)($_POST['hr_closed_end'] ?? []);
        $c_rules  = (array)($_POST['hr_closed_rule'] ?? []);
        $closed = [];
        $ccount = max(count($c_labels), count($c_starts), count($c_ends), count($c_rules));
        for ($i=0; $i<$ccount; $i++) {
            $cl = sanitize_text_field($c_labels[$i] ?? '');
            $cs = sanitize_text_field($c_starts[$i] ?? '');
            $ce = sanitize_text_field($c_ends[$i] ?? '');
            $cr = sanitize_text_field($c_rules[$i] ?? 'no_stay');
            if ($cs && $ce) { $closed[] = ['label'=>$cl, 'start'=>$cs, 'end'=>$ce, 'rule'=>$cr]; }
        }
        update_post_meta($post_id, 'hr_closed_out', $closed);
    }

    private static function register_bookings() {
        $labels = [
            'name' => __('Bookings', 'hotel-reservation-lite'),
            'singular_name' => __('Booking', 'hotel-reservation-lite'),
            'add_new_item' => __('Add Booking', 'hotel-reservation-lite'),
            'edit_item' => __('Edit Booking', 'hotel-reservation-lite'),
            'menu_name' => __('Bookings', 'hotel-reservation-lite'),
        ];
        $args = [ 'labels'=>$labels,'public'=>false,'show_ui'=>true,'show_in_menu'=>true,'menu_icon'=>'dashicons-clipboard','supports'=>['title'] ];
        register_post_type('hrt_booking', $args);
        add_action('add_meta_boxes', function(){ add_meta_box('hrt_booking_meta', __('Booking Details', 'hotel-reservation-lite'), ['HRT_Post_Types','render_booking_meta'],'hrt_booking','normal','default'); });
        add_action('save_post_hrt_booking', ['HRT_Post_Types','save_booking_meta']);
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
        <p><label><strong><?php esc_html_e('Room Type', 'hotel-reservation-lite'); ?></strong></label>
        <?php $room_types = get_posts(['post_type'=>'hrt_room_type','numberposts'=>-1]); echo '<select name="hr_room_type_id">'; echo '<option value="0">'.esc_html__('— Select Room Type —','hotel-reservation-lite').'</option>'; foreach($room_types as $rt){ printf('<option value="%d" %s>%s</option>', $rt->ID, selected($type_id,$rt->ID,false), esc_html($rt->post_title)); } echo '</select>'; ?></p>
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
            <select name="hr_status"><?php $statuses=['pending'=>__('Pending','hotel-reservation-lite'),'pending_payment'=>__('Pending Payment','hotel-reservation-lite'),'confirmed'=>__('Confirmed','hotel-reservation-lite'),'cancelled'=>__('Cancelled','hotel-reservation-lite')]; foreach($statuses as $k=>$lbl){ printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($status,$k,false), esc_html($lbl)); } ?></select>
        </p>
        <?php
    }

    public static function save_booking_meta($post_id) {
        if (!isset($_POST['hrt_booking_meta_nonce']) || !wp_verify_nonce($_POST['hrt_booking_meta_nonce'], 'hrt_booking_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $fields = ['hr_room_type_id'=>'absint','hr_checkin'=>'sanitize_text_field','hr_checkout'=>'sanitize_text_field','hr_guest_name'=>'sanitize_text_field','hr_guest_email'=>'sanitize_email','hr_guest_phone'=>'sanitize_text_field','hr_total'=>'floatval','hr_status'=>'sanitize_text_field'];
        foreach($fields as $k=>$cb){ if(isset($_POST[$k])){ $val = call_user_func($cb, $_POST[$k]); update_post_meta($post_id,$k,$val); } }
        if (get_post_type($post_id) === 'hrt_booking') { wp_update_post(['ID'=>$post_id,'post_title'=>'Booking #'.$post_id]); }
    }
}
