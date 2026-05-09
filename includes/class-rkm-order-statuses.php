<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Order_Statuses {

    const REVIEW = 'rkm-review';
    const CONFIRMED = 'rkm-confirmed';
    const WAREHOUSE = 'rkm-warehouse';
    const READY = 'rkm-ready';
    const DISPATCHED = 'rkm-dispatched';

    public function init() {
        add_action('init', [$this, 'register_statuses']);
        add_filter('wc_order_statuses', [$this, 'add_statuses_to_woocommerce']);
    }

    public static function get_statuses() {
        return [
            self::REVIEW => [
                'label' => 'En revision',
                'label_count' => _n_noop(
                    'En revision <span class="count">(%s)</span>',
                    'En revision <span class="count">(%s)</span>'
                ),
            ],
            self::CONFIRMED => [
                'label' => 'Confirmado',
                'label_count' => _n_noop(
                    'Confirmado <span class="count">(%s)</span>',
                    'Confirmados <span class="count">(%s)</span>'
                ),
            ],
            self::WAREHOUSE => [
                'label' => 'En almacen',
                'label_count' => _n_noop(
                    'En almacen <span class="count">(%s)</span>',
                    'En almacen <span class="count">(%s)</span>'
                ),
            ],
            self::READY => [
                'label' => 'Listo para despacho',
                'label_count' => _n_noop(
                    'Listo para despacho <span class="count">(%s)</span>',
                    'Listos para despacho <span class="count">(%s)</span>'
                ),
            ],
            self::DISPATCHED => [
                'label' => 'Despachado',
                'label_count' => _n_noop(
                    'Despachado <span class="count">(%s)</span>',
                    'Despachados <span class="count">(%s)</span>'
                ),
            ],
        ];
    }

    public static function get_active_statuses() {
        return [
            self::REVIEW,
            'pending',
            'on-hold',
            self::CONFIRMED,
            'processing',
            self::WAREHOUSE,
            self::READY,
            self::DISPATCHED,
            'en-revision',
        ];
    }

    public static function get_operational_statuses() {
        return [
            self::REVIEW,
            self::CONFIRMED,
            self::WAREHOUSE,
            self::READY,
            self::DISPATCHED,
        ];
    }

    public function register_statuses() {
        foreach (self::get_statuses() as $status => $config) {
            register_post_status('wc-' . $status, [
                'label'                     => $config['label'],
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => $config['label_count'],
            ]);
        }
    }

    public function add_statuses_to_woocommerce($order_statuses) {
        $rkm_statuses = [];

        foreach (self::get_statuses() as $status => $config) {
            $rkm_statuses['wc-' . $status] = $config['label'];
        }

        if (isset($order_statuses['wc-pending'])) {
            return $this->insert_after_status($order_statuses, 'wc-pending', $rkm_statuses);
        }

        return array_merge($order_statuses, $rkm_statuses);
    }

    private function insert_after_status($order_statuses, $target_status, $new_statuses) {
        $sorted_statuses = [];

        foreach ($order_statuses as $status => $label) {
            $sorted_statuses[$status] = $label;

            if ($status === $target_status) {
                $sorted_statuses = array_merge($sorted_statuses, $new_statuses);
            }
        }

        return $sorted_statuses;
    }
}
