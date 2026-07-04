<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Omnipos extends ClientsController
{
    public function __construct()
    {
        parent::__construct();

        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }

        $this->load->model('clients_model');
        $this->load->model('invoices_model');
    }

    public function wallet()
    {
        $wallet = $this->ensure_wallet_account();
        $clientId = (int) get_client_user_id();

        $this->reset_daily_spend_if_new_day($wallet['id']);

        $staffAccounts = $this->db
            ->where('wallet_account_id', $wallet['id'])
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'pos_wallet_staff_accounts')
            ->result_array();

        $ledger = $this->db
            ->where('wallet_account_id', $wallet['id'])
            ->order_by('id', 'DESC')
            ->limit(100)
            ->get(db_prefix() . 'pos_wallet_ledger')
            ->result_array();

        $allocated = 0;
        foreach ($staffAccounts as $row) {
            $allocated += (float) $row['remaining_limit'];
        }

        $data['title'] = 'Company Wallet';
        $data['wallet'] = $wallet;
        $data['staff_accounts'] = $staffAccounts;
        $data['ledger'] = $ledger;
        $data['master_allocated'] = $allocated;
        $data['master_available'] = (float) $wallet['balance'] - $allocated;
        $data['client_id'] = $clientId;

        $this->data($data);
        $this->view('wallet/index');
        $this->layout();
    }

    public function create_staff_account()
    {
        $wallet = $this->ensure_wallet_account();

        if (!$this->input->post()) {
            redirect(site_url('omnipos/wallet'));
        }

        $fullName = trim((string) $this->input->post('full_name', true));
        $jobTitle = trim((string) $this->input->post('job_title', true));
        $employeeCode = trim((string) $this->input->post('employee_code', true));
        $contactId = (int) $this->input->post('contact_id');
        $spendingLimit = max(0, (float) $this->input->post('spending_limit'));
        $dailyLimit = max(0, (float) $this->input->post('daily_limit'));
        $pin = preg_replace('/[^0-9]/', '', (string) $this->input->post('pin'));

        if ($fullName === '') {
            set_alert('warning', 'Staff full name is required.');
            redirect(site_url('omnipos/wallet'));
        }

        if ($employeeCode === '') {
            set_alert('warning', 'Employee ID is required.');
            redirect(site_url('omnipos/wallet'));
        }

        if (strlen($pin) !== 4) {
            set_alert('warning', 'PIN must be exactly 4 digits.');
            redirect(site_url('omnipos/wallet'));
        }

        $barcode = 'WAL-' . strtoupper(substr(md5($wallet['id'] . '|' . $employeeCode . '|' . time()), 0, 14));

        $this->db->insert(db_prefix() . 'pos_wallet_staff_accounts', [
            'wallet_account_id' => $wallet['id'],
            'contact_id' => $contactId,
            'full_name' => $fullName,
            'job_title' => $jobTitle,
            'employee_code' => $employeeCode,
            'barcode' => $barcode,
            'pin_hash' => password_hash($pin, PASSWORD_BCRYPT),
            'spending_limit' => $spendingLimit,
            'remaining_limit' => 0,
            'daily_limit' => $dailyLimit,
            'daily_spent' => 0,
            'daily_spent_date' => date('Y-m-d'),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        set_alert('success', 'Staff sub-account created.');
        redirect(site_url('omnipos/wallet'));
    }

    public function set_staff_pin($staffWalletId)
    {
        $wallet = $this->ensure_wallet_account();
        $staff = $this->get_staff_wallet_or_404((int) $staffWalletId, (int) $wallet['id']);

        $pin = preg_replace('/[^0-9]/', '', (string) $this->input->post('pin'));
        if (strlen($pin) !== 4) {
            set_alert('warning', 'PIN must be exactly 4 digits.');
            redirect(site_url('omnipos/wallet'));
        }

        $this->db->where('id', $staff['id']);
        $this->db->update(db_prefix() . 'pos_wallet_staff_accounts', [
            'pin_hash' => password_hash($pin, PASSWORD_BCRYPT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        set_alert('success', 'PIN updated successfully.');
        redirect(site_url('omnipos/wallet'));
    }

    public function toggle_staff_status($staffWalletId)
    {
        $wallet = $this->ensure_wallet_account();
        $staff = $this->get_staff_wallet_or_404((int) $staffWalletId, (int) $wallet['id']);

        $newStatus = $staff['status'] === 'active' ? 'frozen' : 'active';

        $this->db->where('id', $staff['id']);
        $this->db->update(db_prefix() . 'pos_wallet_staff_accounts', [
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        set_alert('success', 'Staff account status updated to ' . ucfirst($newStatus) . '.');
        redirect(site_url('omnipos/wallet'));
    }

    public function transfer_in($staffWalletId)
    {
        $wallet = $this->ensure_wallet_account();
        $staff = $this->get_staff_wallet_or_404((int) $staffWalletId, (int) $wallet['id']);

        $amount = max(0, (float) $this->input->post('amount'));
        if ($amount <= 0) {
            set_alert('warning', 'Transfer amount must be greater than zero.');
            redirect(site_url('omnipos/wallet'));
        }

        if ((float) $wallet['balance'] < $amount) {
            set_alert('warning', 'Insufficient master wallet balance.');
            redirect(site_url('omnipos/wallet'));
        }

        $newMasterBalance = (float) $wallet['balance'] - $amount;
        $newStaffRemaining = (float) $staff['remaining_limit'] + $amount;

        $this->db->where('id', $wallet['id']);
        $this->db->update(db_prefix() . 'pos_wallet_accounts', [
            'balance' => $newMasterBalance,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->where('id', $staff['id']);
        $this->db->update(db_prefix() . 'pos_wallet_staff_accounts', [
            'remaining_limit' => $newStaffRemaining,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->create_wallet_ledger((int) $wallet['id'], (int) $staff['id'], 'transfer_in', -$amount, 'master_wallet', $wallet['id'], 'Transfer to staff sub-account');
        $this->create_wallet_ledger((int) $wallet['id'], (int) $staff['id'], 'staff_fund_in', $amount, 'staff_wallet', $staff['id'], 'Fund deployment from master pool');

        set_alert('success', 'Funds transferred to staff account.');
        redirect(site_url('omnipos/wallet'));
    }

    public function transfer_out($staffWalletId)
    {
        $wallet = $this->ensure_wallet_account();
        $staff = $this->get_staff_wallet_or_404((int) $staffWalletId, (int) $wallet['id']);

        $amount = max(0, (float) $this->input->post('amount'));
        if ($amount <= 0) {
            set_alert('warning', 'Clawback amount must be greater than zero.');
            redirect(site_url('omnipos/wallet'));
        }

        if ((float) $staff['remaining_limit'] < $amount) {
            set_alert('warning', 'Staff account has insufficient remaining balance.');
            redirect(site_url('omnipos/wallet'));
        }

        $newMasterBalance = (float) $wallet['balance'] + $amount;
        $newStaffRemaining = (float) $staff['remaining_limit'] - $amount;

        $this->db->where('id', $wallet['id']);
        $this->db->update(db_prefix() . 'pos_wallet_accounts', [
            'balance' => $newMasterBalance,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->where('id', $staff['id']);
        $this->db->update(db_prefix() . 'pos_wallet_staff_accounts', [
            'remaining_limit' => $newStaffRemaining,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->create_wallet_ledger((int) $wallet['id'], (int) $staff['id'], 'staff_fund_out', -$amount, 'staff_wallet', $staff['id'], 'Fund clawback to master pool');
        $this->create_wallet_ledger((int) $wallet['id'], (int) $staff['id'], 'transfer_out', $amount, 'master_wallet', $wallet['id'], 'Transfer from staff sub-account');

        set_alert('success', 'Funds clawed back to master account.');
        redirect(site_url('omnipos/wallet'));
    }

    public function request_topup()
    {
        $wallet = $this->ensure_wallet_account();

        $amount = max(0, (float) $this->input->post('amount'));
        if ($amount <= 0) {
            set_alert('warning', 'Top-up amount must be greater than zero.');
            redirect(site_url('omnipos/wallet'));
        }

        $clientId = (int) get_client_user_id();
        $client = $this->clients_model->get($clientId);

        if (!$client) {
            set_alert('warning', 'Client account not found.');
            redirect(site_url('omnipos/wallet'));
        }

        $invoiceData = [
            'clientid' => $clientId,
            'number' => get_option('next_invoice_number'),
            'date' => date('Y-m-d'),
            'duedate' => date('Y-m-d'),
            'currency' => isset($client->default_currency) ? (int) $client->default_currency : 0,
            'billing_street' => isset($client->billing_street) ? (string) $client->billing_street : '',
            'billing_city' => isset($client->billing_city) ? (string) $client->billing_city : '',
            'billing_state' => isset($client->billing_state) ? (string) $client->billing_state : '',
            'billing_zip' => isset($client->billing_zip) ? (string) $client->billing_zip : '',
            'billing_country' => isset($client->billing_country) ? (int) $client->billing_country : 0,
            'shipping_street' => isset($client->shipping_street) ? (string) $client->shipping_street : '',
            'shipping_city' => isset($client->shipping_city) ? (string) $client->shipping_city : '',
            'shipping_state' => isset($client->shipping_state) ? (string) $client->shipping_state : '',
            'shipping_zip' => isset($client->shipping_zip) ? (string) $client->shipping_zip : '',
            'shipping_country' => isset($client->shipping_country) ? (int) $client->shipping_country : 0,
            'newitems' => [
                1 => [
                    'description' => 'Wallet Top-up Request',
                    'long_description' => 'Top-up for corporate master wallet account',
                    'qty' => 1,
                    'rate' => $amount,
                    'unit' => '',
                ],
            ],
        ];

        $invoiceId = $this->invoices_model->add($invoiceData);

        if (!$invoiceId) {
            set_alert('warning', 'Unable to create top-up invoice at this time.');
            redirect(site_url('omnipos/wallet'));
        }

        $this->db->insert(db_prefix() . 'pos_wallet_topups', [
            'wallet_account_id' => $wallet['id'],
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->create_wallet_ledger((int) $wallet['id'], null, 'topup_request', 0, 'invoice', $invoiceId, 'Top-up invoice generated for ' . number_format($amount, 2));

        set_alert('success', 'Top-up request created. Invoice #' . $invoiceId . ' generated.');
        redirect(site_url('clients/invoice/' . $invoiceId . '/' . $this->invoices_model->get($invoiceId)->hash));
    }

    public function staff_code($staffWalletId)
    {
        $wallet = $this->ensure_wallet_account();
        $staff = $this->get_staff_wallet_or_404((int) $staffWalletId, (int) $wallet['id']);

        $payload = json_encode([
            'wallet_staff_id' => (int) $staff['id'],
            'barcode' => $staff['barcode'],
            'employee_code' => $staff['employee_code'],
        ]);

        $qrUrl = 'https://chart.googleapis.com/chart?chs=260x260&cht=qr&chl=' . rawurlencode($payload);

        $data['title'] = 'Wallet Staff QR';
        $data['staff'] = $staff;
        $data['qr_url'] = $qrUrl;

        $this->data($data);
        $this->view('wallet/staff_code');
        $this->layout();
    }

    public function ledger_export()
    {
        $wallet = $this->ensure_wallet_account();

        $rows = $this->db
            ->where('wallet_account_id', $wallet['id'])
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'pos_wallet_ledger')
            ->result_array();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="wallet-ledger-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Type', 'Amount', 'Reference Type', 'Reference ID', 'Notes']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['created_at'],
                $row['entry_type'],
                $row['amount'],
                $row['reference_type'],
                $row['reference_id'],
                $row['notes'],
            ]);
        }

        fclose($out);
        exit;
    }

    private function ensure_wallet_account()
    {
        $clientId = (int) get_client_user_id();

        $wallet = $this->db
            ->where('client_id', $clientId)
            ->get(db_prefix() . 'pos_wallet_accounts')
            ->row_array();

        if ($wallet) {
            return $wallet;
        }

        $this->db->insert(db_prefix() . 'pos_wallet_accounts', [
            'client_id' => $clientId,
            'balance' => 0,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db
            ->where('id', (int) $this->db->insert_id())
            ->get(db_prefix() . 'pos_wallet_accounts')
            ->row_array();
    }

    private function get_staff_wallet_or_404($staffWalletId, $walletAccountId)
    {
        $staff = $this->db
            ->where('id', $staffWalletId)
            ->where('wallet_account_id', $walletAccountId)
            ->get(db_prefix() . 'pos_wallet_staff_accounts')
            ->row_array();

        if (!$staff) {
            show_404();
        }

        return $staff;
    }

    private function create_wallet_ledger($walletId, $staffWalletId, $entryType, $amount, $refType, $refId, $notes)
    {
        $this->db->insert(db_prefix() . 'pos_wallet_ledger', [
            'wallet_account_id' => $walletId,
            'staff_wallet_id' => $staffWalletId,
            'entry_type' => $entryType,
            'amount' => $amount,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'notes' => $notes,
            'created_by' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function reset_daily_spend_if_new_day($walletAccountId)
    {
        $today = date('Y-m-d');

        $rows = $this->db
            ->where('wallet_account_id', $walletAccountId)
            ->get(db_prefix() . 'pos_wallet_staff_accounts')
            ->result_array();

        foreach ($rows as $row) {
            if ($row['daily_spent_date'] !== $today) {
                $this->db->where('id', $row['id']);
                $this->db->update(db_prefix() . 'pos_wallet_staff_accounts', [
                    'daily_spent' => 0,
                    'daily_spent_date' => $today,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
