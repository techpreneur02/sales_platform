<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!is_staff_logged_in()) {
            access_denied('OmniPOS Settings');
        }
    }

    public function index()
    {
        $data['title'] = 'OmniPOS Settings';
        $data['active_tab'] = trim((string) $this->input->get('tab', true));
        if ($data['active_tab'] === '') {
            $data['active_tab'] = 'general';
        }

        $data['warehouses'] = $this->db
            ->where('is_active', 1)
            ->order_by('name', 'ASC')
            ->get(db_prefix() . 'pos_warehouses')
            ->result_array();

        $data['settings'] = [
            'default_register' => (string) get_option('pos_default_register'),
            'default_warehouse_id' => (int) get_option('pos_default_warehouse_id'),
            'allow_zero_stock_sales' => (string) get_option('pos_allow_zero_stock_sales'),
            'default_tax_rate' => (string) get_option('pos_default_tax_rate'),
            'wallet_require_pin' => (string) get_option('pos_wallet_require_pin'),
            'loyalty_earn_rate' => (string) get_option('pos_loyalty_earn_rate'),
            'loyalty_point_value' => (string) get_option('pos_loyalty_point_value'),
            'default_item_unit' => (string) get_option('pos_default_item_unit'),
            'item_units' => $this->normalize_lines((string) get_option('pos_item_units'), false, ['pcs', 'bag', 'box', 'pack', 'bottle', 'kg', 'g', 'l', 'ml']),
            'card_brands' => $this->normalize_lines((string) get_option('pos_card_brands'), false, ['Visa', 'Mastercard', 'AMEX', 'Discover']),
            'shrinkage_reason_codes' => $this->normalize_lines((string) get_option('pos_shrinkage_reason_codes'), true, ['DAMAGED', 'EXPIRED', 'STOLEN', 'SPILLAGE']),
            'refund_reason_codes' => $this->normalize_lines((string) get_option('pos_refund_reason_codes'), true, ['RETURNED_GOODS', 'WRONG_ITEM', 'DAMAGED_ITEM', 'CUSTOMER_CANCELLED']),
        ];

        if ($data['settings']['default_item_unit'] === '') {
            $data['settings']['default_item_unit'] = $data['settings']['item_units'][0];
        }

        $this->load->view('omnipos/settings/index', $data);
    }

    public function save_general()
    {
        $defaultRegister = trim((string) $this->input->post('default_register', true));
        $defaultWarehouseId = (int) $this->input->post('default_warehouse_id');
        $allowZeroStockSales = $this->input->post('allow_zero_stock_sales') ? '1' : '0';
        $defaultTaxRate = (string) max(0, (float) $this->input->post('default_tax_rate'));
        $walletRequirePin = $this->input->post('wallet_require_pin') ? '1' : '0';
        $loyaltyEarnRate = (string) max(0, (float) $this->input->post('loyalty_earn_rate'));
        $loyaltyPointValue = (string) max(0, (float) $this->input->post('loyalty_point_value'));
        $defaultItemUnit = trim((string) $this->input->post('default_item_unit', true));

        if ($defaultRegister === '') {
            $defaultRegister = 'main-register';
        }

        $warehouseExists = $this->db
            ->where('id', $defaultWarehouseId)
            ->where('is_active', 1)
            ->count_all_results(db_prefix() . 'pos_warehouses') > 0;

        if (!$warehouseExists) {
            $defaultWarehouseId = 1;
        }

        $units = $this->normalize_lines((string) get_option('pos_item_units'), false, ['pcs']);
        if ($defaultItemUnit === '' || !in_array($defaultItemUnit, $units, true)) {
            $defaultItemUnit = $units[0];
        }

        update_option('pos_default_register', $defaultRegister);
        update_option('pos_default_warehouse_id', (string) $defaultWarehouseId);
        update_option('pos_allow_zero_stock_sales', $allowZeroStockSales);
        update_option('pos_default_tax_rate', $defaultTaxRate);
        update_option('pos_wallet_require_pin', $walletRequirePin);
        update_option('pos_loyalty_earn_rate', $loyaltyEarnRate);
        update_option('pos_loyalty_point_value', $loyaltyPointValue);
        update_option('pos_default_item_unit', $defaultItemUnit);

        set_alert('success', 'OmniPOS general settings saved.');
        redirect(admin_url('omnipos/settings?tab=general'));
    }

    public function save_dynamic_values()
    {
        $units = $this->normalize_lines((string) $this->input->post('item_units'), false, ['pcs']);
        $cardBrands = $this->normalize_lines((string) $this->input->post('card_brands'), false, ['Visa', 'Mastercard']);
        $shrinkageCodes = $this->normalize_lines((string) $this->input->post('shrinkage_reason_codes'), true, ['DAMAGED']);
        $refundCodes = $this->normalize_lines((string) $this->input->post('refund_reason_codes'), true, ['RETURNED_GOODS']);

        update_option('pos_item_units', implode("\n", $units));
        update_option('pos_card_brands', implode("\n", $cardBrands));
        update_option('pos_shrinkage_reason_codes', implode("\n", $shrinkageCodes));
        update_option('pos_refund_reason_codes', implode("\n", $refundCodes));

        $defaultItemUnit = (string) get_option('pos_default_item_unit');
        if ($defaultItemUnit === '' || !in_array($defaultItemUnit, $units, true)) {
            update_option('pos_default_item_unit', $units[0]);
        }

        set_alert('success', 'Dynamic value settings saved.');
        redirect(admin_url('omnipos/settings?tab=dynamic'));
    }

    private function normalize_lines($raw, $uppercase, $fallback)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
        $values = [];

        foreach ($lines as $line) {
            $value = trim((string) $line);
            if ($value === '') {
                continue;
            }

            if ($uppercase) {
                $value = strtoupper($value);
            }

            $values[] = $value;
        }

        $values = array_values(array_unique($values));

        if (empty($values)) {
            return $fallback;
        }

        return $values;
    }
}
