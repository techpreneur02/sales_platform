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
        $data['items_groups'] = $this->invoice_items_model->get_groups();
        $data['items'] = $this->invoice_items_model->get('', [], true);
        $data['cart'] = $this->get_active_cart();
        $data['cart_items'] = $this->get_active_cart_items();
        $data['current_shift'] = $this->get_open_shift();
        $data['suspended_carts'] = $this->db
            ->where('staff_id', get_staff_user_id())
            ->where('status', 'suspended')
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'pos_suspended_carts')
            ->result_array();

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

        $this->load->view('omnipos/pos/shifts', $data);
    }

    public function cart()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->json_response(true, 'Cart loaded', [
            'cart' => $this->get_active_cart(),
            'items' => $this->get_active_cart_items(),
            'totals' => $this->calculate_cart_totals($this->get_active_cart_items()),
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

        $this->db->insert(db_prefix() . 'pos_shifts', $insertData);
        $shiftId = (int) $this->db->insert_id();

        $this->json_response(true, 'Shift opened successfully.', [
            'shift_id' => $shiftId,
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

        $expectedCash = (float) $this->db
            ->select_sum('amount')
            ->where('payment_type', 'cash')
            ->where('transaction_id IN ' . $this->sub_query_transaction_ids($shift['id']), null, false)
            ->get(db_prefix() . 'pos_payment_logs')
            ->row()->amount;

        $expectedCard = (float) $this->db
            ->select_sum('amount')
            ->where('payment_type', 'card')
            ->where('transaction_id IN ' . $this->sub_query_transaction_ids($shift['id']), null, false)
            ->get(db_prefix() . 'pos_payment_logs')
            ->row()->amount;

        $expectedCashWithFloat = $expectedCash + (float) $shift['opening_float'];

        $this->db->where('id', $shift['id']);
        $this->db->update(db_prefix() . 'pos_shifts', [
            'closed_at'              => date('Y-m-d H:i:s'),
            'status'                 => 'closed',
            'closing_expected_cash'  => $expectedCashWithFloat,
            'closing_counted_cash'   => $countedCash,
            'closing_expected_card'  => $expectedCard,
            'closing_counted_card'   => $countedCard,
            'cash_variance'          => $countedCash - $expectedCashWithFloat,
            'card_variance'          => $countedCard - $expectedCard,
            'notes'                  => $notes,
        ]);

        $this->db->insert(db_prefix() . 'pos_shift_blind_counts', [
            'shift_id'       => $shift['id'],
            'counted_cash'   => $countedCash,
            'counted_card'   => $countedCard,
            'terminal_total' => $terminalTotal,
            'notes'          => $notes,
            'counted_by'     => get_staff_user_id(),
            'counted_at'     => date('Y-m-d H:i:s'),
        ]);

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
            $this->json_response(false, 'No product found for scanned barcode.');
        }

        $cart = $this->get_or_create_active_cart($shift['id']);

        $lineRate = (float) $item['rate'];

        $existingItem = $this->db
            ->where('cart_id', $cart['id'])
            ->where('item_id', $item['itemid'])
            ->get(db_prefix() . 'pos_cart_items')
            ->row_array();

        if ($existingItem) {
            $qty = (float) $existingItem['qty'] + 1;
            $this->db->where('id', $existingItem['id']);
            $this->db->update(db_prefix() . 'pos_cart_items', [
                'qty' => $qty,
                'line_total' => $qty * $lineRate,
            ]);
        } else {
            $this->db->insert(db_prefix() . 'pos_cart_items', [
                'cart_id'           => $cart['id'],
                'item_id'           => $item['itemid'],
                'description'       => $item['description'],
                'long_description'  => $item['long_description'],
                'qty'               => 1,
                'unit_price'        => $lineRate,
                'line_total'        => $lineRate,
            ]);
        }

        $this->touch_cart($cart['id']);

        $items = $this->get_active_cart_items();

        $this->json_response(true, 'Item added to cart.', [
            'item' => $item,
            'cart' => $this->get_active_cart(),
            'cart_items' => $items,
            'totals' => $this->calculate_cart_totals($items),
        ]);
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
            $this->json_response(false, 'Client is required for invoice generation.');
        }

        $paymentType = strtolower(trim((string) $this->input->post('payment_type', true)));
        if (!in_array($paymentType, ['cash', 'card'], true)) {
            $this->json_response(false, 'Payment type must be cash or card.');
        }

        $cardBrand = trim((string) $this->input->post('card_brand', true));
        $cardAuthCode = trim((string) $this->input->post('card_auth_code', true));
        $cardLast4 = preg_replace('/[^0-9]/', '', (string) $this->input->post('card_last4', true));

        if ($paymentType === 'card') {
            if ($cardBrand === '' || $cardAuthCode === '' || strlen($cardLast4) !== 4) {
                $this->json_response(false, 'Card brand, auth code and last 4 digits are required for card payment.');
            }
        }

        $totals = $this->calculate_cart_totals($cartItems);
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
            $discountFromPoints = min($discountFromPoints, $totals['subtotal']);
        }

        $newItems = [];
        foreach ($cartItems as $idx => $item) {
            $newItems[$idx + 1] = [
                'description'      => $item['description'],
                'long_description' => $item['long_description'],
                'qty'              => (float) $item['qty'],
                'rate'             => (float) $item['unit_price'],
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
            'subtotal'         => $totals['subtotal'],
            'total'            => max(0, $totals['subtotal'] - $discountFromPoints),
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
            'allowed_payment_modes' => [$this->resolve_payment_mode_id($paymentType)],
        ];

        $invoiceId = $this->invoices_model->add($invoiceData);
        if (!$invoiceId) {
            $this->json_response(false, 'Invoice creation failed.');
        }

        $invoice = $this->invoices_model->get($invoiceId);
        $invoiceTotal = $invoice ? (float) $invoice->total : max(0, $totals['subtotal'] - $discountFromPoints);

        $paymentData = [
            'amount'        => $invoiceTotal,
            'invoiceid'     => $invoiceId,
            'paymentmode'   => $this->resolve_payment_mode_id($paymentType),
            'date'          => date('Y-m-d H:i:s'),
            'transactionid' => $paymentType === 'card' ? $cardAuthCode : 'POS-CASH-' . strtoupper(app_generate_hash()),
            'note'          => $paymentType === 'card' ? 'Card ' . $cardBrand . ' ****' . $cardLast4 : 'Cash payment at register',
        ];

        $invoicePaymentId = (int) $this->payments_model->add($paymentData);

        $this->db->insert(db_prefix() . 'pos_transactions', [
            'shift_id'        => $shift['id'],
            'cart_id'         => $cart['id'],
            'invoice_id'      => $invoiceId,
            'staff_id'        => get_staff_user_id(),
            'client_id'       => $clientId,
            'subtotal'        => $totals['subtotal'],
            'discount_total'  => $discountFromPoints,
            'total'           => $invoiceTotal,
            'payment_type'    => $paymentType,
            'card_brand'      => $paymentType === 'card' ? $cardBrand : null,
            'card_auth_code'  => $paymentType === 'card' ? $cardAuthCode : null,
            'card_last4'      => $paymentType === 'card' ? $cardLast4 : null,
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

            $this->decrement_stock_for_sale((int) $item['item_id'], (float) $item['qty'], $transactionId);
        }

        $this->db->insert(db_prefix() . 'pos_payment_logs', [
            'transaction_id'     => $transactionId,
            'invoice_payment_id' => $invoicePaymentId > 0 ? $invoicePaymentId : null,
            'payment_type'       => $paymentType,
            'amount'             => $invoiceTotal,
            'card_brand'         => $paymentType === 'card' ? $cardBrand : null,
            'card_auth_code'     => $paymentType === 'card' ? $cardAuthCode : null,
            'card_last4'         => $paymentType === 'card' ? $cardLast4 : null,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

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
            'payment_id' => $invoicePaymentId,
            'points_earned' => $pointsEarned,
            'points_used' => $pointsUsed,
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

        return $this->db
            ->where('cart_id', $cart['id'])
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_cart_items')
            ->result_array();
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

    private function calculate_cart_totals($cartItems)
    {
        $subtotal = 0;

        foreach ($cartItems as $item) {
            $subtotal += (float) $item['line_total'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal, 2),
        ];
    }

    private function decrement_stock_for_sale($itemId, $qty, $transactionId)
    {
        $stock = $this->db
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
                'item_id' => $itemId,
                'qty_on_hand' => $newQty,
                'reorder_level' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->insert(db_prefix() . 'pos_inventory_ledger', [
            'item_id' => $itemId,
            'entry_type' => 'sale',
            'qty_change' => -$qty,
            'qty_after' => $newQty,
            'reference_type' => 'transaction',
            'reference_id' => $transactionId,
            'notes' => 'Auto decrement from POS sale',
            'created_by' => get_staff_user_id(),
            'created_at' => date('Y-m-d H:i:s'),
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
}
