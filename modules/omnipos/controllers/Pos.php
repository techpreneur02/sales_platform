<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pos extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!is_staff_logged_in()) {
            access_denied('OmniPOS');
        }

        $this->load->model('invoices_model');
        $this->load->model('payments_model');
        $this->load->model('clients_model');
        $this->load->model('invoice_items_model');
    }

    public function index()
    {
        $data['title'] = 'OmniPOS Terminal';
        $data['items_groups'] = $this->normalize_item_groups($this->invoice_items_model->get_groups());
        $data['items'] = $this->normalize_items($this->invoice_items_model->get());
        $data['cart'] = $this->get_active_cart();
        $data['cart_items'] = $this->get_active_cart_items();
        $data['current_shift'] = $this->get_open_shift();
        $data['warehouses'] = $this->db->where('is_active', 1)->order_by('name', 'ASC')->get(db_prefix() . 'pos_warehouses')->result_array();
        $data['card_brands'] = $this->parse_setting_lines((string) get_option('pos_card_brands'), ['Visa', 'Mastercard', 'AMEX', 'Discover']);
        $data['refund_reason_codes'] = $this->parse_setting_lines((string) get_option('pos_refund_reason_codes'), ['RETURNED_GOODS', 'WRONG_ITEM', 'DAMAGED_ITEM', 'CUSTOMER_CANCELLED'], true);
        $data['suspended_carts'] = $this->db
            ->where('staff_id', get_staff_user_id())
            ->where('status', 'suspended')
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'pos_suspended_carts')
            ->result_array();
        $data['stock_map'] = $this->get_stock_map($this->get_active_warehouse_id());
        $data['zero_stock_locked'] = get_option('pos_allow_zero_stock_sales') !== '1';
        $data['default_client_id'] = $this->resolve_default_pos_client_id();

        $this->load->view('omnipos/pos/index', $data);
    }

    public function shifts()
    {
        $data['title'] = 'Shift History';
        $data['rows'] = $this->db
            ->where('staff_id', get_staff_user_id())
            ->order_by('id', 'DESC')
            ->limit(100)
            ->get(db_prefix() . 'pos_shifts')
            ->result_array();
        $data['current_shift'] = $this->get_open_shift();
        $data['warehouses'] = $this->db->where('is_active', 1)->order_by('name', 'ASC')->get(db_prefix() . 'pos_warehouses')->result_array();
        $data['suspended_carts'] = $this->db
            ->where('staff_id', get_staff_user_id())
            ->where('status', 'suspended')
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'pos_suspended_carts')
            ->result_array();
        $data['refund_reason_codes'] = $this->parse_setting_lines((string) get_option('pos_refund_reason_codes'), ['RETURNED_GOODS', 'WRONG_ITEM', 'DAMAGED_ITEM', 'CUSTOMER_CANCELLED'], true);

        $this->load->view('omnipos/pos/shifts', $data);
    }

    public function cart()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $cart = $this->get_active_cart();
        $cartItems = $this->get_active_cart_items();

        $this->json_response(true, 'Cart loaded', [
            'cart' => $cart,
            'items' => $cartItems,
            'totals' => $this->calculate_cart_totals($cartItems, $cart),
        ]);
    }

    public function search_items()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $q = trim((string) $this->input->get('q', true));

        $itemsTable = db_prefix() . 'items';
        $itemPkField = $this->db->field_exists('id', $itemsTable) ? 'id' : 'itemid';
        $groupField = $this->db->field_exists('group_id', $itemsTable) ? 'group_id' : 'groupid';

        $this->db->select(db_prefix() . 'items.' . $itemPkField . ' as itemid, description, long_description, rate, ' . db_prefix() . 'items.' . $groupField . ' as group_id, ' . db_prefix() . 'items_groups.name as group_name');
        $this->db->from($itemsTable);
        $this->db->join(db_prefix() . 'items_groups', db_prefix() . 'items_groups.id = ' . db_prefix() . 'items.' . $groupField, 'left');

        if ($q !== '') {
            $safeQ = $this->db->escape_like_str($q);

            $this->db->group_start();
            $this->db->like('description', $q);
            $this->db->or_like('long_description', $q);

            if ($this->db->field_exists('sku', db_prefix() . 'items')) {
                $this->db->or_like('sku', $q);
            }

            if ($this->db->field_exists('barcode', db_prefix() . 'items')) {
                $this->db->or_like('barcode', $q);
            }

            $this->db->or_where('EXISTS (SELECT 1 FROM ' . db_prefix() . 'customfieldsvalues cfv WHERE cfv.relid=' . db_prefix() . 'items.' . $itemPkField . ' AND cfv.fieldto="items_pr" AND cfv.value LIKE "%'.$safeQ.'%")', null, false);
            $this->db->group_end();
        }

        $this->db->order_by('description', 'ASC');
        $this->db->limit(40);

        $rows = $this->db->get()->result_array();
        $stockMap = $this->get_stock_map($this->get_active_warehouse_id());

        foreach ($rows as &$row) {
            $itemId = (int) $row['itemid'];
            $row['stock_qty'] = isset($stockMap[$itemId]) ? (float) $stockMap[$itemId] : 0;
            $row['stock_locked'] = $this->is_zero_stock_locked() && $row['stock_qty'] <= 0;
        }

        $this->json_response(true, 'Search complete', [
            'items' => $rows,
        ]);
    }

    private function normalize_items($rows)
    {
        $normalized = [];
        foreach ((array) $rows as $row) {
            $row = (array) $row;

            $row['itemid'] = isset($row['itemid']) ? (int) $row['itemid'] : (isset($row['id']) ? (int) $row['id'] : 0);
            $row['group_id'] = isset($row['group_id']) ? (int) $row['group_id'] : (isset($row['groupid']) ? (int) $row['groupid'] : 0);

            $normalized[] = $row;
        }

        return $normalized;
    }

    private function normalize_item_groups($rows)
    {
        $normalized = [];
        foreach ((array) $rows as $row) {
            $row = (array) $row;
            $row['id'] = isset($row['id']) ? (int) $row['id'] : (isset($row['groupid']) ? (int) $row['groupid'] : 0);
            $normalized[] = $row;
        }

        return $normalized;
    }

    public function wallet_lookup()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $barcode = trim((string) $this->input->post('barcode', true));
        if ($barcode === '') {
            $this->json_response(false, 'Wallet barcode is required.');
        }

        $staff = $this->get_wallet_staff_by_barcode($barcode);
        if (!$staff) {
            $this->json_response(false, 'No wallet staff account found for barcode.');
        }

        $this->reset_daily_spend_row_if_needed($staff);
        $staff = $this->db->where('id', $staff['id'])->get(db_prefix() . 'pos_wallet_staff_accounts')->row_array();

        $wallet = $this->db
            ->where('id', $staff['wallet_account_id'])
            ->where('status', 'active')
            ->get(db_prefix() . 'pos_wallet_accounts')
            ->row_array();

        if (!$wallet) {
            $this->json_response(false, 'Master wallet account is inactive or missing.');
        }

        $available = min((float) $wallet['balance'], (float) $staff['remaining_limit']);

        if ((float) $staff['daily_limit'] > 0) {
            $dailyLeft = max(0, (float) $staff['daily_limit'] - (float) $staff['daily_spent']);
            $available = min($available, $dailyLeft);
        }

        $this->json_response(true, 'Wallet staff loaded.', [
            'wallet_staff' => [
                'id' => (int) $staff['id'],
                'full_name' => (string) $staff['full_name'],
                'employee_code' => (string) $staff['employee_code'],
                'barcode' => (string) $staff['barcode'],
                'status' => (string) $staff['status'],
                'remaining_limit' => (float) $staff['remaining_limit'],
                'daily_limit' => (float) $staff['daily_limit'],
                'daily_spent' => (float) $staff['daily_spent'],
                'wallet_balance' => (float) $wallet['balance'],
                'available_to_spend' => round($available, 2),
            ],
            'client_id' => (int) $wallet['client_id'],
        ]);
    }

    public function add_item()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $itemId = (int) $this->input->post('item_id');
        $qty = max(1, (float) $this->input->post('qty'));

        if ($itemId < 1) {
            $this->json_response(false, 'Invalid item ID.');
        }

        $item = $this->invoice_items_model->get($itemId);
        if (!$item) {
            $this->json_response(false, 'Item not found.');
        }

        $item = (array) $item;
        $this->add_item_to_cart($item, $qty);
    }

    public function update_line_item()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $lineId = (int) $this->input->post('line_id');
        $qty = max(0.01, (float) $this->input->post('qty'));
        $discountType = strtolower(trim((string) $this->input->post('discount_type', true)));
        $discountValue = max(0, (float) $this->input->post('discount_value'));
        $taxRate = max(0, (float) $this->input->post('tax_rate'));
        $notes = trim((string) $this->input->post('notes', true));

        if (!in_array($discountType, ['', 'fixed', 'percent'], true)) {
            $discountType = '';
        }

        $cart = $this->get_active_cart();
        if (!$cart) {
            $this->json_response(false, 'No active cart.');
        }

        $line = $this->db
            ->where('id', $lineId)
            ->where('cart_id', $cart['id'])
            ->get(db_prefix() . 'pos_cart_items')
            ->row_array();

        if (!$line) {
            $this->json_response(false, 'Cart line not found.');
        }

        if (!$this->can_sell_qty((int) $line['item_id'], $qty)) {
            $this->json_response(false, 'Zero-stock lockout is active for this item.');
        }

        $this->db->where('id', $lineId);
        $this->db->update(db_prefix() . 'pos_cart_items', [
            'qty' => $qty,
            'modifier_discount_type' => $discountType,
            'modifier_discount_value' => $discountValue,
            'modifier_tax_rate' => $taxRate,
            'modifier_notes' => $notes,
        ]);

        $this->touch_cart($cart['id']);

        $items = $this->get_active_cart_items();

        $this->json_response(true, 'Line updated.', [
            'cart' => $this->get_active_cart(),
            'cart_items' => $items,
            'totals' => $this->calculate_cart_totals($items, $this->get_active_cart()),
        ]);
    }

    public function update_cart_adjustments()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $cart = $this->get_active_cart();
        if (!$cart) {
            $this->json_response(false, 'No active cart found.');
        }

        $discountType = strtolower(trim((string) $this->input->post('discount_type', true)));
        $discountValue = max(0, (float) $this->input->post('discount_value'));
        $serviceCharge = max(0, (float) $this->input->post('service_charge'));

        if (!in_array($discountType, ['', 'fixed', 'percent'], true)) {
            $discountType = '';
        }

        $this->db->where('id', $cart['id']);
        $this->db->update(db_prefix() . 'pos_carts', [
            'global_discount_type' => $discountType,
            'global_discount_value' => $discountValue,
            'service_charge_value' => $serviceCharge,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $updatedCart = $this->get_active_cart();
        $items = $this->get_active_cart_items();

        $this->json_response(true, 'Cart adjustments updated.', [
            'cart' => $updatedCart,
            'cart_items' => $items,
            'totals' => $this->calculate_cart_totals($items, $updatedCart),
        ]);
    }

    public function open_shift()
    {
        if (!$this->input->post()) {
            show_404();
        }

        $registerKey = trim((string) $this->input->post('register_key', true));
        if ($registerKey === '') {
            $registerKey = get_option('pos_default_register');
        }

        $openingFloat = (float) $this->input->post('opening_float');
        $warehouseId = (int) $this->input->post('warehouse_id');
        $warehouseId = $this->resolve_open_shift_warehouse_id($warehouseId);

        $existing = $this->get_open_shift();
        if ($existing) {
            $this->json_response(false, 'An open shift already exists for this staff member.', [
                'shift' => $existing,
            ]);
        }

        $insertData = [
            'staff_id'       => get_staff_user_id(),
            'register_key'   => $registerKey,
            'opening_float'  => $openingFloat,
            'opened_at'      => date('Y-m-d H:i:s'),
            'status'         => 'open',
        ];

        if ($this->db->field_exists('warehouse_id', db_prefix() . 'pos_shifts')) {
            $insertData['warehouse_id'] = $warehouseId;
        }

        $inserted = $this->db->insert(db_prefix() . 'pos_shifts', $insertData);
        if (!$inserted) {
            $this->json_response(false, 'Failed to open shift. Please verify OmniPOS tables are upgraded and try again.');
        }

        $this->json_response(true, 'Shift opened successfully.', [
            'shift_id' => (int) $this->db->insert_id(),
            'warehouse_id' => $warehouseId,
        ]);
    }

    public function close_shift()
    {
        if (!$this->input->post()) {
            show_404();
        }

        $shift = $this->get_open_shift();
        if (!$shift) {
            $this->json_response(false, 'No open shift found.');
        }

        $countedCash = (float) $this->input->post('counted_cash');
        $countedCard = (float) $this->input->post('counted_card');
        $terminalTotal = (float) $this->input->post('terminal_total');
        $notes = trim((string) $this->input->post('notes', true));

        $paymentLogTable = db_prefix() . 'pos_payment_logs';
        $expectedCash = 0.0;
        $expectedCard = 0.0;

        if ($this->db->table_exists($paymentLogTable)) {
            $cashRow = $this->db
                ->select_sum('amount')
                ->where('payment_type', 'cash')
                ->where('transaction_id IN ' . $this->sub_query_transaction_ids($shift['id']), null, false)
                ->get($paymentLogTable)
                ->row_array();

            $cardRow = $this->db
                ->select_sum('amount')
                ->where('payment_type', 'card')
                ->where('transaction_id IN ' . $this->sub_query_transaction_ids($shift['id']), null, false)
                ->get($paymentLogTable)
                ->row_array();

            $expectedCash = $cashRow && isset($cashRow['amount']) ? (float) $cashRow['amount'] : 0.0;
            $expectedCard = $cardRow && isset($cardRow['amount']) ? (float) $cardRow['amount'] : 0.0;
        }

        $expectedCashWithFloat = $expectedCash + (float) $shift['opening_float'];

        $shiftTable = db_prefix() . 'pos_shifts';
        $updateData = [
            'status' => 'closed',
        ];

        $optionalFields = [
            'closed_at' => date('Y-m-d H:i:s'),
            'closing_expected_cash' => $expectedCashWithFloat,
            'closing_counted_cash' => $countedCash,
            'closing_expected_card' => $expectedCard,
            'closing_counted_card' => $countedCard,
            'cash_variance' => $countedCash - $expectedCashWithFloat,
            'card_variance' => $countedCard - $expectedCard,
            'notes' => $notes,
        ];

        foreach ($optionalFields as $field => $value) {
            if ($this->db->field_exists($field, $shiftTable)) {
                $updateData[$field] = $value;
            }
        }

        $this->db->where('id', $shift['id']);
        $updated = $this->db->update($shiftTable, $updateData);
        if (!$updated) {
            $this->json_response(false, 'Failed to close shift. Please verify OmniPOS tables are upgraded and try again.');
        }

        $blindCountsTable = db_prefix() . 'pos_shift_blind_counts';
        if ($this->db->table_exists($blindCountsTable)) {
            $blindData = [];
            $candidate = [
                'shift_id' => $shift['id'],
                'counted_cash' => $countedCash,
                'counted_card' => $countedCard,
                'terminal_total' => $terminalTotal,
                'notes' => $notes,
                'counted_by' => get_staff_user_id(),
                'counted_at' => date('Y-m-d H:i:s'),
            ];

            foreach ($candidate as $field => $value) {
                if ($this->db->field_exists($field, $blindCountsTable)) {
                    $blindData[$field] = $value;
                }
            }

            if (!empty($blindData)) {
                $this->db->insert($blindCountsTable, $blindData);
            }
        }

        $this->json_response(true, 'Shift closed successfully.', [
            'shift_id' => $shift['id'],
        ]);
    }

    public function scan_item()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $shift = $this->get_open_shift();
        if (!$shift) {
            $this->json_response(false, 'Open a shift before scanning items.');
        }

        $barcode = trim((string) $this->input->post('barcode', true));
        if ($barcode === '') {
            $this->json_response(false, 'Barcode is required.');
        }

        $item = $this->find_item_by_barcode($barcode);
        if (!$item) {
            $staff = $this->get_wallet_staff_by_barcode($barcode);
            if ($staff) {
                return $this->wallet_lookup();
            }

            $this->json_response(false, 'No product or wallet profile found for scanned barcode.');
        }

        $this->add_item_to_cart($item, 1);
    }

    public function suspend_cart()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $cart = $this->get_active_cart();
        if (!$cart) {
            $this->json_response(false, 'No active cart found.');
        }

        $items = $this->get_active_cart_items();
        if (empty($items)) {
            $this->json_response(false, 'Cannot suspend an empty cart.');
        }

        $label = trim((string) $this->input->post('label', true));
        if ($label === '') {
            $label = 'Suspended #' . $cart['id'];
        }

        $this->db->insert(db_prefix() . 'pos_suspended_carts', [
            'cart_id'       => $cart['id'],
            'staff_id'      => get_staff_user_id(),
            'label'         => $label,
            'suspended_at'  => date('Y-m-d H:i:s'),
            'status'        => 'suspended',
        ]);

        $suspendedId = (int) $this->db->insert_id();

        foreach ($items as $item) {
            $this->db->insert(db_prefix() . 'pos_suspended_items', [
                'suspended_id'     => $suspendedId,
                'item_id'          => $item['item_id'],
                'description'      => $item['description'],
                'long_description' => $item['long_description'],
                'qty'              => $item['qty'],
                'unit_price'       => $item['unit_price'],
                'line_total'       => $item['line_total'],
            ]);
        }

        $this->db->where('id', $cart['id']);
        $this->db->update(db_prefix() . 'pos_carts', [
            'status'     => 'suspended',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->json_response(true, 'Cart suspended.', ['suspended_id' => $suspendedId]);
    }

    public function recall_cart($suspendedId)
    {
        $suspendedId = (int) $suspendedId;

        if ($suspendedId < 1) {
            $this->json_response(false, 'Invalid suspended cart ID.');
        }

        $suspended = $this->db
            ->where('id', $suspendedId)
            ->where('staff_id', get_staff_user_id())
            ->where('status', 'suspended')
            ->get(db_prefix() . 'pos_suspended_carts')
            ->row_array();

        if (!$suspended) {
            $this->json_response(false, 'Suspended cart not found.');
        }

        $shift = $this->get_open_shift();
        if (!$shift) {
            $this->json_response(false, 'Open a shift before recalling a cart.');
        }

        $cart = $this->get_or_create_active_cart($shift['id']);

        $this->db->where('cart_id', $cart['id']);
        $this->db->delete(db_prefix() . 'pos_cart_items');

        $items = $this->db
            ->where('suspended_id', $suspendedId)
            ->get(db_prefix() . 'pos_suspended_items')
            ->result_array();

        foreach ($items as $item) {
            $this->db->insert(db_prefix() . 'pos_cart_items', [
                'cart_id'           => $cart['id'],
                'item_id'           => $item['item_id'],
                'description'       => $item['description'],
                'long_description'  => $item['long_description'],
                'qty'               => $item['qty'],
                'unit_price'        => $item['unit_price'],
                'line_total'        => $item['line_total'],
                'modifier_discount_type' => '',
                'modifier_discount_value' => 0,
                'modifier_tax_rate' => 0,
                'modifier_notes' => null,
            ]);
        }

        $this->db->where('id', $suspendedId);
        $this->db->update(db_prefix() . 'pos_suspended_carts', [
            'status' => 'recalled',
            'recalled_at' => date('Y-m-d H:i:s'),
        ]);

        $this->touch_cart($cart['id']);

        redirect(admin_url('omnipos/pos'));
    }

    public function checkout()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $shift = $this->get_open_shift();
        if (!$shift) {
            $this->json_response(false, 'Open a shift before checkout.');
        }

        $cart = $this->get_active_cart();
        if (!$cart) {
            $this->json_response(false, 'No active cart found.');
        }

        $cartItems = $this->get_active_cart_items();
        if (empty($cartItems)) {
            $this->json_response(false, 'Cannot checkout an empty cart.');
        }

        $clientId = (int) $this->input->post('client_id');
        if ($clientId < 1) {
            $clientId = $this->resolve_default_pos_client_id();
        }

        if ($clientId < 1) {
            $this->json_response(false, 'Client is required for invoice generation. Configure a default POS client or select one in POS.');
        }

        $paymentType = strtolower(trim((string) $this->input->post('payment_type', true)));
        if (!in_array($paymentType, ['cash', 'card', 'split', 'wallet'], true)) {
            $this->json_response(false, 'Payment type must be cash, card, split, or wallet.');
        }

        $cardBrand = trim((string) $this->input->post('card_brand', true));
        $cardAuthCode = trim((string) $this->input->post('card_auth_code', true));
        $cardLast4 = preg_replace('/[^0-9]/', '', (string) $this->input->post('card_last4', true));
        $walletStaffId = (int) $this->input->post('wallet_staff_id');
        $walletPin = preg_replace('/[^0-9]/', '', (string) $this->input->post('wallet_pin'));

        $totals = $this->calculate_cart_totals($cartItems, $cart);
        $pointsUsed = max(0, (int) $this->input->post('points_to_use'));
        $pointValue = (float) get_option('pos_loyalty_point_value');
        $pointValue = $pointValue > 0 ? $pointValue : 0.01;

        $discountFromPoints = 0;
        if ($pointsUsed > 0) {
            $balance = $this->get_loyalty_balance($clientId);
            if ($balance < $pointsUsed) {
                $this->json_response(false, 'Insufficient loyalty points.');
            }
            $discountFromPoints = round($pointsUsed * $pointValue, 2);
            $discountFromPoints = min($discountFromPoints, $totals['grand_total']);
        }

        $finalTotal = round(max(0, $totals['grand_total'] - $discountFromPoints), 2);

        $splitCashAmount = max(0, (float) $this->input->post('split_cash_amount'));
        $splitCardAmount = max(0, (float) $this->input->post('split_card_amount'));
        $cashReceived = max(0, (float) $this->input->post('cash_received'));

        if ($paymentType === 'card' || ($paymentType === 'split' && $splitCardAmount > 0)) {
            if ($cardBrand === '') {
                $cardBrand = 'Card';
            }

            if ($cardAuthCode === '') {
                $cardAuthCode = 'MANUAL-' . date('His');
            }

            if (strlen($cardLast4) !== 4) {
                $cardLast4 = '0000';
            }
        }

        if ($paymentType === 'split') {
            if ($splitCashAmount <= 0 || $splitCardAmount <= 0) {
                $this->json_response(false, 'Split checkout requires both cash and card amounts.');
            }

            if (abs(($splitCashAmount + $splitCardAmount) - $finalTotal) > 0.01) {
                $this->json_response(false, 'Split amounts must match invoice total.');
            }
        }

        if ($paymentType === 'cash' && $cashReceived <= 0) {
            $cashReceived = $finalTotal;
        }

        if ($paymentType === 'split' && $cashReceived <= 0) {
            $cashReceived = $splitCashAmount;
        }

        $changeDue = 0;
        if ($paymentType === 'cash') {
            $changeDue = max(0, $cashReceived - $finalTotal);
        }

        if ($paymentType === 'split') {
            $changeDue = max(0, $cashReceived - $splitCashAmount);
        }

        $walletStaff = null;
        $masterWallet = null;

        if ($paymentType === 'wallet') {
            if ($walletStaffId < 1) {
                $this->json_response(false, 'Wallet staff profile is required.');
            }

            $walletStaff = $this->db
                ->where('id', $walletStaffId)
                ->where('status', 'active')
                ->get(db_prefix() . 'pos_wallet_staff_accounts')
                ->row_array();

            if (!$walletStaff) {
                $this->json_response(false, 'Wallet staff account is not active or was not found.');
            }

            $this->reset_daily_spend_row_if_needed($walletStaff);
            $walletStaff = $this->db->where('id', $walletStaffId)->get(db_prefix() . 'pos_wallet_staff_accounts')->row_array();

            if (get_option('pos_wallet_require_pin') === '1') {
                if (strlen($walletPin) !== 4) {
                    $this->json_response(false, 'A valid 4-digit wallet PIN is required.');
                }

                if (empty($walletStaff['pin_hash']) || !password_verify($walletPin, $walletStaff['pin_hash'])) {
                    $this->json_response(false, 'Invalid wallet PIN.');
                }
            }

            $masterWallet = $this->db
                ->where('id', $walletStaff['wallet_account_id'])
                ->where('status', 'active')
                ->get(db_prefix() . 'pos_wallet_accounts')
                ->row_array();

            if (!$masterWallet) {
                $this->json_response(false, 'Master wallet account not found or inactive.');
            }

            if ((int) $masterWallet['client_id'] !== $clientId) {
                $this->json_response(false, 'Wallet account does not belong to the selected client.');
            }

            $available = min((float) $masterWallet['balance'], (float) $walletStaff['remaining_limit']);

            if ((float) $walletStaff['daily_limit'] > 0) {
                $dailyLeft = max(0, (float) $walletStaff['daily_limit'] - (float) $walletStaff['daily_spent']);
                $available = min($available, $dailyLeft);
            }

            if ($available < $finalTotal) {
                $this->json_response(false, 'Wallet balance/limits are insufficient for this checkout.');
            }
        }

        $newItems = [];

        foreach ($cartItems as $idx => $item) {
            $lineBase = (float) $item['qty'] * (float) $item['unit_price'];
            $lineRate = $item['qty'] > 0 ? round($lineBase / (float) $item['qty'], 2) : (float) $item['unit_price'];

            $modText = '';
            if (!empty($item['modifier_discount_type']) && (float) $item['modifier_discount_value'] > 0) {
                $modText .= ' | Discount: ' . $item['modifier_discount_type'] . ' ' . $item['modifier_discount_value'];
            }
            if ((float) $item['modifier_tax_rate'] > 0) {
                $modText .= ' | Tax: ' . $item['modifier_tax_rate'] . '%';
            }
            if (!empty($item['modifier_notes'])) {
                $modText .= ' | Notes: ' . $item['modifier_notes'];
            }

            $newItems[$idx + 1] = [
                'description'      => $item['description'],
                'long_description' => trim((string) $item['long_description'] . $modText),
                'qty'              => (float) $item['qty'],
                'rate'             => $lineRate,
                'unit'             => '',
            ];
        }

        if ($totals['global_discount'] > 0) {
            $newItems[count($newItems) + 1] = [
                'description'      => 'Global cart discount',
                'long_description' => 'Applied at checkout',
                'qty'              => 1,
                'rate'             => -$totals['global_discount'],
                'unit'             => '',
            ];
        }

        if ($totals['service_charge'] > 0) {
            $newItems[count($newItems) + 1] = [
                'description'      => 'Service charge',
                'long_description' => 'Applied at checkout',
                'qty'              => 1,
                'rate'             => $totals['service_charge'],
                'unit'             => '',
            ];
        }

        if ($discountFromPoints > 0) {
            $newItems[count($newItems) + 1] = [
                'description'      => 'Loyalty points redemption',
                'long_description' => 'Redeemed points: ' . $pointsUsed,
                'qty'              => 1,
                'rate'             => -$discountFromPoints,
                'unit'             => '',
            ];
        }

        $client = $this->clients_model->get($clientId);
        if (!$client) {
            $this->json_response(false, 'Invalid client selected.');
        }

        $invoiceData = [
            'clientid'         => $clientId,
            'number'           => get_option('next_invoice_number'),
            'date'             => date('Y-m-d'),
            'duedate'          => date('Y-m-d'),
            'currency'         => isset($client->default_currency) ? (int) $client->default_currency : 0,
            'billing_street'   => isset($client->billing_street) ? (string) $client->billing_street : '',
            'billing_city'     => isset($client->billing_city) ? (string) $client->billing_city : '',
            'billing_state'    => isset($client->billing_state) ? (string) $client->billing_state : '',
            'billing_zip'      => isset($client->billing_zip) ? (string) $client->billing_zip : '',
            'billing_country'  => isset($client->billing_country) ? (int) $client->billing_country : 0,
            'shipping_street'  => isset($client->shipping_street) ? (string) $client->shipping_street : '',
            'shipping_city'    => isset($client->shipping_city) ? (string) $client->shipping_city : '',
            'shipping_state'   => isset($client->shipping_state) ? (string) $client->shipping_state : '',
            'shipping_zip'     => isset($client->shipping_zip) ? (string) $client->shipping_zip : '',
            'shipping_country' => isset($client->shipping_country) ? (int) $client->shipping_country : 0,
            'newitems'         => $newItems,
            'allowed_payment_modes' => $this->get_allowed_payment_modes_by_type($paymentType),
        ];

        $invoiceId = $this->invoices_model->add($invoiceData);
        if (!$invoiceId) {
            $this->json_response(false, 'Invoice creation failed.');
        }

        $invoice = $this->invoices_model->get($invoiceId);
        $invoiceTotal = $invoice ? (float) $invoice->total : $finalTotal;

        $this->db->insert(db_prefix() . 'pos_transactions', [
            'shift_id'        => $shift['id'],
            'cart_id'         => $cart['id'],
            'invoice_id'      => $invoiceId,
            'staff_id'        => get_staff_user_id(),
            'client_id'       => $clientId,
            'subtotal'        => $totals['subtotal_before_adjustments'],
            'discount_total'  => $totals['line_discount_total'] + $totals['global_discount'] + $discountFromPoints,
            'total'           => $invoiceTotal,
            'payment_type'    => $paymentType,
            'card_brand'      => ($paymentType === 'card' || $paymentType === 'split') ? $cardBrand : null,
            'card_auth_code'  => ($paymentType === 'card' || $paymentType === 'split') ? $cardAuthCode : null,
            'card_last4'      => ($paymentType === 'card' || $paymentType === 'split') ? $cardLast4 : null,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $transactionId = (int) $this->db->insert_id();

        foreach ($cartItems as $item) {
            $this->db->insert(db_prefix() . 'pos_transaction_items', [
                'transaction_id' => $transactionId,
                'item_id'        => $item['item_id'],
                'qty'            => $item['qty'],
                'unit_price'     => $item['unit_price'],
                'line_total'     => $item['line_total'],
            ]);

            $this->decrement_stock_for_sale((int) $item['item_id'], (float) $item['qty'], $transactionId, (int) $shift['warehouse_id']);
        }

        $paymentEntries = [];

        if ($paymentType === 'cash') {
            $paymentEntries[] = [
                'type' => 'cash',
                'amount' => $invoiceTotal,
                'transaction_id' => 'POS-CASH-' . strtoupper(app_generate_hash()),
                'note' => 'Cash payment at register',
            ];
        } elseif ($paymentType === 'card') {
            $paymentEntries[] = [
                'type' => 'card',
                'amount' => $invoiceTotal,
                'transaction_id' => $cardAuthCode,
                'note' => 'Card ' . $cardBrand . ' ****' . $cardLast4,
            ];
        } elseif ($paymentType === 'wallet') {
            $paymentEntries[] = [
                'type' => 'wallet',
                'amount' => $invoiceTotal,
                'transaction_id' => 'POS-WALLET-' . strtoupper(app_generate_hash()),
                'note' => 'Wallet payment by staff #' . $walletStaffId,
            ];
        } else {
            $paymentEntries[] = [
                'type' => 'cash',
                'amount' => $splitCashAmount,
                'transaction_id' => 'POS-SPLIT-CASH-' . strtoupper(app_generate_hash()),
                'note' => 'Split cash payment',
            ];
            $paymentEntries[] = [
                'type' => 'card',
                'amount' => $splitCardAmount,
                'transaction_id' => $cardAuthCode,
                'note' => 'Split card ' . $cardBrand . ' ****' . $cardLast4,
            ];
        }

        $paymentIds = [];

        foreach ($paymentEntries as $entry) {
            $modeId = $this->resolve_payment_mode_id($entry['type']);

            $paymentId = (int) $this->payments_model->add([
                'amount'        => $entry['amount'],
                'invoiceid'     => $invoiceId,
                'paymentmode'   => $modeId,
                'date'          => date('Y-m-d H:i:s'),
                'transactionid' => $entry['transaction_id'],
                'note'          => $entry['note'],
            ]);

            $paymentIds[] = $paymentId;

            $this->db->insert(db_prefix() . 'pos_payment_logs', [
                'transaction_id'     => $transactionId,
                'invoice_payment_id' => $paymentId > 0 ? $paymentId : null,
                'payment_type'       => $entry['type'],
                'amount'             => $entry['amount'],
                'card_brand'         => $entry['type'] === 'card' ? $cardBrand : null,
                'card_auth_code'     => $entry['type'] === 'card' ? $cardAuthCode : null,
                'card_last4'         => $entry['type'] === 'card' ? $cardLast4 : null,
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
        }

        if ($paymentType === 'wallet' && $walletStaff && $masterWallet) {
            $newMasterBalance = (float) $masterWallet['balance'] - $invoiceTotal;
            $newRemaining = (float) $walletStaff['remaining_limit'] - $invoiceTotal;
            $newDailySpent = (float) $walletStaff['daily_spent'] + $invoiceTotal;

            $this->db->where('id', $masterWallet['id']);
            $this->db->update(db_prefix() . 'pos_wallet_accounts', [
                'balance' => $newMasterBalance,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->where('id', $walletStaff['id']);
            $this->db->update(db_prefix() . 'pos_wallet_staff_accounts', [
                'remaining_limit' => $newRemaining,
                'daily_spent' => $newDailySpent,
                'daily_spent_date' => date('Y-m-d'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->insert(db_prefix() . 'pos_wallet_ledger', [
                'wallet_account_id' => $masterWallet['id'],
                'staff_wallet_id' => $walletStaff['id'],
                'entry_type' => 'pos_wallet_debit',
                'amount' => -$invoiceTotal,
                'reference_type' => 'transaction',
                'reference_id' => $transactionId,
                'notes' => 'POS wallet payment for invoice #' . $invoiceId,
                'created_by' => get_staff_user_id(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($pointsUsed > 0) {
            $this->add_loyalty_entry($clientId, $transactionId, 'redeem', -$pointsUsed, 'Redeemed on checkout');
        }

        $earnRate = (float) get_option('pos_loyalty_earn_rate');
        if ($earnRate <= 0) {
            $earnRate = 1;
        }

        $pointsEarned = (int) floor($invoiceTotal * $earnRate);
        if ($pointsEarned > 0) {
            $this->add_loyalty_entry($clientId, $transactionId, 'earn', $pointsEarned, 'Earned on purchase');
        }

        $this->db->where('id', $cart['id']);
        $this->db->update(db_prefix() . 'pos_carts', [
            'status' => 'checked_out',
            'client_id' => $clientId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->json_response(true, 'Checkout completed.', [
            'invoice_id' => $invoiceId,
            'transaction_id' => $transactionId,
            'payment_ids' => $paymentIds,
            'points_earned' => $pointsEarned,
            'points_used' => $pointsUsed,
            'change_due' => round($changeDue, 2),
            'wallet_staff_id' => $paymentType === 'wallet' ? $walletStaffId : null,
        ]);
    }

    public function recent_transactions()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $rows = $this->db
            ->select('id, invoice_id, client_id, payment_type, total, created_at')
            ->order_by('id', 'DESC')
            ->limit(20)
            ->get(db_prefix() . 'pos_transactions')
            ->result_array();

        $this->json_response(true, 'Recent transactions loaded.', [
            'rows' => $rows,
        ]);
    }

    public function refund_transaction()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $shift = $this->get_open_shift();
        if (!$shift) {
            $this->json_response(false, 'Open a shift before processing refunds.');
        }

        $originalTransactionId = (int) $this->input->post('transaction_id');
        $refundType = strtolower(trim((string) $this->input->post('refund_type', true)));
        $reason = trim((string) $this->input->post('reason', true));
        $itemsJson = (string) $this->input->post('refund_items');

        if ($originalTransactionId < 1) {
            $this->json_response(false, 'Transaction ID is required.');
        }

        if (!in_array($refundType, ['cash', 'card', 'wallet'], true)) {
            $refundType = 'cash';
        }

        $original = $this->db
            ->where('id', $originalTransactionId)
            ->get(db_prefix() . 'pos_transactions')
            ->row_array();

        if (!$original) {
            $this->json_response(false, 'Original transaction not found.');
        }

        $requestMap = $this->parse_refund_request_items($itemsJson);
        if ($requestMap === false) {
            $this->json_response(false, 'Invalid refund item payload.');
        }

        $lines = $this->build_refund_lines((int) $original['id'], $requestMap);
        if (empty($lines)) {
            $this->json_response(false, 'No refundable quantity remains for the selected transaction/items.');
        }

        $refundAmount = 0.0;
        foreach ($lines as $line) {
            $refundAmount += (float) $line['line_total'];
        }
        $refundAmount = round($refundAmount, 2);

        if ($refundAmount <= 0) {
            $this->json_response(false, 'Calculated refund amount is zero.');
        }

        $warehouseId = $this->get_transaction_warehouse_id($original);

        $this->db->insert(db_prefix() . 'pos_refunds', [
            'original_transaction_id' => (int) $original['id'],
            'shift_id' => (int) $shift['id'],
            'staff_id' => get_staff_user_id(),
            'refund_type' => $refundType,
            'amount' => $refundAmount,
            'reason' => $reason !== '' ? $reason : 'POS refund',
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $refundId = (int) $this->db->insert_id();

        $this->db->insert(db_prefix() . 'pos_transactions', [
            'shift_id'        => (int) $shift['id'],
            'cart_id'         => (int) $original['cart_id'],
            'invoice_id'      => (int) $original['invoice_id'],
            'staff_id'        => get_staff_user_id(),
            'client_id'       => (int) $original['client_id'],
            'subtotal'        => -$refundAmount,
            'discount_total'  => 0,
            'total'           => -$refundAmount,
            'payment_type'    => $refundType,
            'card_brand'      => null,
            'card_auth_code'  => null,
            'card_last4'      => null,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $refundTransactionId = (int) $this->db->insert_id();

        foreach ($lines as $line) {
            $this->db->insert(db_prefix() . 'pos_refund_items', [
                'refund_id' => $refundId,
                'transaction_item_id' => (int) $line['transaction_item_id'],
                'item_id' => (int) $line['item_id'],
                'qty' => (float) $line['qty'],
                'unit_price' => (float) $line['unit_price'],
                'line_total' => (float) $line['line_total'],
            ]);

            $this->db->insert(db_prefix() . 'pos_transaction_items', [
                'transaction_id' => $refundTransactionId,
                'item_id' => (int) $line['item_id'],
                'qty' => -(float) $line['qty'],
                'unit_price' => (float) $line['unit_price'],
                'line_total' => -(float) $line['line_total'],
            ]);

            $this->increment_stock_for_refund((int) $line['item_id'], (float) $line['qty'], (int) $original['id'], (int) $warehouseId);
        }

        $this->db->where('id', $refundId);
        $this->db->update(db_prefix() . 'pos_refunds', [
            'refund_transaction_id' => $refundTransactionId,
        ]);

        $this->db->insert(db_prefix() . 'pos_payment_logs', [
            'transaction_id' => $refundTransactionId,
            'invoice_payment_id' => null,
            'payment_type' => $refundType,
            'amount' => -$refundAmount,
            'card_brand' => null,
            'card_auth_code' => null,
            'card_last4' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->reverse_loyalty_for_refund((int) $original['client_id'], (int) $original['id'], $refundTransactionId);

        $this->json_response(true, 'Refund completed successfully.', [
            'refund_id' => $refundId,
            'refund_transaction_id' => $refundTransactionId,
            'amount' => $refundAmount,
            'warehouse_id' => (int) $warehouseId,
        ]);
    }

    private function add_item_to_cart($item, $qty)
    {
        $shift = $this->get_open_shift();
        if (!$shift) {
            $this->json_response(false, 'Open a shift before adding items.');
        }

        $itemId = (int) $item['itemid'];

        $cart = $this->get_or_create_active_cart($shift['id']);

        $existingItem = $this->db
            ->where('cart_id', $cart['id'])
            ->where('item_id', $itemId)
            ->get(db_prefix() . 'pos_cart_items')
            ->row_array();

        $targetQty = $qty;
        if ($existingItem) {
            $targetQty += (float) $existingItem['qty'];
        }

        if (!$this->can_sell_qty($itemId, $targetQty)) {
            $this->json_response(false, 'Item is out of stock and zero-stock lockout is active.');
        }

        if ($existingItem) {
            $this->db->where('id', $existingItem['id']);
            $this->db->update(db_prefix() . 'pos_cart_items', [
                'qty' => $targetQty,
            ]);
        } else {
            $this->db->insert(db_prefix() . 'pos_cart_items', [
                'cart_id'           => $cart['id'],
                'item_id'           => $itemId,
                'description'       => $item['description'],
                'long_description'  => isset($item['long_description']) ? $item['long_description'] : '',
                'qty'               => $qty,
                'unit_price'        => (float) $item['rate'],
                'line_total'        => (float) $item['rate'] * $qty,
                'modifier_discount_type' => '',
                'modifier_discount_value' => 0,
                'modifier_tax_rate' => (float) get_option('pos_default_tax_rate'),
                'modifier_notes' => null,
            ]);
        }

        $this->touch_cart($cart['id']);

        $items = $this->get_active_cart_items();

        $this->json_response(true, 'Item added to cart.', [
            'item' => $item,
            'cart' => $this->get_active_cart(),
            'cart_items' => $items,
            'totals' => $this->calculate_cart_totals($items, $this->get_active_cart()),
        ]);
    }

    private function get_open_shift()
    {
        return $this->db
            ->where('staff_id', get_staff_user_id())
            ->where('status', 'open')
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'pos_shifts')
            ->row_array();
    }

    private function get_or_create_active_cart($shiftId)
    {
        $cart = $this->get_active_cart();

        if ($cart) {
            return $cart;
        }

        $insert = [
            'staff_id'    => get_staff_user_id(),
            'shift_id'    => $shiftId,
            'status'      => 'active',
            'global_discount_type' => '',
            'global_discount_value' => 0,
            'service_charge_value' => 0,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $this->db->insert(db_prefix() . 'pos_carts', $insert);

        return $this->db->where('id', (int) $this->db->insert_id())->get(db_prefix() . 'pos_carts')->row_array();
    }

    private function get_active_cart()
    {
        return $this->db
            ->where('staff_id', get_staff_user_id())
            ->where('status', 'active')
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'pos_carts')
            ->row_array();
    }

    private function get_active_cart_items()
    {
        $cart = $this->get_active_cart();

        if (!$cart) {
            return [];
        }

        $items = $this->db
            ->where('cart_id', $cart['id'])
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_cart_items')
            ->result_array();

        $didUpdate = false;
        foreach ($items as &$item) {
            $line = $this->calculate_line_total($item);
            if (abs((float) $item['line_total'] - $line['line_total']) > 0.0001) {
                $this->db->where('id', $item['id']);
                $this->db->update(db_prefix() . 'pos_cart_items', ['line_total' => $line['line_total']]);
                $item['line_total'] = $line['line_total'];
                $didUpdate = true;
            }

            $item['line_discount_amount'] = $line['line_discount'];
            $item['line_tax_amount'] = $line['line_tax'];
            $item['line_subtotal'] = $line['line_subtotal'];
        }

        if ($didUpdate) {
            $this->touch_cart($cart['id']);
        }

        return $items;
    }

    private function calculate_line_total($item)
    {
        $qty = (float) $item['qty'];
        $unit = (float) $item['unit_price'];
        $base = round($qty * $unit, 2);

        $discountType = isset($item['modifier_discount_type']) ? strtolower((string) $item['modifier_discount_type']) : '';
        $discountValue = isset($item['modifier_discount_value']) ? (float) $item['modifier_discount_value'] : 0;

        $discountAmount = 0;
        if ($discountType === 'percent') {
            $discountAmount = round(($base * max(0, min(100, $discountValue))) / 100, 2);
        } elseif ($discountType === 'fixed') {
            $discountAmount = min($base, round(max(0, $discountValue), 2));
        }

        $subtotalAfterDiscount = max(0, $base - $discountAmount);

        $taxRate = isset($item['modifier_tax_rate']) ? (float) $item['modifier_tax_rate'] : 0;
        $taxAmount = round(($subtotalAfterDiscount * max(0, $taxRate)) / 100, 2);

        return [
            'line_subtotal' => $base,
            'line_discount' => $discountAmount,
            'line_tax' => $taxAmount,
            'line_total' => round($subtotalAfterDiscount + $taxAmount, 2),
        ];
    }

    private function touch_cart($cartId)
    {
        $this->db->where('id', (int) $cartId);
        $this->db->update(db_prefix() . 'pos_carts', [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function find_item_by_barcode($barcode)
    {
        $table = db_prefix() . 'items';

        $this->db->select(db_prefix() . 'items.id as itemid, description, long_description, rate, group_id');
        $this->db->from($table);
        $this->db->group_start();

        if ($this->db->field_exists('barcode', $table)) {
            $this->db->where('barcode', $barcode);
        }

        if ($this->db->field_exists('sku', $table)) {
            $this->db->or_where('sku', $barcode);
        }

        $this->db->or_where('description', $barcode);
        $this->db->group_end();
        $this->db->limit(1);

        return $this->db->get()->row_array();
    }

    private function calculate_cart_totals($cartItems, $cart = null)
    {
        if (!$cart) {
            $cart = $this->get_active_cart();
        }

        $lineSubtotal = 0;
        $lineDiscount = 0;
        $taxTotal = 0;

        foreach ($cartItems as $item) {
            $line = $this->calculate_line_total($item);
            $lineSubtotal += $line['line_subtotal'];
            $lineDiscount += $line['line_discount'];
            $taxTotal += $line['line_tax'];
        }

        $subtotalAfterLineDiscount = max(0, $lineSubtotal - $lineDiscount);

        $globalDiscount = 0;
        $globalDiscountType = isset($cart['global_discount_type']) ? strtolower((string) $cart['global_discount_type']) : '';
        $globalDiscountValue = isset($cart['global_discount_value']) ? (float) $cart['global_discount_value'] : 0;

        if ($globalDiscountType === 'percent') {
            $globalDiscount = round(($subtotalAfterLineDiscount * max(0, min(100, $globalDiscountValue))) / 100, 2);
        } elseif ($globalDiscountType === 'fixed') {
            $globalDiscount = min($subtotalAfterLineDiscount, round(max(0, $globalDiscountValue), 2));
        }

        $serviceCharge = isset($cart['service_charge_value']) ? round(max(0, (float) $cart['service_charge_value']), 2) : 0;

        $grandTotal = round(max(0, ($subtotalAfterLineDiscount - $globalDiscount) + $taxTotal + $serviceCharge), 2);

        return [
            'subtotal_before_adjustments' => round($lineSubtotal, 2),
            'line_discount_total' => round($lineDiscount, 2),
            'subtotal_after_line_discount' => round($subtotalAfterLineDiscount, 2),
            'tax_total' => round($taxTotal, 2),
            'global_discount' => round($globalDiscount, 2),
            'service_charge' => round($serviceCharge, 2),
            'grand_total' => $grandTotal,
        ];
    }

    private function decrement_stock_for_sale($itemId, $qty, $transactionId, $warehouseId)
    {
        if ($warehouseId < 1) {
            $warehouseId = 1;
        }

        $stock = $this->db
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->get(db_prefix() . 'pos_storeroom_stock')
            ->row_array();

        if ($stock) {
            $newQty = (float) $stock['qty_on_hand'] - $qty;

            $this->db->where('id', $stock['id']);
            $this->db->update(db_prefix() . 'pos_storeroom_stock', [
                'qty_on_hand' => $newQty,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $newQty = -$qty;
            $this->db->insert(db_prefix() . 'pos_storeroom_stock', [
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'group_id' => 0,
                'qty_on_hand' => $newQty,
                'reorder_level' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->insert(db_prefix() . 'pos_inventory_ledger', [
            'warehouse_id' => $warehouseId,
            'to_warehouse_id' => null,
            'item_id' => $itemId,
            'entry_type' => 'sale',
            'qty_change' => -$qty,
            'qty_after' => $newQty,
            'reason_code' => 'POS_SALE',
            'reference_type' => 'transaction',
            'reference_id' => $transactionId,
            'notes' => 'Auto decrement from POS sale',
            'created_by' => get_staff_user_id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function increment_stock_for_refund($itemId, $qty, $originalTransactionId, $warehouseId)
    {
        if ($warehouseId < 1) {
            $warehouseId = $this->get_active_warehouse_id();
        }

        $stock = $this->db
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->get(db_prefix() . 'pos_storeroom_stock')
            ->row_array();

        if ($stock) {
            $newQty = (float) $stock['qty_on_hand'] + $qty;

            $this->db->where('id', $stock['id']);
            $this->db->update(db_prefix() . 'pos_storeroom_stock', [
                'qty_on_hand' => $newQty,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $newQty = $qty;
            $item = $this->db->where('id', $itemId)->get(db_prefix() . 'items')->row_array();

            $this->db->insert(db_prefix() . 'pos_storeroom_stock', [
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'group_id' => isset($item['group_id']) ? (int) $item['group_id'] : 0,
                'qty_on_hand' => $newQty,
                'reorder_level' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->insert(db_prefix() . 'pos_inventory_ledger', [
            'warehouse_id' => $warehouseId,
            'to_warehouse_id' => null,
            'item_id' => $itemId,
            'entry_type' => 'refund_return',
            'qty_change' => $qty,
            'qty_after' => $newQty,
            'reason_code' => 'POS_REFUND',
            'reference_type' => 'transaction',
            'reference_id' => $originalTransactionId,
            'notes' => 'Auto restock from POS refund',
            'created_by' => get_staff_user_id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function parse_refund_request_items($itemsJson)
    {
        $itemsJson = trim((string) $itemsJson);
        if ($itemsJson === '') {
            return [];
        }

        $decoded = json_decode($itemsJson, true);
        if (!is_array($decoded)) {
            return false;
        }

        $map = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $itemId = isset($row['item_id']) ? (int) $row['item_id'] : 0;
            $qty = isset($row['qty']) ? (float) $row['qty'] : 0;

            if ($itemId < 1 || $qty <= 0) {
                continue;
            }

            if (!isset($map[$itemId])) {
                $map[$itemId] = 0;
            }

            $map[$itemId] += $qty;
        }

        return $map;
    }

    private function build_refund_lines($originalTransactionId, $requestMap)
    {
        $sourceLines = $this->db
            ->where('transaction_id', $originalTransactionId)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_transaction_items')
            ->result_array();

        if (empty($sourceLines)) {
            return [];
        }

        $refundedRows = $this->db
            ->select('ri.transaction_item_id, SUM(ri.qty) as refunded_qty', false)
            ->from(db_prefix() . 'pos_refund_items as ri')
            ->join(db_prefix() . 'pos_refunds as r', 'r.id = ri.refund_id', 'inner')
            ->where('r.original_transaction_id', (int) $originalTransactionId)
            ->where('r.status', 'completed')
            ->group_by('ri.transaction_item_id')
            ->get()
            ->result_array();

        $refundedMap = [];
        foreach ($refundedRows as $r) {
            $refundedMap[(int) $r['transaction_item_id']] = (float) $r['refunded_qty'];
        }

        $isPartialRequest = !empty($requestMap);
        $lines = [];

        foreach ($sourceLines as $line) {
            $transactionItemId = (int) $line['id'];
            $itemId = (int) $line['item_id'];
            $soldQty = max(0, (float) $line['qty']);

            if ($soldQty <= 0) {
                continue;
            }

            $alreadyRefunded = isset($refundedMap[$transactionItemId]) ? max(0, (float) $refundedMap[$transactionItemId]) : 0;
            $remaining = round(max(0, $soldQty - $alreadyRefunded), 4);

            if ($remaining <= 0) {
                continue;
            }

            if ($isPartialRequest && !isset($requestMap[$itemId])) {
                continue;
            }

            $requestedQty = $isPartialRequest ? (float) $requestMap[$itemId] : $remaining;
            $refundQty = min($remaining, max(0, $requestedQty));
            if ($refundQty <= 0) {
                continue;
            }

            $lineRate = $soldQty > 0 ? ((float) $line['line_total'] / $soldQty) : (float) $line['unit_price'];
            $refundLineTotal = round($lineRate * $refundQty, 2);

            $lines[] = [
                'transaction_item_id' => $transactionItemId,
                'item_id' => $itemId,
                'qty' => $refundQty,
                'unit_price' => (float) $line['unit_price'],
                'line_total' => $refundLineTotal,
            ];

            if ($isPartialRequest) {
                $requestMap[$itemId] = max(0, (float) $requestMap[$itemId] - $refundQty);
            }
        }

        return $lines;
    }

    private function get_transaction_warehouse_id($transaction)
    {
        if (!isset($transaction['shift_id'])) {
            return $this->get_active_warehouse_id();
        }

        $shift = $this->db
            ->select('warehouse_id')
            ->where('id', (int) $transaction['shift_id'])
            ->get(db_prefix() . 'pos_shifts')
            ->row_array();

        if ($shift && isset($shift['warehouse_id']) && (int) $shift['warehouse_id'] > 0) {
            return (int) $shift['warehouse_id'];
        }

        return $this->get_active_warehouse_id();
    }

    private function reverse_loyalty_for_refund($clientId, $originalTransactionId, $refundTransactionId)
    {
        $row = $this->db
            ->select('SUM(points) as points_delta', false)
            ->where('transaction_id', (int) $originalTransactionId)
            ->get(db_prefix() . 'pos_loyalty_ledger')
            ->row_array();

        $pointsDelta = $row ? (int) $row['points_delta'] : 0;
        if ($pointsDelta === 0) {
            return;
        }

        $this->add_loyalty_entry((int) $clientId, (int) $refundTransactionId, 'refund_adjust', -$pointsDelta, 'Auto loyalty reversal from refund of transaction #' . (int) $originalTransactionId);
    }

    private function get_stock_map($warehouseId = null)
    {
        if ($warehouseId === null || (int) $warehouseId < 1) {
            $warehouseId = $this->get_active_warehouse_id();
        }

        $rows = $this->db
            ->select('item_id, qty_on_hand')
            ->where('warehouse_id', (int) $warehouseId)
            ->get(db_prefix() . 'pos_storeroom_stock')
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['item_id']] = (float) $row['qty_on_hand'];
        }

        return $map;
    }

    private function is_zero_stock_locked()
    {
        return get_option('pos_allow_zero_stock_sales') !== '1';
    }

    private function can_sell_qty($itemId, $targetQty)
    {
        if (!$this->is_zero_stock_locked()) {
            return true;
        }

        $warehouseId = $this->get_active_warehouse_id();

        $stock = $this->db
            ->select('qty_on_hand')
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->get(db_prefix() . 'pos_storeroom_stock')
            ->row_array();

        $qtyOnHand = $stock ? (float) $stock['qty_on_hand'] : 0;

        return $qtyOnHand >= (float) $targetQty;
    }

    private function get_active_warehouse_id()
    {
        $shift = $this->get_open_shift();
        if ($shift && isset($shift['warehouse_id']) && (int) $shift['warehouse_id'] > 0) {
            return (int) $shift['warehouse_id'];
        }

        $defaultId = (int) get_option('pos_default_warehouse_id');
        if ($defaultId > 0) {
            return $defaultId;
        }

        $warehouse = $this->db
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_warehouses')
            ->row_array();

        return $warehouse ? (int) $warehouse['id'] : 1;
    }

    private function resolve_default_pos_client_id()
    {
        $configuredClientId = (int) get_option('pos_default_client_id');
        if ($configuredClientId > 0) {
            $client = $this->clients_model->get($configuredClientId);
            if ($client) {
                return $configuredClientId;
            }
        }

        $row = $this->db
            ->select('userid')
            ->from(db_prefix() . 'clients')
            ->order_by('userid', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        return $row ? (int) $row['userid'] : 0;
    }

    private function resolve_open_shift_warehouse_id($requestedWarehouseId)
    {
        $requestedWarehouseId = (int) $requestedWarehouseId;

        if ($requestedWarehouseId > 0) {
            $warehouse = $this->db
                ->where('id', $requestedWarehouseId)
                ->where('is_active', 1)
                ->get(db_prefix() . 'pos_warehouses')
                ->row_array();

            if ($warehouse) {
                return (int) $warehouse['id'];
            }
        }

        $defaultWarehouseId = (int) get_option('pos_default_warehouse_id');
        if ($defaultWarehouseId > 0) {
            $warehouse = $this->db
                ->where('id', $defaultWarehouseId)
                ->where('is_active', 1)
                ->get(db_prefix() . 'pos_warehouses')
                ->row_array();

            if ($warehouse) {
                return (int) $warehouse['id'];
            }
        }

        $warehouse = $this->db
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_warehouses')
            ->row_array();

        if ($warehouse) {
            return (int) $warehouse['id'];
        }

        $this->db->insert(db_prefix() . 'pos_warehouses', [
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $newWarehouseId = (int) $this->db->insert_id();
        if ($newWarehouseId > 0) {
            update_option('pos_default_warehouse_id', (string) $newWarehouseId);

            return $newWarehouseId;
        }

        return 1;
    }

    private function get_wallet_staff_by_barcode($barcode)
    {
        return $this->db
            ->where('barcode', $barcode)
            ->get(db_prefix() . 'pos_wallet_staff_accounts')
            ->row_array();
    }

    private function reset_daily_spend_row_if_needed($staff)
    {
        $today = date('Y-m-d');

        if (isset($staff['daily_spent_date']) && $staff['daily_spent_date'] === $today) {
            return;
        }

        $this->db->where('id', (int) $staff['id']);
        $this->db->update(db_prefix() . 'pos_wallet_staff_accounts', [
            'daily_spent' => 0,
            'daily_spent_date' => $today,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function add_loyalty_entry($clientId, $transactionId, $entryType, $points, $notes)
    {
        $this->db->insert(db_prefix() . 'pos_loyalty_ledger', [
            'client_id' => $clientId,
            'transaction_id' => $transactionId,
            'entry_type' => $entryType,
            'points' => $points,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $balanceRow = $this->db
            ->where('client_id', $clientId)
            ->get(db_prefix() . 'pos_loyalty_balances')
            ->row_array();

        if ($balanceRow) {
            $newBalance = (int) $balanceRow['points_balance'] + (int) $points;
            if ($newBalance < 0) {
                $newBalance = 0;
            }

            $this->db->where('id', $balanceRow['id']);
            $this->db->update(db_prefix() . 'pos_loyalty_balances', [
                'points_balance' => $newBalance,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->db->insert(db_prefix() . 'pos_loyalty_balances', [
                'client_id' => $clientId,
                'points_balance' => max(0, (int) $points),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function get_loyalty_balance($clientId)
    {
        $row = $this->db
            ->select('points_balance')
            ->where('client_id', $clientId)
            ->get(db_prefix() . 'pos_loyalty_balances')
            ->row_array();

        if (!$row) {
            return 0;
        }

        return (int) $row['points_balance'];
    }

    private function get_allowed_payment_modes_by_type($paymentType)
    {
        if ($paymentType === 'split') {
            return [
                $this->resolve_payment_mode_id('cash'),
                $this->resolve_payment_mode_id('card'),
            ];
        }

        if ($paymentType === 'wallet') {
            return [$this->resolve_payment_mode_id('wallet')];
        }

        return [$this->resolve_payment_mode_id($paymentType)];
    }

    private function resolve_payment_mode_id($paymentType)
    {
        $paymentType = strtolower((string) $paymentType);

        if ($paymentType === 'cash') {
            $row = $this->db
                ->like('name', 'cash')
                ->where('active', 1)
                ->order_by('id', 'ASC')
                ->get(db_prefix() . 'payment_modes')
                ->row_array();

            if ($row) {
                return (int) $row['id'];
            }
        }

        if ($paymentType === 'card') {
            $row = $this->db
                ->group_start()
                ->like('name', 'card')
                ->or_like('name', 'credit')
                ->or_like('name', 'debit')
                ->group_end()
                ->where('active', 1)
                ->order_by('id', 'ASC')
                ->get(db_prefix() . 'payment_modes')
                ->row_array();

            if ($row) {
                return (int) $row['id'];
            }
        }

        if ($paymentType === 'wallet') {
            $row = $this->db
                ->like('name', 'wallet')
                ->where('active', 1)
                ->order_by('id', 'ASC')
                ->get(db_prefix() . 'payment_modes')
                ->row_array();

            if ($row) {
                return (int) $row['id'];
            }
        }

        $fallback = $this->db
            ->where('active', 1)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'payment_modes')
            ->row_array();

        return $fallback ? (int) $fallback['id'] : 0;
    }

    private function sub_query_transaction_ids($shiftId)
    {
        return "(SELECT id FROM " . db_prefix() . "pos_transactions WHERE shift_id = " . (int) $shiftId . ")";
    }

    private function json_response($success, $message, $data = [])
    {
        $payload = [
            'success' => (bool) $success,
            'message' => $message,
            'data' => $data,
        ];

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));

        exit;
    }

    private function parse_setting_lines($raw, $fallback, $uppercase = false)
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
