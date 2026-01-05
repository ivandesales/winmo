<?php

if (!defined('ABSPATH')) {
    exit;
}

class Winmo_Operations
{
    public const POST_TYPE = 'winmo_operation';

    public function register()
    {
        $labels = [
            'name' => __('Operaciones', 'winmo-core'),
            'singular_name' => __('Operación', 'winmo-core'),
            'add_new' => __('Añadir nueva', 'winmo-core'),
            'add_new_item' => __('Añadir nueva operación', 'winmo-core'),
            'edit_item' => __('Editar operación', 'winmo-core'),
            'new_item' => __('Nueva operación', 'winmo-core'),
            'view_item' => __('Ver operación', 'winmo-core'),
            'search_items' => __('Buscar operaciones', 'winmo-core'),
            'not_found' => __('No se encontraron operaciones', 'winmo-core'),
            'not_found_in_trash' => __('No hay operaciones en la papelera', 'winmo-core'),
            'all_items' => __('Todas las operaciones', 'winmo-core'),
            'menu_name' => __('Operaciones', 'winmo-core'),
            'name_admin_bar' => __('Operación', 'winmo-core'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'rewrite' => ['slug' => 'operaciones'],
        ];

        register_post_type(self::POST_TYPE, $args);

        add_filter('rest_pre_insert_' . self::POST_TYPE, [$this, 'block_rest_if_no_license'], 10, 2);
        add_filter('post_row_actions', [$this, 'filter_row_actions'], 10, 2);
        add_action('admin_head-edit.php', [$this, 'maybe_hide_add_new_button']);
        add_filter('manage_edit-' . self::POST_TYPE . '_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'sortable_columns']);
        add_action('pre_get_posts', [$this, 'maybe_sort_by_datetime']);
        add_action('restrict_manage_posts', [$this, 'add_filters']);
        add_action('pre_get_posts', [$this, 'maybe_filter_by_client']);
    }

    public function register_meta()
    {
        register_post_meta(self::POST_TYPE, 'winmo_property_id', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [$this, 'auth_meta'],
        ]);

        register_post_meta(self::POST_TYPE, 'winmo_client_id', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [$this, 'auth_meta'],
        ]);

        $string_meta = [
            'winmo_op_type',
            'winmo_op_status',
            'winmo_op_datetime',
            'winmo_op_notes',
        ];

        foreach ($string_meta as $key) {
            register_post_meta(self::POST_TYPE, $key, [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_string'],
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => [$this, 'auth_meta'],
            ]);
        }
    }

    public function auth_meta()
    {
        return current_user_can('manage_options');
    }

    public function register_metabox()
    {
        add_meta_box(
            'winmo_operation_data',
            __('Datos de la operación', 'winmo-core'),
            [$this, 'render_metabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function render_metabox($post)
    {
        wp_nonce_field('winmo_operation_meta', 'winmo_operation_meta_nonce');

        $meta = [
            'winmo_property_id' => isset($_GET['winmo_property_id']) ? absint($_GET['winmo_property_id']) : get_post_meta($post->ID, 'winmo_property_id', true),
            'winmo_client_id' => isset($_GET['winmo_client_id']) ? absint($_GET['winmo_client_id']) : get_post_meta($post->ID, 'winmo_client_id', true),
            'winmo_op_type' => get_post_meta($post->ID, 'winmo_op_type', true),
            'winmo_op_status' => get_post_meta($post->ID, 'winmo_op_status', true),
            'winmo_op_datetime' => get_post_meta($post->ID, 'winmo_op_datetime', true),
            'winmo_op_notes' => get_post_meta($post->ID, 'winmo_op_notes', true),
        ];

        $types = [
            'visita' => __('Visita', 'winmo-core'),
            'oferta' => __('Oferta', 'winmo-core'),
            'reserva' => __('Reserva', 'winmo-core'),
            'cierre' => __('Cierre', 'winmo-core'),
            'cancelacion' => __('Cancelación', 'winmo-core'),
        ];

        $statuses = [
            'pendiente' => __('Pendiente', 'winmo-core'),
            'realizada' => __('Realizada', 'winmo-core'),
            'cancelada' => __('Cancelada', 'winmo-core'),
        ];

        $disabled = $this->is_premium_enabled() ? '' : 'disabled readonly aria-disabled="true"';

        $properties = $this->get_posts_for_select('winmo_property', 'winmo_ref');
        $clients = $this->get_posts_for_select('winmo_client');
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="winmo_property_id"><?php esc_html_e('Inmueble', 'winmo-core'); ?></label></th>
                    <td>
                        <select id="winmo_property_id" name="winmo_property_id" <?php echo $disabled; ?>>
                            <option value=""><?php esc_html_e('Seleccionar inmueble', 'winmo-core'); ?></option>
                            <?php foreach ($properties as $id => $label) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected((int) $meta['winmo_property_id'], (int) $id); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_client_id"><?php esc_html_e('Cliente', 'winmo-core'); ?></label></th>
                    <td>
                        <select id="winmo_client_id" name="winmo_client_id" <?php echo $disabled; ?>>
                            <option value=""><?php esc_html_e('Seleccionar cliente', 'winmo-core'); ?></option>
                            <?php foreach ($clients as $id => $label) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected((int) $meta['winmo_client_id'], (int) $id); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_op_type"><?php esc_html_e('Tipo', 'winmo-core'); ?></label></th>
                    <td>
                        <select id="winmo_op_type" name="winmo_op_type" <?php echo $disabled; ?>>
                            <option value=""><?php esc_html_e('Seleccionar', 'winmo-core'); ?></option>
                            <?php foreach ($types as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['winmo_op_type'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_op_status"><?php esc_html_e('Estado', 'winmo-core'); ?></label></th>
                    <td>
                        <select id="winmo_op_status" name="winmo_op_status" <?php echo $disabled; ?>>
                            <option value=""><?php esc_html_e('Seleccionar', 'winmo-core'); ?></option>
                            <?php foreach ($statuses as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['winmo_op_status'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_op_datetime"><?php esc_html_e('Fecha y hora', 'winmo-core'); ?></label></th>
                    <td><input type="datetime-local" id="winmo_op_datetime" name="winmo_op_datetime" value="<?php echo esc_attr($meta['winmo_op_datetime']); ?>" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_op_notes"><?php esc_html_e('Notas', 'winmo-core'); ?></label></th>
                    <td>
                        <textarea id="winmo_op_notes" name="winmo_op_notes" rows="4" class="large-text" <?php echo $disabled; ?>><?php echo esc_textarea($meta['winmo_op_notes']); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
        if (!$this->is_premium_enabled()) {
            echo '<p class="description">' . esc_html__('Licencia requerida para editar.', 'winmo-core') . '</p>';
        }
    }

    public function save_meta($post_id, $post, $update)
    {
        if (!$this->is_premium_enabled()) {
            add_filter('redirect_post_location', [$this, 'add_license_notice_query']);
            return;
        }

        if (!isset($_POST['winmo_operation_meta_nonce']) || !wp_verify_nonce($_POST['winmo_operation_meta_nonce'], 'winmo_operation_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $fields = [
            'winmo_property_id' => 'absint',
            'winmo_client_id' => 'absint',
            'winmo_op_type' => [$this, 'sanitize_op_type'],
            'winmo_op_status' => [$this, 'sanitize_op_status'],
            'winmo_op_datetime' => [$this, 'sanitize_string'],
            'winmo_op_notes' => [$this, 'sanitize_textarea'],
        ];

        foreach ($fields as $key => $callback) {
            if (!isset($_POST[$key])) {
                delete_post_meta($post_id, $key);
                continue;
            }

            $value = is_callable($callback) ? call_user_func($callback, wp_unslash($_POST[$key])) : null;
            if ($callback === 'absint') {
                $value = absint($_POST[$key]);
            }

            if ($value === null || $value === '') {
                delete_post_meta($post_id, $key);
            } else {
                update_post_meta($post_id, $key, $value);
            }
        }
    }

    public function block_new_if_no_license()
    {
        global $pagenow;
        if ($pagenow !== 'post-new.php') {
            return;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        if ($this->is_premium_enabled()) {
            return;
        }

        wp_safe_redirect(add_query_arg(['post_type' => self::POST_TYPE, 'winmo_license_required' => 1], admin_url('edit.php')));
        exit;
    }

    public function maybe_lock_post_data($data, $postarr)
    {
        if ($data['post_type'] !== self::POST_TYPE) {
            return $data;
        }

        if ($this->is_premium_enabled()) {
            return $data;
        }

        if (!empty($postarr['ID'])) {
            $existing = get_post((int) $postarr['ID']);
            if ($existing) {
                $data['post_title'] = $existing->post_title;
                $data['post_content'] = $existing->post_content;
                $data['post_excerpt'] = $existing->post_excerpt;
                $data['post_status'] = $existing->post_status;
            }
        }

        return $data;
    }

    public function maybe_show_edit_notice()
    {
        if (!isset($_GET['winmo_license_required'])) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }

        $message = __('Licencia requerida para editar.', 'winmo-core');
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }

    public function add_license_notice_query($location)
    {
        return add_query_arg('winmo_license_required', 1, $location);
    }

    public function enqueue_admin_assets($hook)
    {
        global $post_type;
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        if (!$this->is_premium_enabled()) {
            wp_enqueue_style('winmo-admin', WINMO_CORE_URL . 'assets/admin.css', [], WINMO_CORE_VERSION);
        }
    }

    public function filter_admin_body_class($classes)
    {
        global $post_type;
        if ($post_type === self::POST_TYPE && !$this->is_premium_enabled()) {
            $classes .= ' winmo-locked';
        }

        return $classes;
    }

    public function block_rest_if_no_license($prepared_post, $request)
    {
        if ($this->is_premium_enabled()) {
            return $prepared_post;
        }

        return new WP_Error(
            'winmo_license_required',
            __('Licencia requerida para editar.', 'winmo-core'),
            ['status' => 403]
        );
    }

    public function filter_row_actions($actions, $post)
    {
        if ($post->post_type !== self::POST_TYPE) {
            return $actions;
        }

        if ($this->is_premium_enabled()) {
            return $actions;
        }

        unset($actions['edit'], $actions['inline hide-if-no-js'], $actions['trash']);

        return $actions;
    }

    public function maybe_hide_add_new_button()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }

        if ($this->is_premium_enabled()) {
            return;
        }

        echo '<style>.page-title-action{display:none !important;}</style>';
    }

    public function is_premium_enabled()
    {
        if (function_exists('winmo_premium_enabled')) {
            return (bool) winmo_premium_enabled();
        }

        return true;
    }

    public function sanitize_string($value)
    {
        return sanitize_text_field($value);
    }

    public function sanitize_textarea($value)
    {
        return sanitize_textarea_field($value);
    }

    public function sanitize_op_type($value)
    {
        $value = sanitize_text_field($value);
        $allowed = ['visita', 'oferta', 'reserva', 'cierre', 'cancelacion'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    public function sanitize_op_status($value)
    {
        $value = sanitize_text_field($value);
        $allowed = ['pendiente', 'realizada', 'cancelada'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    public function columns($columns)
    {
        $new = [];
        $new['cb'] = $columns['cb'];
        $new['title'] = __('Título', 'winmo-core');
        $new['winmo_property'] = __('Inmueble', 'winmo-core');
        $new['winmo_client'] = __('Cliente', 'winmo-core');
        $new['winmo_op_type'] = __('Tipo', 'winmo-core');
        $new['winmo_op_status'] = __('Estado', 'winmo-core');
        $new['winmo_op_datetime'] = __('Fecha/Hora', 'winmo-core');
        return $new;
    }

    public function render_column($column, $post_id)
    {
        switch ($column) {
            case 'winmo_property':
                $property_id = (int) get_post_meta($post_id, 'winmo_property_id', true);
                if ($property_id) {
                    $label = $this->get_post_label($property_id, 'winmo_ref');
                    $link = get_edit_post_link($property_id);
                    echo $link ? '<a href="' . esc_url($link) . '">' . esc_html($label) . '</a>' : esc_html($label);
                } else {
                    esc_html_e('—', 'winmo-core');
                }
                break;
            case 'winmo_client':
                $client_id = (int) get_post_meta($post_id, 'winmo_client_id', true);
                if ($client_id) {
                    $label = $this->get_post_label($client_id);
                    $link = get_edit_post_link($client_id);
                    echo $link ? '<a href="' . esc_url($link) . '">' . esc_html($label) . '</a>' : esc_html($label);
                } else {
                    esc_html_e('—', 'winmo-core');
                }
                break;
            case 'winmo_op_type':
                $type = get_post_meta($post_id, 'winmo_op_type', true);
                $types = $this->get_op_types();
                echo isset($types[$type]) ? esc_html($types[$type]) : esc_html__('—', 'winmo-core');
                break;
            case 'winmo_op_status':
                $status = get_post_meta($post_id, 'winmo_op_status', true);
                $statuses = $this->get_op_statuses();
                echo isset($statuses[$status]) ? esc_html($statuses[$status]) : esc_html__('—', 'winmo-core');
                break;
            case 'winmo_op_datetime':
                $datetime = get_post_meta($post_id, 'winmo_op_datetime', true);
                $ts = $datetime ? strtotime($datetime) : false;
                echo $ts ? esc_html(date_i18n('d/m/Y H:i', $ts)) : esc_html__('—', 'winmo-core');
                break;
        }
    }

    public function sortable_columns($columns)
    {
        $columns['winmo_op_datetime'] = 'winmo_op_datetime';
        return $columns;
    }

    public function maybe_sort_by_datetime($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $post_type = $query->get('post_type');
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        if ($query->get('orderby') === 'winmo_op_datetime') {
            $query->set('meta_key', 'winmo_op_datetime');
            $query->set('orderby', 'meta_value');
        }

        $type_filter = isset($_GET['filter_op_type']) ? sanitize_text_field(wp_unslash($_GET['filter_op_type'])) : '';
        $status_filter = isset($_GET['filter_op_status']) ? sanitize_text_field(wp_unslash($_GET['filter_op_status'])) : '';
        $client_filter = isset($_GET['filter_op_client']) ? absint($_GET['filter_op_client']) : 0;

        $meta_query = [];
        if ($type_filter !== '') {
            $meta_query[] = [
                'key' => 'winmo_op_type',
                'value' => $type_filter,
                'compare' => '=',
            ];
        }
        if ($status_filter !== '') {
            $meta_query[] = [
                'key' => 'winmo_op_status',
                'value' => $status_filter,
                'compare' => '=',
            ];
        }
        if ($client_filter) {
            $meta_query[] = [
                'key' => 'winmo_client_id',
                'value' => $client_filter,
                'compare' => '=',
            ];
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }

    public function add_filters($post_type)
    {
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        $types = $this->get_op_types();
        $statuses = $this->get_op_statuses();
        $clients = $this->get_posts_for_select('winmo_client');

        $current_type = isset($_GET['filter_op_type']) ? sanitize_text_field(wp_unslash($_GET['filter_op_type'])) : '';
        $current_status = isset($_GET['filter_op_status']) ? sanitize_text_field(wp_unslash($_GET['filter_op_status'])) : '';
        $current_client = isset($_GET['filter_op_client']) ? absint($_GET['filter_op_client']) : 0;
        ?>
        <select name="filter_op_type">
            <option value=""><?php esc_html_e('Todos los tipos', 'winmo-core'); ?></option>
            <?php foreach ($types as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_type, $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_op_status">
            <option value=""><?php esc_html_e('Todos los estados', 'winmo-core'); ?></option>
            <?php foreach ($statuses as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_op_client">
            <option value="0"><?php esc_html_e('Todos los clientes', 'winmo-core'); ?></option>
            <?php foreach ($clients as $id => $label) : ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($current_client, $id); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function maybe_filter_by_client($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        $client_filter = isset($_GET['filter_op_client']) ? absint($_GET['filter_op_client']) : 0;
        if ($client_filter) {
            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = [
                'key' => 'winmo_client_id',
                'value' => $client_filter,
                'compare' => '=',
            ];
            $query->set('meta_query', $meta_query);
        }
    }

    private function get_op_types()
    {
        return [
            'visita' => __('Visita', 'winmo-core'),
            'oferta' => __('Oferta', 'winmo-core'),
            'reserva' => __('Reserva', 'winmo-core'),
            'cierre' => __('Cierre', 'winmo-core'),
            'cancelacion' => __('Cancelación', 'winmo-core'),
        ];
    }

    private function get_op_statuses()
    {
        return [
            'pendiente' => __('Pendiente', 'winmo-core'),
            'realizada' => __('Realizada', 'winmo-core'),
            'cancelada' => __('Cancelada', 'winmo-core'),
        ];
    }

    private function get_post_label($post_id, $ref_meta_key = '')
    {
        $title = get_the_title($post_id);
        if ($ref_meta_key) {
            $ref = get_post_meta($post_id, $ref_meta_key, true);
            if ($ref) {
                $title = sprintf('%s — %s', $ref, $title);
            }
        }

        return $title ? $title : sprintf(__('ID: %d', 'winmo-core'), $post_id);
    }

    private function get_posts_for_select($post_type, $ref_meta_key = '')
    {
        $items = [];
        $query = new WP_Query([
            'post_type' => $post_type,
            'posts_per_page' => 200,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        if (!empty($query->posts)) {
            foreach ($query->posts as $post_id) {
                $title = get_the_title($post_id);
                if ($ref_meta_key) {
                    $ref = get_post_meta($post_id, $ref_meta_key, true);
                    if ($ref) {
                        $title = sprintf('%s — %s', $ref, $title);
                    }
                }
                $items[$post_id] = $title;
            }
        }

        return $items;
    }
}
