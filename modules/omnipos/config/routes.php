<?php

defined('BASEPATH') or exit('No direct script access allowed');

$route['omnipos/wallet'] = 'omnipos/wallet';
$route['omnipos/wallet/create-staff'] = 'omnipos/create_staff_account';
$route['omnipos/wallet/request-topup'] = 'omnipos/request_topup';
$route['omnipos/wallet/transfer-in/(:num)'] = 'omnipos/transfer_in/$1';
$route['omnipos/wallet/transfer-out/(:num)'] = 'omnipos/transfer_out/$1';
$route['omnipos/wallet/pin/(:num)'] = 'omnipos/set_staff_pin/$1';
$route['omnipos/wallet/toggle/(:num)'] = 'omnipos/toggle_staff_status/$1';
$route['omnipos/wallet/code/(:num)'] = 'omnipos/staff_code/$1';
$route['omnipos/wallet/export-ledger'] = 'omnipos/ledger_export';

$route['omnipos/inventory'] = 'inventory/index';
$route['omnipos/inventory/export-stock'] = 'inventory/export_stock_csv';
$route['omnipos/inventory/import-template'] = 'inventory/download_import_template';
$route['omnipos/inventory/import-stock'] = 'inventory/import_stock_csv';

$route['omnipos/settings'] = 'settings/index';
$route['omnipos/settings/save-general'] = 'settings/save_general';
$route['omnipos/settings/save-dynamic'] = 'settings/save_dynamic_values';
