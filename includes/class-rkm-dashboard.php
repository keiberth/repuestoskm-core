<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Dashboard {

    const BCV_USD_RATE_TRANSIENT = 'rkm_bcv_usd_rate_alcambio_v1';
    const BCV_USD_RATE_TTL = 21600;
    const ALCAMBIO_GRAPHQL_URL = 'https://api.alcambio.app/graphql';
    const ALCAMBIO_PUBLIC_URL = 'https://alcambio.app/';
    const BCV_USD_RATE_URL = 'https://www.bcv.org.ve/';
    const DOLARAPI_BCV_USD_RATE_URL = 'https://dolarapi.com/v1/dolares/oficial';

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_account_panel_endpoint', [$this, 'render_dashboard']);

        add_filter('woocommerce_account_menu_items', [$this, 'remove_woocommerce_menu']);
    }

    public function remove_woocommerce_menu($items) {
        return [];
    }

    public function enqueue_assets() {
        if (!is_user_logged_in()) {
            return;
        }

        if (!(function_exists('is_account_page') && is_account_page())) {
            return;
        }

        $section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'panel';

        wp_enqueue_style(
            'rkm-base-css',
            RKM_CORE_URL . 'assets/css/base.css',
            [],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-layout-css',
            RKM_CORE_URL . 'assets/css/layout.css',
            ['rkm-base-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-components-css',
            RKM_CORE_URL . 'assets/css/components.css',
            ['rkm-layout-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-responsive-css',
            RKM_CORE_URL . 'assets/css/responsive.css',
            ['rkm-components-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-dashboard-css',
            RKM_CORE_URL . 'assets/css/dashboard.css',
            ['rkm-responsive-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-orders-css',
            RKM_CORE_URL . 'assets/css/orders.css',
            ['rkm-dashboard-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-pedidos-css',
            RKM_CORE_URL . 'assets/css/pedidos.css',
            ['rkm-orders-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-catalogo-css',
            RKM_CORE_URL . 'assets/css/catalogo.css',
            ['rkm-pedidos-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-orders-js',
            RKM_CORE_URL . 'assets/js/orders.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'rkm-orders-js',
            'rkmOrders',
            [
                'ajax_url'        => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('rkm_orders_nonce'),
                'orders_url'      => wc_get_account_endpoint_url('orders'),
                'current_user_id' => get_current_user_id(),
            ]
        );

        wp_enqueue_script(
            'rkm-pedidos-js',
            RKM_CORE_URL . 'assets/js/pedidos.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'rkm-pedidos-js',
            'rkmPedidos',
            [
                'cancelable_statuses' => class_exists('RKM_Orders_Actions')
                    ? RKM_Orders_Actions::get_cancelable_statuses()
                    : ['pending', 'on-hold', 'processing'],
            ]
        );

        wp_enqueue_script(
            'rkm-private-header-js',
            RKM_CORE_URL . 'assets/js/private-header.js',
            [],
            '1.0.0',
            true
        );

        if ($section === 'nueva-orden') {
            wp_enqueue_script(
                'rkm-catalog-filters-js',
                RKM_CORE_URL . 'assets/js/catalog-filters.js',
                [],
                '1.0.0',
                true
            );

            wp_enqueue_script(
                'rkm-product-quick-view-js',
                RKM_CORE_URL . 'assets/js/product-quick-view.js',
                [],
                '1.0.0',
                true
            );
        }
    }

    public function render_dashboard() {
        $user = wp_get_current_user();
        $bcv_rate = $this->get_bcv_usd_rate();
        $section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'panel';
        $data = $this->get_view_context($user, $bcv_rate);

        switch ($section) {
            case 'mi-cuenta':
                $template = RKM_CORE_PATH . 'templates/mi-cuenta.php';
                break;
                
            case 'nueva-orden':
                $template = RKM_CORE_PATH . 'templates/nueva-orden.php';
                break;

            case 'pedidos':
                $orders = function_exists('wc_get_orders') ? wc_get_orders([
                    'customer_id' => $user->ID,
                    'limit'       => -1,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'status'      => ['pending', 'on-hold', 'processing', 'en-revision'],
                ]) : [];
                $template = RKM_CORE_PATH . 'templates/pedidos.php';
                break;

            case 'historial':
                $orders = function_exists('wc_get_orders') ? wc_get_orders([
                    'customer_id' => $user->ID,
                    'limit'       => -1,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'status'      => ['completed', 'cancelled', 'refunded', 'failed'],
                ]) : [];
                $template = RKM_CORE_PATH . 'templates/historial.php';
                break;

            case 'cuenta-corriente':
                if (class_exists('RKM_Current_Account')) {
                    (new RKM_Current_Account())->render_customer_page($data);
                    return;
                }

                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;

            case 'panel-vendedor':
                if (class_exists('RKM_Sellers')) {
                    (new RKM_Sellers())->render_dashboard($data);
                    return;
                }

                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;

            case 'usuarios':
                if (class_exists('RKM_Admin_Users')) {
                    (new RKM_Admin_Users())->render_page($data);
                    return;
                }

                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;

            case 'asignaciones':
                if (class_exists('RKM_Assignments')) {
                    (new RKM_Assignments())->render_page($data);
                    return;
                }

                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;

            case 'formas-pago':
                if (class_exists('RKM_Payment_Methods')) {
                    (new RKM_Payment_Methods())->render_page($data);
                    return;
                }

                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;

            case 'condiciones-pago':
                if (class_exists('RKM_Payment_Terms')) {
                    (new RKM_Payment_Terms())->render_page($data);
                    return;
                }

                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;

            case 'productos':
                if (class_exists('RKM_Products')) {
                    (new RKM_Products())->render_page($data);
                    return;
                }

                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;

            case 'pagos-clientes':
                if (class_exists('RKM_Current_Account')) {
                    (new RKM_Current_Account())->render_admin_page($data);
                    return;
                }

                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;

            case 'panel':
            default:
                if (class_exists('RKM_Admin_Dashboard') && RKM_Admin_Dashboard::can_access($user)) {
                    (new RKM_Admin_Dashboard())->render_dashboard($data);
                    return;
                }

                $data = array_merge($data, $this->get_customer_dashboard_data($user));
                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;
        }

        if (file_exists($template)) {
            include $template;
        }
    }

    private function get_view_context($user, $bcv_rate) {
        return [
            'user_name'       => $user->display_name,
            'user_role_label' => $this->get_role_label($user),
            'bcv_rate'        => $bcv_rate,
        ];
    }

    private function get_customer_dashboard_data($user) {
        $pending_total = $this->get_pending_total($user->ID);
        $balance_favor = $this->get_balance_favor($user->ID);
        $last_purchase_date = $this->get_last_purchase_date($user->ID);
        $returns_count = $this->get_returns_count($user->ID);

        return [
            'pending_total'      => $pending_total,
            'balance_favor'      => $balance_favor,
            'last_purchase_date' => $last_purchase_date,
            'returns_count'      => $returns_count,
            'dashboard_cards'    => [
                [
                    'label' => 'Pendiente por pagar',
                    'value' => $pending_total,
                ],
                [
                    'label' => 'Saldo a favor',
                    'value' => $balance_favor,
                ],
                [
                    'label' => 'Ultima compra',
                    'value' => $last_purchase_date,
                ],
                [
                    'label' => 'Devoluciones',
                    'value' => $returns_count,
                ],
            ],
        ];
    }

    public function get_bcv_usd_rate($force_refresh = false) {
        if (!$force_refresh) {
            $cached_rate = get_transient(self::BCV_USD_RATE_TRANSIENT);

            if ($this->is_valid_bcv_rate($cached_rate)) {
                $this->log_bcv_debug('Usando tasa BCV desde transient válido.', [
                    'value' => $cached_rate['value'],
                    'effective_date' => $cached_rate['effective_date'],
                ]);
                return $cached_rate;
            }

            if ($cached_rate !== false) {
                $this->log_bcv_debug('Transient BCV inválido detectado. Eliminando cache.', [
                    'cached_rate' => $cached_rate,
                ]);
                delete_transient(self::BCV_USD_RATE_TRANSIENT);
            }
        }

        $sources = ['alcambio', 'bcv', 'dolarapi'];

        $this->log_bcv_debug('Consultando tasa BCV segun prioridad configurada.', [
            'force_refresh' => $force_refresh,
            'sources' => $sources,
        ]);

        $parsed_rate = null;

        foreach ($sources as $source) {
            if ($source === 'alcambio') {
                $parsed_rate = $this->fetch_bcv_usd_rate_from_alcambio();
            } elseif ($source === 'bcv') {
                $parsed_rate = $this->fetch_bcv_usd_rate_from_official_site();
            } elseif ($source === 'dolarapi') {
                $parsed_rate = $this->fetch_bcv_usd_rate_from_dolarapi();
            }

            if ($this->is_valid_bcv_rate($parsed_rate)) {
                break;
            }
        }

        if (!$this->is_valid_bcv_rate($parsed_rate)) {
            $this->log_bcv_debug('No se obtuvo una tasa BCV válida desde ninguna fuente.');
            delete_transient(self::BCV_USD_RATE_TRANSIENT);
            return null;
        }

        set_transient(self::BCV_USD_RATE_TRANSIENT, $parsed_rate, self::BCV_USD_RATE_TTL);

        $this->log_bcv_debug('Tasa BCV obtenida y guardada en transient.', [
            'value' => $parsed_rate['value'],
            'effective_date' => $parsed_rate['effective_date'],
            'ttl' => self::BCV_USD_RATE_TTL,
        ]);

        return $parsed_rate;
    }

    private function fetch_bcv_usd_rate_from_alcambio() {
        $payload = [
            'query' => $this->get_alcambio_bcv_query(),
            'variables' => [
                'countryCode' => 'VE',
                'dateSearch'  => $this->get_alcambio_date_search_payload(),
            ],
        ];

        $this->log_bcv_debug('Intentando fuente Al Cambio.', [
            'url' => self::ALCAMBIO_GRAPHQL_URL,
            'variables' => $payload['variables'],
        ]);

        $response = wp_remote_post(self::ALCAMBIO_GRAPHQL_URL, [
            'timeout'    => 12,
            'redirection'=> 3,
            'user-agent' => 'RKM Dashboard/1.0; ' . home_url('/'),
            'headers'    => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body'       => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $this->log_bcv_debug('wp_remote_post devolvio WP_Error en Al Cambio.', [
                'error' => $response->get_error_message(),
                'error_data' => $response->get_error_data(),
            ]);
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log_bcv_debug('Respuesta remota recibida desde Al Cambio.', [
            'status_code' => $status_code,
            'body_length' => is_string($body) ? strlen($body) : 0,
        ]);

        if (!is_array($data)) {
            $this->log_bcv_debug('Al Cambio no devolvio JSON utilizable.', [
                'body_preview' => is_string($body) ? substr($body, 0, 220) : null,
            ]);
            return null;
        }

        if (!empty($data['errors'])) {
            $this->log_bcv_debug('Al Cambio devolvio errores GraphQL.', [
                'errors' => $data['errors'],
            ]);
            return null;
        }

        $conversion_data = $data['data']['getCountryConversions'] ?? null;

        if (!is_array($conversion_data)) {
            $this->log_bcv_debug('Al Cambio no devolvio getCountryConversions.', [
                'payload' => $data,
            ]);
            return null;
        }

        $rate_entry = $this->get_alcambio_usd_rate_entry($conversion_data['conversionRates'] ?? []);

        if (!is_array($rate_entry)) {
            $this->log_bcv_debug('Al Cambio no devolvio una tasa oficial USD utilizable.', [
                'conversion_rates' => $conversion_data['conversionRates'] ?? null,
            ]);
            return null;
        }

        $raw_value = !empty($rate_entry['usesRateValue'])
            ? ($rate_entry['rateValue'] ?? null)
            : ($rate_entry['baseValue'] ?? null);

        $value = $this->normalize_bcv_rate_number($raw_value);
        $effective_date = $this->format_bcv_timestamp(
            $conversion_data['dateBcvFees'] ?? ($conversion_data['dateBcv'] ?? null)
        );

        $rate = [
            'label'          => 'Tasa BCV',
            'currency'       => 'USD',
            'value'          => $value,
            'value_display'  => 'USD ' . $value,
            'effective_date' => $effective_date,
            'source_url'     => self::ALCAMBIO_PUBLIC_URL,
            'source_name'    => 'Al Cambio',
            'fetched_at'     => current_time('mysql'),
        ];

        if (!$this->is_valid_bcv_rate($rate)) {
            $this->log_bcv_debug('Al Cambio respondio pero la tasa no fue valida.', [
                'rate_entry' => $rate_entry,
                'conversion_data' => $conversion_data,
            ]);
            return null;
        }

        return $rate;
    }

    private function fetch_bcv_usd_rate_from_official_site() {
        $this->log_bcv_debug('Intentando fuente oficial BCV.', [
            'url' => self::BCV_USD_RATE_URL,
        ]);

        $response = wp_remote_get(self::BCV_USD_RATE_URL, [
            'timeout'    => 12,
            'redirection'=> 3,
            'user-agent' => 'RKM Dashboard/1.0; ' . home_url('/'),
        ]);

        if (is_wp_error($response)) {
            $this->log_bcv_debug('wp_remote_get devolvió WP_Error en BCV oficial.', [
                'error' => $response->get_error_message(),
                'error_data' => $response->get_error_data(),
            ]);
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $html = wp_remote_retrieve_body($response);

        $this->log_bcv_debug('Respuesta remota recibida desde BCV.', [
            'status_code' => $status_code,
            'body_length' => is_string($html) ? strlen($html) : 0,
        ]);

        if (!is_string($html) || $html === '') {
            $this->log_bcv_debug('El body del BCV llegó vacío o inválido.', [
                'status_code' => $status_code,
            ]);
            return null;
        }

        $parsed_rate = $this->parse_bcv_usd_rate($html);

        if (!$this->is_valid_bcv_rate($parsed_rate)) {
            $this->log_bcv_debug('No se pudo parsear una tasa BCV válida desde el HTML oficial.', $this->inspect_bcv_html($html));
        }

        return $parsed_rate;
    }

    private function fetch_bcv_usd_rate_from_dolarapi() {
        $this->log_bcv_debug('Intentando fuente DolarApi.', [
            'url' => self::DOLARAPI_BCV_USD_RATE_URL,
        ]);

        $response = wp_remote_get(self::DOLARAPI_BCV_USD_RATE_URL, [
            'timeout'    => 12,
            'redirection'=> 3,
            'user-agent' => 'RKM Dashboard/1.0; ' . home_url('/'),
            'headers'    => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->log_bcv_debug('wp_remote_get devolvió WP_Error en DolarApi.', [
                'error' => $response->get_error_message(),
                'error_data' => $response->get_error_data(),
            ]);
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log_bcv_debug('Respuesta remota recibida desde DolarApi.', [
            'status_code' => $status_code,
            'body_length' => is_string($body) ? strlen($body) : 0,
        ]);

        if (!is_array($data)) {
            $this->log_bcv_debug('DolarApi no devolvió JSON utilizable.', [
                'body_preview' => is_string($body) ? substr($body, 0, 220) : null,
            ]);
            return null;
        }

        $value = '';
        $effective_date = isset($data['fechaActualizacion']) ? trim((string) $data['fechaActualizacion']) : '';

        if (isset($data['promedio']) && $data['promedio'] !== '' && $data['promedio'] !== null) {
            $value = $this->normalize_bcv_rate_number($data['promedio']);
        } else {
            $buy = isset($data['compra']) ? (float) $data['compra'] : 0;
            $sell = isset($data['venta']) ? (float) $data['venta'] : 0;

            if ($buy > 0 && $sell > 0) {
                $value = $this->normalize_bcv_rate_number(($buy + $sell) / 2);
            } elseif ($sell > 0) {
                $value = $this->normalize_bcv_rate_number($sell);
            } elseif ($buy > 0) {
                $value = $this->normalize_bcv_rate_number($buy);
            }
        }

        $rate = [
            'label'          => 'Tasa BCV',
            'currency'       => 'USD',
            'value'          => $value,
            'value_display'  => 'USD ' . $value,
            'effective_date' => $effective_date,
            'source_url'     => self::DOLARAPI_BCV_USD_RATE_URL,
            'source_name'    => 'DolarApi',
            'fetched_at'     => current_time('mysql'),
        ];

        if (!$this->is_valid_bcv_rate($rate)) {
            $this->log_bcv_debug('DolarApi respondió pero la tasa no fue válida.', [
                'payload' => $data,
            ]);
            return null;
        }

        return $rate;
    }

    private function parse_bcv_usd_rate($html) {
        $value = null;
        $effective_date = null;

        if (preg_match('/id=["\']dolar["\'][\s\S]*?<strong>\s*([0-9\.,]+)\s*<\/strong>/i', $html, $matches)) {
            $value = trim($matches[1]);
        } elseif (preg_match('/USD[\s\S]*?<strong>\s*([0-9\.,]+)\s*<\/strong>/i', $html, $matches)) {
            $value = trim($matches[1]);
        }

        if (preg_match('/Fecha\s*Valor[\s\S]{0,200}?(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/iu', $html, $matches)) {
            $effective_date = trim($matches[1]);
        } elseif (preg_match('/Fecha\s*Valor[\s\S]{0,200}?([A-Za-zÁÉÍÓÚáéíóúÑñ]+,\s*\d{1,2}\s+[A-Za-zÁÉÍÓÚáéíóúñ]+\s+\d{4})/u', $html, $matches)) {
            $effective_date = trim(wp_strip_all_tags($matches[1]));
        } elseif (preg_match('/Fecha\s*Valor[\s\S]{0,200}?([A-Za-zÁÉÍÓÚáéíóúÑñ]+\s+\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/u', $html, $matches)) {
            $effective_date = trim(wp_strip_all_tags($matches[1]));
        }

        if (!$value || !$effective_date) {
            return null;
        }

        return [
            'label'          => 'Tasa BCV',
            'currency'       => 'USD',
            'value'          => $value,
            'value_display'  => 'USD ' . $value,
            'effective_date' => $effective_date,
            'source_url'     => self::BCV_USD_RATE_URL,
            'source_name'    => 'BCV',
            'fetched_at'     => current_time('mysql'),
        ];
    }

    private function is_valid_bcv_rate($rate) {
        if (!is_array($rate)) {
            return false;
        }

        if (empty($rate['value']) || empty($rate['effective_date'])) {
            return false;
        }

        return preg_match('/^\d[\d\.,]*$/', (string) $rate['value']) === 1;
    }

    private function get_alcambio_bcv_query() {
        return <<<'GRAPHQL'
query getCountryConversions($countryCode: String!, $dateSearch: DateSearchInput) {
  getCountryConversions(payload: { countryCode: $countryCode }, dateSearch: $dateSearch) {
    conversionRates {
      baseValue
      official
      rateCurrency {
        code
      }
      rateValue
      type
      usesRateValue
    }
    dateBcvFees
    dateBcv
    createdAt
  }
}
GRAPHQL;
    }

    private function get_alcambio_date_search_payload() {
        $reference_timestamp = time() - (4 * HOUR_IN_SECONDS);
        $weekday = (int) gmdate('w', $reference_timestamp);
        $days_offset = 0;

        if ($weekday === 6) {
            $days_offset = 1;
        } elseif ($weekday === 0) {
            $days_offset = 2;
        }

        $year = (int) gmdate('Y', $reference_timestamp);
        $month = (int) gmdate('n', $reference_timestamp);
        $day = (int) gmdate('j', $reference_timestamp) - $days_offset;

        $start_date = gmmktime(4, 0, 0, $month, $day, $year) * 1000;

        return [
            'startDate'     => $start_date,
            'endDate'       => $start_date + (480 * MINUTE_IN_SECONDS * 1000),
            'filterByField' => 'dateBcvFees',
        ];
    }

    private function get_alcambio_usd_rate_entry($conversion_rates) {
        if (!is_array($conversion_rates)) {
            return null;
        }

        foreach ($conversion_rates as $entry) {
            if (
                is_array($entry)
                && !empty($entry['official'])
                && ($entry['type'] ?? '') === 'SECONDARY'
                && (($entry['rateCurrency']['code'] ?? '') === 'USD')
            ) {
                return $entry;
            }
        }

        foreach ($conversion_rates as $entry) {
            if (
                is_array($entry)
                && !empty($entry['official'])
                && (($entry['rateCurrency']['code'] ?? '') === 'USD')
            ) {
                return $entry;
            }
        }

        return null;
    }

    private function format_bcv_timestamp($timestamp_ms) {
        $timestamp_ms = is_numeric($timestamp_ms) ? (int) $timestamp_ms : 0;

        if ($timestamp_ms <= 0) {
            return '';
        }

        return wp_date('d/m/Y', (int) floor($timestamp_ms / 1000), new DateTimeZone('America/Caracas'));
    }

    private function normalize_bcv_rate_number($value) {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return '';
            }

            $normalized = preg_replace('/[^0-9\.,]/', '', $value);

            if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } elseif (strpos($normalized, ',') !== false) {
                $normalized = str_replace(',', '.', $normalized);
            }

            $normalized = preg_replace('/[^0-9\.]/', '', $normalized);

            if ($normalized === '' || !is_numeric($normalized)) {
                return '';
            }

            $value = (float) $normalized;
        }

        if (!is_numeric($value)) {
            return '';
        }

        return number_format((float) $value, 4, ',', '.');
    }

    private function is_local_environment() {
        if (function_exists('wp_get_environment_type')) {
            $environment_type = wp_get_environment_type();

            if (in_array($environment_type, ['local', 'development'], true)) {
                return true;
            }
        }

        $home_url = home_url('/');

        return (
            strpos($home_url, '.local') !== false ||
            strpos($home_url, 'localhost') !== false ||
            strpos($home_url, '127.0.0.1') !== false
        );
    }

    private function inspect_bcv_html($html) {
        return [
            'has_dolar_id' => preg_match('/id=["\']dolar["\']/i', $html) === 1,
            'has_usd_text' => preg_match('/USD/i', $html) === 1,
            'has_fecha_valor_text' => preg_match('/Fecha\s*Valor/i', $html) === 1,
            'value_match' => preg_match('/id=["\']dolar["\'][\s\S]*?<strong>\s*([0-9\.,]+)\s*<\/strong>/i', $html, $value_match) === 1 ? $value_match[1] : null,
            'date_match' => preg_match('/Fecha\s*Valor[\s\S]{0,200}?(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/iu', $html, $date_match) === 1 ? $date_match[1] : null,
            'body_preview' => substr(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($html))), 0, 220),
        ];
    }

    private function log_bcv_debug($message, $context = []) {
        if (!(defined('WP_DEBUG') && WP_DEBUG) && !(defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)) {
            return;
        }

        $payload = $context ? ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        error_log('[RKM BCV] ' . $message . $payload);
    }

    private function get_role_label($user) {
        if (empty($user->roles)) {
            return 'Cliente';
        }

        $role = $user->roles[0];

        return class_exists('RKM_Permissions')
            ? RKM_Permissions::get_role_label($role)
            : ucfirst($role);
    }

    private function get_pending_total($user_id) {
        if (!function_exists('wc_get_orders')) {
            return '$0';
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status'      => ['pending'],
            'limit'       => -1,
        ]);

        $total = 0;

        foreach ($orders as $order) {
            $total += (float) $order->get_total();
        }

        return function_exists('wc_price') ? wc_price($total) : '$' . number_format($total, 2, ',', '.');
    }

    private function get_balance_favor($user_id) {
        $saldo = get_user_meta($user_id, 'saldo_favor', true);
        $saldo = $saldo ? (float) $saldo : 0;

        return function_exists('wc_price') ? wc_price($saldo) : '$' . number_format($saldo, 2, ',', '.');
    }

    private function get_last_purchase_date($user_id) {
        if (!function_exists('wc_get_orders')) {
            return 'Sin compras';
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => 1,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => ['completed', 'processing', 'on-hold'],
        ]);

        if (empty($orders)) {
            return 'Sin compras';
        }

        $order = $orders[0];
        $date = $order->get_date_created();

        return $date ? $date->date_i18n('d/m/Y') : 'Sin fecha';
    }

    private function get_returns_count($user_id) {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => -1,
        ]);

        $returns = 0;

        foreach ($orders as $order) {
            if ((float) $order->get_total_refunded() > 0) {
                $returns++;
            }
        }

        return $returns;
    }

}
