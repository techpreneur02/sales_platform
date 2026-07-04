<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: OmniPOS
Description: Tablet-first POS, wallet, loyalty, inventory and shift controls for Perfex CRM.
Version: 1.0.0
Requires at least: 3.0.*
*/

define('OMNIPOS_MODULE_NAME', 'omnipos');

hooks()->add_filter('module_' . OMNIPOS_MODULE_NAME . '_action_links', 'module_omnipos_action_links');
hooks()->add_action('admin_init', 'omnipos_admin_init_menu');
hooks()->add_action('admin_init', 'omnipos_upgrade_schema');
hooks()->add_action('app_admin_footer', 'omnipos_inject_scanner_asset');
hooks()->add_action('clients_init', 'omnipos_add_clients_menu_item');
hooks()->add_filter('get_client_portal_menu_items', 'omnipos_add_client_portal_menu_item');

register_activation_hook(OMNIPOS_MODULE_NAME, 'omnipos_activation_hook');

function module_omnipos_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('omnipos/pos') . '">POS Terminal</a>';

    return $actions;
}

function omnipos_admin_init_menu()
{
    $CI = &get_instance();

    if (!is_staff_logged_in()) {
        return;
    }

    $CI->app_menu->add_sidebar_menu_item('omnipos', [
        'name'     => 'OmniPOS',
        'href'     => admin_url('omnipos/pos'),
        'icon'     => 'fa-solid fa-store',
        'position' => 32,
    ]);

    $CI->app_menu->add_sidebar_children_item('omnipos', [
        'slug'     => 'omnipos-terminal',
        'name'     => 'POS Terminal',
        'href'     => admin_url('omnipos/pos'),
        'position' => 5,
    ]);

    $CI->app_menu->add_sidebar_children_item('omnipos', [
        'slug'     => 'omnipos-shifts',
        'name'     => 'Shift Control',
        'href'     => admin_url('omnipos/pos/shifts'),
        'position' => 10,
    ]);

    $CI->app_menu->add_sidebar_children_item('omnipos', [
        'slug'     => 'omnipos-inventory',
        'name'     => 'Inventory',
        'href'     => admin_url('omnipos/inventory'),
        'position' => 15,
    ]);
}

function omnipos_inject_scanner_asset()
{
    if (!is_staff_logged_in()) {
        return;
    }

    $uri = uri_string();

    if (strpos($uri, 'admin/omnipos/pos') === false) {
        return;
    }

    echo '<script src="' . module_dir_url(OMNIPOS_MODULE_NAME, 'assets/js/scanner.js') . '"></script>';
}

function omnipos_add_clients_menu_item()
{
    if (!function_exists('add_theme_menu_item') || !is_client_logged_in()) {
        return;
    }

    add_theme_menu_item('omnipos-wallet', [
        'name'     => 'Company Wallet',
        'href'     => site_url('omnipos/wallet'),
        'position' => 35,
    ]);
}

function omnipos_add_client_portal_menu_item($items)
{
    if (!is_array($items)) {
        return $items;
    }

    $items['omnipos_wallet'] = [
        'name'     => 'Company Wallet',
        'href'     => site_url('omnipos/wallet'),
        'icon'     => 'fa-solid fa-wallet',
        'position' => 35,
    ];

    return $items;
}

function omnipos_activation_hook()
{
    $CI = &get_instance();

    omnipos_create_default_options();

    $charset = 'utf8mb4';
    $collate = 'utf8mb4_unicode_ci';

    $tables = [
        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_shifts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `staff_id` INT UNSIGNED NOT NULL,
            `register_key` VARCHAR(100) NOT NULL,
            `warehouse_id` BIGINT UNSIGNED NULL,
            `opening_float` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `opened_at` DATETIME NOT NULL,
            `closed_at` DATETIME NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'open',
            `closing_expected_cash` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `closing_counted_cash` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `closing_expected_card` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `closing_counted_card` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `cash_variance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `card_variance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `notes` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_staff_status` (`staff_id`, `status`),
            KEY `idx_register_status` (`register_key`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_warehouses` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(191) NOT NULL,
            `code` VARCHAR(50) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_purchase_orders` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `warehouse_id` BIGINT UNSIGNED NOT NULL,
            `supplier_name` VARCHAR(191) NOT NULL,
            `reference_no` VARCHAR(100) NOT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
            `expected_at` DATE NULL,
            `received_at` DATETIME NULL,
            `notes` TEXT NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_warehouse` (`warehouse_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_purchase_order_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `purchase_order_id` BIGINT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `expected_qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `received_qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `unit_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `idx_po` (`purchase_order_id`),
            KEY `idx_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_shift_blind_counts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `shift_id` BIGINT UNSIGNED NOT NULL,
            `counted_cash` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `counted_card` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `terminal_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `notes` TEXT NULL,
            `counted_by` INT UNSIGNED NOT NULL,
            `counted_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_shift` (`shift_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_carts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `staff_id` INT UNSIGNED NOT NULL,
            `shift_id` BIGINT UNSIGNED NULL,
            `client_id` INT UNSIGNED NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_staff_status` (`staff_id`, `status`),
            KEY `idx_shift` (`shift_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_cart_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cart_id` BIGINT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `long_description` TEXT NULL,
            `qty` DECIMAL(15,2) NOT NULL DEFAULT 1.00,
            `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_cart_item` (`cart_id`, `item_id`),
            KEY `idx_cart` (`cart_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_suspended_carts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `cart_id` BIGINT UNSIGNED NOT NULL,
            `staff_id` INT UNSIGNED NOT NULL,
            `label` VARCHAR(120) NULL,
            `suspended_at` DATETIME NOT NULL,
            `recalled_at` DATETIME NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'suspended',
            PRIMARY KEY (`id`),
            KEY `idx_staff_status` (`staff_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_suspended_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `suspended_id` BIGINT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `long_description` TEXT NULL,
            `qty` DECIMAL(15,2) NOT NULL DEFAULT 1.00,
            `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `idx_suspended` (`suspended_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_transactions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `shift_id` BIGINT UNSIGNED NOT NULL,
            `cart_id` BIGINT UNSIGNED NOT NULL,
            `invoice_id` INT UNSIGNED NOT NULL,
            `staff_id` INT UNSIGNED NOT NULL,
            `client_id` INT UNSIGNED NOT NULL,
            `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `discount_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `payment_type` VARCHAR(20) NOT NULL,
            `card_brand` VARCHAR(40) NULL,
            `card_auth_code` VARCHAR(40) NULL,
            `card_last4` VARCHAR(4) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_shift` (`shift_id`),
            KEY `idx_invoice` (`invoice_id`),
            KEY `idx_client` (`client_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_transaction_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `transaction_id` BIGINT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `qty` DECIMAL(15,2) NOT NULL DEFAULT 1.00,
            `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `idx_transaction` (`transaction_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_refunds` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `original_transaction_id` BIGINT UNSIGNED NOT NULL,
            `refund_transaction_id` BIGINT UNSIGNED NULL,
            `shift_id` BIGINT UNSIGNED NOT NULL,
            `staff_id` INT UNSIGNED NOT NULL,
            `refund_type` VARCHAR(20) NOT NULL DEFAULT 'cash',
            `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `reason` VARCHAR(255) NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'completed',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_original_transaction` (`original_transaction_id`),
            KEY `idx_refund_transaction` (`refund_transaction_id`),
            KEY `idx_shift` (`shift_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_refund_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `refund_id` BIGINT UNSIGNED NOT NULL,
            `transaction_item_id` BIGINT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `idx_refund` (`refund_id`),
            KEY `idx_transaction_item` (`transaction_item_id`),
            KEY `idx_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_payment_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `transaction_id` BIGINT UNSIGNED NOT NULL,
            `invoice_payment_id` INT UNSIGNED NULL,
            `payment_type` VARCHAR(20) NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `card_brand` VARCHAR(40) NULL,
            `card_auth_code` VARCHAR(40) NULL,
            `card_last4` VARCHAR(4) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_transaction` (`transaction_id`),
            KEY `idx_payment_type` (`payment_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_wallet_accounts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `client_id` INT UNSIGNED NOT NULL,
            `balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_client` (`client_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_wallet_staff_accounts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `wallet_account_id` BIGINT UNSIGNED NOT NULL,
            `contact_id` INT UNSIGNED NOT NULL,
            `barcode` VARCHAR(120) NOT NULL,
            `pin_hash` VARCHAR(255) NULL,
            `spending_limit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `remaining_limit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_barcode` (`barcode`),
            KEY `idx_wallet` (`wallet_account_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_wallet_ledger` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `wallet_account_id` BIGINT UNSIGNED NOT NULL,
            `staff_wallet_id` BIGINT UNSIGNED NULL,
            `entry_type` VARCHAR(30) NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `reference_type` VARCHAR(30) NULL,
            `reference_id` BIGINT UNSIGNED NULL,
            `notes` TEXT NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_wallet` (`wallet_account_id`),
            KEY `idx_ref` (`reference_type`, `reference_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_wallet_topups` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `wallet_account_id` BIGINT UNSIGNED NOT NULL,
            `invoice_id` INT UNSIGNED NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_wallet` (`wallet_account_id`),
            KEY `idx_invoice` (`invoice_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_loyalty_balances` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `client_id` INT UNSIGNED NOT NULL,
            `points_balance` BIGINT NOT NULL DEFAULT 0,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_client` (`client_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_loyalty_ledger` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `client_id` INT UNSIGNED NOT NULL,
            `transaction_id` BIGINT UNSIGNED NULL,
            `entry_type` VARCHAR(20) NOT NULL,
            `points` BIGINT NOT NULL DEFAULT 0,
            `notes` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_client` (`client_id`),
            KEY `idx_transaction` (`transaction_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_reward_rules` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(120) NOT NULL,
            `earn_rate` DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
            `point_value` DECIMAL(12,4) NOT NULL DEFAULT 0.0100,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_storeroom_stock` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
            `item_id` INT UNSIGNED NOT NULL,
            `qty_on_hand` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `reorder_level` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_warehouse_item` (`warehouse_id`, `item_id`),
            KEY `idx_warehouse` (`warehouse_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};",

        "CREATE TABLE IF NOT EXISTS `" . db_prefix() . "pos_inventory_ledger` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
            `to_warehouse_id` BIGINT UNSIGNED NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `entry_type` VARCHAR(30) NOT NULL,
            `qty_change` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `qty_after` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `reason_code` VARCHAR(50) NULL,
            `reference_type` VARCHAR(30) NULL,
            `reference_id` BIGINT UNSIGNED NULL,
            `notes` TEXT NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_warehouse` (`warehouse_id`),
            KEY `idx_item` (`item_id`),
            KEY `idx_ref` (`reference_type`, `reference_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};"
    ];

    foreach ($tables as $tableSql) {
        $CI->db->query($tableSql);
    }

    $ruleExists = $CI->db->where('is_active', 1)->count_all_results(db_prefix() . 'pos_reward_rules') > 0;
    if (!$ruleExists) {
        $CI->db->insert(db_prefix() . 'pos_reward_rules', [
            'name'       => 'Default loyalty rule',
            'earn_rate'  => 1,
            'point_value'=> 0.01,
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    $warehouseExists = $CI->db->where('code', 'MAIN')->count_all_results(db_prefix() . 'pos_warehouses') > 0;
    if (!$warehouseExists) {
        $CI->db->insert(db_prefix() . 'pos_warehouses', [
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

function omnipos_create_default_options()
{
    if (get_option('pos_loyalty_earn_rate') === '') {
        add_option('pos_loyalty_earn_rate', '1');
    }

    if (get_option('pos_loyalty_point_value') === '') {
        add_option('pos_loyalty_point_value', '0.01');
    }

    if (get_option('pos_default_register') === '') {
        add_option('pos_default_register', 'main-register');
    }

    if (get_option('pos_allow_zero_stock_sales') === '') {
        add_option('pos_allow_zero_stock_sales', '0');
    }

    if (get_option('pos_default_tax_rate') === '') {
        add_option('pos_default_tax_rate', '0');
    }

    if (get_option('pos_wallet_require_pin') === '') {
        add_option('pos_wallet_require_pin', '1');
    }

    if (get_option('pos_default_warehouse_id') === '') {
        add_option('pos_default_warehouse_id', '1');
    }
}

function omnipos_upgrade_schema()
{
    if (!is_admin()) {
        return;
    }

    $CI = &get_instance();
    $prefix = db_prefix();

    if (!$CI->db->field_exists('group_id', $prefix . 'pos_storeroom_stock')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_storeroom_stock` ADD `group_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `item_id`');
    }

    if (!$CI->db->field_exists('warehouse_id', $prefix . 'pos_shifts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_shifts` ADD `warehouse_id` BIGINT UNSIGNED NULL AFTER `register_key`');
    }

    if ($CI->db->table_exists($prefix . 'pos_storeroom_stock') && !$CI->db->field_exists('warehouse_id', $prefix . 'pos_storeroom_stock')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_storeroom_stock` ADD `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`');
    }

    if ($CI->db->table_exists($prefix . 'pos_inventory_ledger') && !$CI->db->field_exists('warehouse_id', $prefix . 'pos_inventory_ledger')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_inventory_ledger` ADD `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`');
    }

    if ($CI->db->table_exists($prefix . 'pos_inventory_ledger') && !$CI->db->field_exists('to_warehouse_id', $prefix . 'pos_inventory_ledger')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_inventory_ledger` ADD `to_warehouse_id` BIGINT UNSIGNED NULL AFTER `warehouse_id`');
    }

    if ($CI->db->table_exists($prefix . 'pos_inventory_ledger') && !$CI->db->field_exists('reason_code', $prefix . 'pos_inventory_ledger')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_inventory_ledger` ADD `reason_code` VARCHAR(50) NULL AFTER `qty_after`');
    }

    if ($CI->db->table_exists($prefix . 'pos_storeroom_stock')) {
        $idxRows = $CI->db->query('SHOW INDEX FROM `' . $prefix . 'pos_storeroom_stock` WHERE Key_name = "uniq_item"')->result_array();
        if (!empty($idxRows)) {
            $CI->db->query('ALTER TABLE `' . $prefix . 'pos_storeroom_stock` DROP INDEX `uniq_item`');
        }

        $idxRows2 = $CI->db->query('SHOW INDEX FROM `' . $prefix . 'pos_storeroom_stock` WHERE Key_name = "uniq_warehouse_item"')->result_array();
        if (empty($idxRows2)) {
            $CI->db->query('ALTER TABLE `' . $prefix . 'pos_storeroom_stock` ADD UNIQUE KEY `uniq_warehouse_item` (`warehouse_id`, `item_id`)');
        }
    }

    if (!$CI->db->table_exists($prefix . 'pos_warehouses')) {
        $CI->db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'pos_warehouses` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(191) NOT NULL,
            `code` VARCHAR(50) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    if (!$CI->db->table_exists($prefix . 'pos_purchase_orders')) {
        $CI->db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'pos_purchase_orders` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `warehouse_id` BIGINT UNSIGNED NOT NULL,
            `supplier_name` VARCHAR(191) NOT NULL,
            `reference_no` VARCHAR(100) NOT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT "draft",
            `expected_at` DATE NULL,
            `received_at` DATETIME NULL,
            `notes` TEXT NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_warehouse` (`warehouse_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    if (!$CI->db->table_exists($prefix . 'pos_purchase_order_items')) {
        $CI->db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'pos_purchase_order_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `purchase_order_id` BIGINT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `expected_qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `received_qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `unit_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `idx_po` (`purchase_order_id`),
            KEY `idx_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    if (!$CI->db->table_exists($prefix . 'pos_refunds')) {
        $CI->db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'pos_refunds` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `original_transaction_id` BIGINT UNSIGNED NOT NULL,
            `refund_transaction_id` BIGINT UNSIGNED NULL,
            `shift_id` BIGINT UNSIGNED NOT NULL,
            `staff_id` INT UNSIGNED NOT NULL,
            `refund_type` VARCHAR(20) NOT NULL DEFAULT "cash",
            `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `reason` VARCHAR(255) NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT "completed",
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_original_transaction` (`original_transaction_id`),
            KEY `idx_refund_transaction` (`refund_transaction_id`),
            KEY `idx_shift` (`shift_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    if (!$CI->db->table_exists($prefix . 'pos_refund_items')) {
        $CI->db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'pos_refund_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `refund_id` BIGINT UNSIGNED NOT NULL,
            `transaction_item_id` BIGINT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `idx_refund` (`refund_id`),
            KEY `idx_transaction_item` (`transaction_item_id`),
            KEY `idx_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    if (!$CI->db->field_exists('global_discount_type', $prefix . 'pos_carts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_carts` ADD `global_discount_type` VARCHAR(20) NULL AFTER `client_id`');
    }

    if (!$CI->db->field_exists('global_discount_value', $prefix . 'pos_carts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_carts` ADD `global_discount_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `global_discount_type`');
    }

    if (!$CI->db->field_exists('service_charge_value', $prefix . 'pos_carts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_carts` ADD `service_charge_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `global_discount_value`');
    }

    if (!$CI->db->field_exists('modifier_discount_type', $prefix . 'pos_cart_items')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_cart_items` ADD `modifier_discount_type` VARCHAR(20) NULL AFTER `line_total`');
    }

    if (!$CI->db->field_exists('modifier_discount_value', $prefix . 'pos_cart_items')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_cart_items` ADD `modifier_discount_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `modifier_discount_type`');
    }

    if (!$CI->db->field_exists('modifier_tax_rate', $prefix . 'pos_cart_items')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_cart_items` ADD `modifier_tax_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000 AFTER `modifier_discount_value`');
    }

    if (!$CI->db->field_exists('modifier_notes', $prefix . 'pos_cart_items')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_cart_items` ADD `modifier_notes` VARCHAR(255) NULL AFTER `modifier_tax_rate`');
    }

    if (!$CI->db->field_exists('full_name', $prefix . 'pos_wallet_staff_accounts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_wallet_staff_accounts` ADD `full_name` VARCHAR(191) NULL AFTER `contact_id`');
    }

    if (!$CI->db->field_exists('job_title', $prefix . 'pos_wallet_staff_accounts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_wallet_staff_accounts` ADD `job_title` VARCHAR(191) NULL AFTER `full_name`');
    }

    if (!$CI->db->field_exists('employee_code', $prefix . 'pos_wallet_staff_accounts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_wallet_staff_accounts` ADD `employee_code` VARCHAR(100) NULL AFTER `job_title`');
    }

    if (!$CI->db->field_exists('daily_limit', $prefix . 'pos_wallet_staff_accounts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_wallet_staff_accounts` ADD `daily_limit` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `remaining_limit`');
    }

    if (!$CI->db->field_exists('daily_spent', $prefix . 'pos_wallet_staff_accounts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_wallet_staff_accounts` ADD `daily_spent` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `daily_limit`');
    }

    if (!$CI->db->field_exists('daily_spent_date', $prefix . 'pos_wallet_staff_accounts')) {
        $CI->db->query('ALTER TABLE `' . $prefix . 'pos_wallet_staff_accounts` ADD `daily_spent_date` DATE NULL AFTER `daily_spent`');
    }

    $warehouseExists = $CI->db->where('code', 'MAIN')->count_all_results($prefix . 'pos_warehouses') > 0;
    if (!$warehouseExists) {
        $CI->db->insert($prefix . 'pos_warehouses', [
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
