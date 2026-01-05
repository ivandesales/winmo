<?php

if (!defined('ABSPATH')) {
    exit;
}

class Winmo_Clients
{
    public const POST_TYPE = 'winmo_client';
    private const PER_PAGE = 20;

    public function register()
    {
        $labels = [
            'name' => __('Clientes', 'winmo-core'),
            'singular_name' => __('Cliente', 'winmo-core'),
            'add_new' => __('Añadir nuevo', 'winmo-core'),
            'add_new_item' => __('Añadir nuevo cliente', 'winmo-core'),
            'edit_item' => __('Editar cliente', 'winmo-core'),
            'new_item' => __('Nuevo cliente', 'winmo-core'),
            'view_item' => __('Ver cliente', 'winmo-core'),
            'search_items' => __('Buscar clientes', 'winmo-core'),
            'not_found' => __('No se encontraron clientes', 'winmo-core'),
            'not_found_in_trash' => __('No hay clientes en la papelera', 'winmo-core'),
            'all_items' => __('Todos los clientes', 'winmo-core'),
            'menu_name' => __('Clientes', 'winmo-core'),
            'name_admin_bar' => __('Cliente', 'winmo-core'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'supports' => ['title', 'editor'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ];

        register_post_type(self::POST_TYPE, $args);

        add_filter('rest_pre_insert_' . self::POST_TYPE, [$this, 'block_rest_if_no_license'], 10, 2);
        add_filter('post_row_actions', [$this, 'filter_row_actions'], 10, 2);
        add_action('admin_head-edit.php', [$this, 'maybe_hide_add_new_button']);
        add_filter('manage_edit-' . self::POST_TYPE . '_columns', [$this, 'register_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_column'], 10, 2);
    }

    public function register_meta()
    {
        $string_meta = [
            'winmo_client_type',
            'winmo_client_email',
            'winmo_client_phone',
            'winmo_client_notes',
        ];

        foreach ($string_meta as $key) {
            register_post_meta(self::POST_TYPE, $key, [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_meta'],
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
            'winmo_client_data',
            __('Datos del cliente', 'winmo-core'),
            [$this, 'render_metabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'winmo_client_properties',
            __('Inmuebles del cliente', 'winmo-core'),
            [$this, 'render_properties_metabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'winmo_client_operations',
            __('Operaciones del cliente', 'winmo-core'),
            [$this, 'render_operations_metabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function render_metabox($post)
    {
        wp_nonce_field('winmo_client_meta', 'winmo_client_meta_nonce');
        $meta = [
            'winmo_client_type' => get_post_meta($post->ID, 'winmo_client_type', true),
            'winmo_client_email' => get_post_meta($post->ID, 'winmo_client_email', true),
            'winmo_client_phone' => get_post_meta($post->ID, 'winmo_client_phone', true),
            'winmo_client_notes' => get_post_meta($post->ID, 'winmo_client_notes', true),
        ];

        $types = [
            'owner' => __('Propietario', 'winmo-core'),
            'buyer' => __('Comprador', 'winmo-core'),
            'tenant' => __('Inquilino', 'winmo-core'),
            'investor' => __('Inversor', 'winmo-core'),
        ];

        $disabled = $this->is_premium_enabled() ? '' : 'disabled readonly aria-disabled="true"';
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="winmo_client_type"><?php esc_html_e('Tipo de cliente', 'winmo-core'); ?></label></th>
                    <td>
                        <select id="winmo_client_type" name="winmo_client_type" <?php echo $disabled; ?>>
                            <option value=""><?php esc_html_e('Seleccionar', 'winmo-core'); ?></option>
                            <?php foreach ($types as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['winmo_client_type'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="winmo_client_email"><?php esc_html_e('Email', 'winmo-core'); ?></label></th>
                    <td><input type="email" id="winmo_client_email" name="winmo_client_email" value="<?php echo esc_attr($meta['winmo_client_email']); ?>" class="regular-text" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_client_phone"><?php esc_html_e('Teléfono', 'winmo-core'); ?></label></th>
                    <td><input type="text" id="winmo_client_phone" name="winmo_client_phone" value="<?php echo esc_attr($meta['winmo_client_phone']); ?>" class="regular-text" <?php echo $disabled; ?> /></td>
                </tr>
                <tr>
                    <th><label for="winmo_client_notes"><?php esc_html_e('Notas', 'winmo-core'); ?></label></th>
                    <td>
                        <textarea id="winmo_client_notes" name="winmo_client_notes" rows="4" class="large-text" <?php echo $disabled; ?>><?php echo esc_textarea($meta['winmo_client_notes']); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
        if (!$this->is_premium_enabled()) {
            echo '<p class="description">' . esc_html__('Licencia requerida para editar.', 'winmo-core') . '</p>';
        }
    }

    /**
     * Renderiza el listado de inmuebles asociados al cliente.
     */
    public function render_properties_metabox($post)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $paged = isset($_GET['winmo_prop_page']) ? max(1, absint($_GET['winmo_prop_page'])) : 1;
        $query = $this->query_properties($post->ID, $paged, self::PER_PAGE);
        $properties = $query->posts;
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Ref.', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Título', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Operación', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Tipo', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Precio', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Ubicación', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Estado', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Publicado', 'winmo-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($properties)) : ?>
                    <?php foreach ($properties as $p) : ?>
                        <?php
                        $ref = get_post_meta($p->ID, 'winmo_ref', true);
                        $operation = get_post_meta($p->ID, 'winmo_operation', true);
                        $type = get_post_meta($p->ID, 'winmo_type', true);
                        $price = get_post_meta($p->ID, 'winmo_price', true);
                        $city = get_post_meta($p->ID, 'winmo_city', true);
                        $province = get_post_meta($p->ID, 'winmo_province', true);
                        $country = get_post_meta($p->ID, 'winmo_country', true);
                        $status = get_post_meta($p->ID, 'winmo_status', true);
                        $location_parts = array_filter([$city, $province, $country]);
                        $location = !empty($location_parts) ? implode(', ', $location_parts) : '—';
                        $op_labels = [
                            'venta' => __('Venta', 'winmo-core'),
                            'alquiler' => __('Alquiler', 'winmo-core'),
                        ];
                        $type_labels = [
                            'piso' => __('Piso', 'winmo-core'),
                            'casa' => __('Casa', 'winmo-core'),
                            'local' => __('Local', 'winmo-core'),
                            'terreno' => __('Terreno', 'winmo-core'),
                            'otro' => __('Otro', 'winmo-core'),
                        ];
                        $status_labels = [
                            'activo' => __('Activo', 'winmo-core'),
                            'reservado' => __('Reservado', 'winmo-core'),
                            'vendido' => __('Vendido', 'winmo-core'),
                        ];
                        ?>
                        <tr>
                            <td><?php echo $ref ? esc_html($ref) : esc_html__('—', 'winmo-core'); ?></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link($p->ID)); ?>"><?php echo esc_html(get_the_title($p->ID)); ?></a></td>
                            <td><?php echo isset($op_labels[$operation]) ? esc_html($op_labels[$operation]) : esc_html__('—', 'winmo-core'); ?></td>
                            <td><?php echo isset($type_labels[$type]) ? esc_html($type_labels[$type]) : esc_html__('—', 'winmo-core'); ?></td>
                            <td><?php echo $price !== '' ? esc_html(number_format_i18n((float) $price, 0)) : esc_html__('—', 'winmo-core'); ?></td>
                            <td><?php echo esc_html($location); ?></td>
                            <td><?php echo isset($status_labels[$status]) ? esc_html($status_labels[$status]) : esc_html__('—', 'winmo-core'); ?></td>
                            <?php $status_obj = get_post_status_object($p->post_status); ?>
                            <td><?php echo esc_html($status_obj ? $status_obj->label : $p->post_status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td><?php esc_html_e('No hay inmuebles asociados.', 'winmo-core'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <p class="description"><?php printf(esc_html__('Se muestran los últimos %d.', 'winmo-core'), self::PER_PAGE); ?></p>
        <?php if ($this->is_premium_enabled()) : ?>
            <p><a class="button" href="<?php echo esc_url(admin_url('post-new.php?post_type=winmo_property&winmo_owner_id=' . (int) $post->ID)); ?>"><?php esc_html_e('Crear inmueble para este cliente', 'winmo-core'); ?></a></p>
        <?php else : ?>
            <p class="description"><?php esc_html_e('Licencia requerida para crear nuevos inmuebles.', 'winmo-core'); ?></p>
        <?php endif; ?>
        <p><a href="<?php echo esc_url(add_query_arg(['post_type' => 'winmo_property', 'winmo_owner_id' => (int) $post->ID], admin_url('edit.php'))); ?>"><?php esc_html_e('Ver todos', 'winmo-core'); ?></a></p>
        <?php
    }

    /**
     * Renderiza el listado de operaciones asociadas al cliente.
     */
    public function render_operations_metabox($post)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $paged = isset($_GET['winmo_op_page']) ? max(1, absint($_GET['winmo_op_page'])) : 1;
        $query = $this->query_operations($post->ID, $paged, self::PER_PAGE);
        $operations = $query->posts;
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Tipo', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Fecha/Hora', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Inmueble', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Estado', 'winmo-core'); ?></th>
                    <th><?php esc_html_e('Acciones', 'winmo-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($operations)) : ?>
                    <?php foreach ($operations as $op) : ?>
                        <?php
                        $type = get_post_meta($op->ID, 'winmo_op_type', true);
                        $status = get_post_meta($op->ID, 'winmo_op_status', true);
                        $datetime = get_post_meta($op->ID, 'winmo_op_datetime', true);
                        $property_id = (int) get_post_meta($op->ID, 'winmo_property_id', true);
                        $type_labels = [
                            'visita' => __('Visita', 'winmo-core'),
                            'oferta' => __('Oferta', 'winmo-core'),
                            'reserva' => __('Reserva', 'winmo-core'),
                            'cierre' => __('Cierre', 'winmo-core'),
                            'cancelacion' => __('Cancelación', 'winmo-core'),
                        ];
                        $status_labels = [
                            'pendiente' => __('Pendiente', 'winmo-core'),
                            'realizada' => __('Realizada', 'winmo-core'),
                            'cancelada' => __('Cancelada', 'winmo-core'),
                        ];
                        $date_display = $datetime ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($datetime)) : date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($op->post_date));
                        ?>
                        <tr>
                            <td><?php echo isset($type_labels[$type]) ? esc_html($type_labels[$type]) : esc_html__('—', 'winmo-core'); ?></td>
                            <td><?php echo esc_html($date_display); ?></td>
                            <td>
                                <?php if ($property_id) : ?>
                                    <?php $prop_link = get_edit_post_link($property_id); ?>
                                    <?php echo $prop_link ? '<a href="' . esc_url($prop_link) . '">' . esc_html(get_the_title($property_id)) . '</a>' : esc_html(get_the_title($property_id)); ?>
                                <?php else : ?>
                                    <?php esc_html_e('—', 'winmo-core'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo isset($status_labels[$status]) ? esc_html($status_labels[$status]) : esc_html__('—', 'winmo-core'); ?></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link($op->ID)); ?>"><?php esc_html_e('Editar', 'winmo-core'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td><?php esc_html_e('No hay operaciones asociadas.', 'winmo-core'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <p class="description"><?php printf(esc_html__('Se muestran las últimas %d.', 'winmo-core'), self::PER_PAGE); ?></p>
        <p><a href="<?php echo esc_url(add_query_arg(['post_type' => 'winmo_operation', 'winmo_client_id' => (int) $post->ID], admin_url('edit.php'))); ?>"><?php esc_html_e('Ver todas', 'winmo-core'); ?></a></p>
        <?php
    }

    public function save_meta($post_id, $post, $update)
    {
        if (!$this->is_premium_enabled()) {
            add_filter('redirect_post_location', [$this, 'add_license_notice_query']);
            return;
        }

        if (!isset($_POST['winmo_client_meta_nonce']) || !wp_verify_nonce($_POST['winmo_client_meta_nonce'], 'winmo_client_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $fields = [
            'winmo_client_type' => [$this, 'sanitize_client_type'],
            'winmo_client_email' => [$this, 'sanitize_email'],
            'winmo_client_phone' => [$this, 'sanitize_meta'],
            'winmo_client_notes' => [$this, 'sanitize_textarea'],
        ];

        foreach ($fields as $key => $callback) {
            if (!isset($_POST[$key])) {
                delete_post_meta($post_id, $key);
                continue;
            }

            $value = call_user_func($callback, wp_unslash($_POST[$key]));
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

    public function register_columns($columns)
    {
        $new = [];
        $new['cb'] = $columns['cb'];
        $new['title'] = __('Nombre', 'winmo-core');
        $new['winmo_properties'] = __('Inmuebles', 'winmo-core');
        $new['winmo_operations'] = __('Operaciones', 'winmo-core');
        return $new;
    }

    public function render_column($column, $post_id)
    {
        switch ($column) {
            case 'winmo_properties':
                $count = $this->count_related('winmo_property', 'winmo_owner_id', $post_id);
                $url = add_query_arg(['post_type' => 'winmo_property', 'winmo_owner_id' => (int) $post_id], admin_url('edit.php'));
                echo '<a href="' . esc_url($url) . '">' . esc_html($count) . '</a>';
                break;
            case 'winmo_operations':
                $count = $this->count_related('winmo_operation', 'winmo_client_id', $post_id);
                $url = add_query_arg(['post_type' => 'winmo_operation', 'winmo_client_id' => (int) $post_id], admin_url('edit.php'));
                echo '<a href="' . esc_url($url) . '">' . esc_html($count) . '</a>';
                break;
        }
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

    public function sanitize_meta($value)
    {
        return sanitize_text_field($value);
    }

    public function sanitize_textarea($value)
    {
        return sanitize_textarea_field($value);
    }

    public function sanitize_client_type($value)
    {
        $value = sanitize_text_field($value);
        $allowed = ['owner', 'buyer', 'tenant', 'investor'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    public function sanitize_email($value)
    {
        $email = sanitize_email($value);
        return $email ? $email : '';
    }

    private function query_properties($client_id, $paged = 1, $per_page = self::PER_PAGE)
    {
        return new WP_Query([
            'post_type' => 'winmo_property',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => ['publish', 'draft', 'pending'],
            'meta_query' => [
                [
                    'key' => 'winmo_owner_id',
                    'value' => (int) $client_id,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);
    }

    private function query_operations($client_id, $paged = 1, $per_page = self::PER_PAGE)
    {
        return new WP_Query([
            'post_type' => 'winmo_operation',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => ['publish', 'draft', 'pending'],
            'meta_query' => [
                [
                    'key' => 'winmo_client_id',
                    'value' => (int) $client_id,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }

    private function count_related($post_type, $meta_key, $meta_value)
    {
        $query = new WP_Query([
            'post_type' => $post_type,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post_status' => ['publish', 'draft', 'pending'],
            'meta_query' => [
                [
                    'key' => $meta_key,
                    'value' => (int) $meta_value,
                    'compare' => '=',
                ],
            ],
            'no_found_rows' => false,
        ]);

        return (int) $query->found_posts;
    }
}
