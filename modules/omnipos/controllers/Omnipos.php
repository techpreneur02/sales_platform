<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Omnipos extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function wallet()
    {
        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }

        $clientId = get_client_user_id();

        $wallet = $this->db
            ->where('client_id', $clientId)
            ->get(db_prefix() . 'pos_wallet_accounts')
            ->row_array();

        if (!$wallet) {
            $this->db->insert(db_prefix() . 'pos_wallet_accounts', [
                'client_id' => $clientId,
                'balance' => 0,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $wallet = $this->db
                ->where('id', $this->db->insert_id())
                ->get(db_prefix() . 'pos_wallet_accounts')
                ->row_array();
        }

        $staffAccounts = $this->db
            ->where('wallet_account_id', $wallet['id'])
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'pos_wallet_staff_accounts')
            ->result_array();

        $ledger = $this->db
            ->where('wallet_account_id', $wallet['id'])
            ->order_by('id', 'DESC')
            ->limit(50)
            ->get(db_prefix() . 'pos_wallet_ledger')
            ->result_array();

        $data['title'] = 'Company Wallet';
        $data['wallet'] = $wallet;
        $data['staff_accounts'] = $staffAccounts;
        $data['ledger'] = $ledger;

        $this->data($data);
        $this->view('wallet/index');
        $this->layout();
    }
}
