<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">OmniPOS Smart Register</h4>
                        <p class="text-muted">Category grid, live stock badges, scanner mode, line modifiers, and split-tender checkout.</p>

                        <?php if ($current_shift) { ?>
                            <div class="alert alert-success">Open shift: <?php echo e($current_shift['register_key']); ?>, started <?php echo _dt($current_shift['opened_at']); ?></div>
                        <?php } else { ?>
                            <div class="alert alert-warning">No shift open. Open a shift to start scanning and sales.</div>
                        <?php } ?>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Client ID</label>
                                    <input type="number" id="omnipos-client-id" class="form-control" placeholder="Customer ID for invoice">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Points to Use</label>
                                    <input type="number" id="omnipos-points-use" class="form-control" min="0" value="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group position-relative">
                                    <label>Instant Search</label>
                                    <input type="text" id="omnipos-search" class="form-control" placeholder="Search by name, SKU, barcode, custom fields">
                                    <div id="omnipos-search-results" class="list-group omnipos-search-results hidden"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Wallet Barcode Lookup</label>
                                    <div class="input-group">
                                        <input type="text" id="omnipos-wallet-barcode" class="form-control" placeholder="Scan staff QR/barcode token">
                                        <span class="input-group-btn">
                                            <button class="btn btn-default" type="button" id="omnipos-wallet-lookup-btn">Lookup</button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="well well-sm tw-mt-2" id="omnipos-wallet-profile" style="display:none;">
                                    <strong id="omnipos-wallet-name"></strong>
                                    <span id="omnipos-wallet-employee" class="text-muted"></span>
                                    <div class="tw-mt-1">
                                        Available: <span id="omnipos-wallet-available">0.00</span> |
                                        Remaining Limit: <span id="omnipos-wallet-remaining">0.00</span> |
                                        Daily Left: <span id="omnipos-wallet-daily-left">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <ul class="nav nav-pills" id="omnipos-category-tabs">
                            <li class="active"><a href="#" data-group="all">All</a></li>
                            <?php foreach ($items_groups as $group) { ?>
                                <li><a href="#" data-group="<?php echo (int) $group['id']; ?>"><?php echo e($group['name']); ?></a></li>
                            <?php } ?>
                        </ul>

                        <div class="row tw-mt-3" id="omnipos-grid">
                            <?php foreach ($items as $item) {
                                $itemId = (int) $item['itemid'];
                                $stockQty = isset($stock_map[$itemId]) ? (float) $stock_map[$itemId] : 0;
                                $locked = $zero_stock_locked && $stockQty <= 0;
                            ?>
                                <div class="col-md-3 col-xs-6 tw-mb-2 omnipos-item-card" data-group="<?php echo (int) $item['group_id']; ?>" data-item-name="<?php echo e(mb_strtolower($item['description'])); ?>">
                                    <button class="btn btn-default btn-block omnipos-btn omnipos-add-item <?php echo $locked ? 'omnipos-stock-locked' : ''; ?>" data-item-id="<?php echo $itemId; ?>" <?php echo $locked ? 'disabled' : ''; ?>>
                                        <span class="omnipos-item-name"><?php echo e($item['description']); ?></span><br>
                                        <small><?php echo app_format_money((float) $item['rate'], get_base_currency()); ?></small>
                                        <span class="badge omnipos-stock-badge <?php echo $stockQty <= 0 ? 'badge-danger' : 'badge-success'; ?>">Stock: <?php echo number_format($stockQty, 2); ?></span>
                                    </button>
                                </div>
                            <?php } ?>
                        </div>

                        <hr>

                        <div class="table-responsive">
                            <table class="table table-striped" id="omnipos-cart-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-right">Qty</th>
                                        <th class="text-right">Unit</th>
                                        <th class="text-right">Discount</th>
                                        <th class="text-right">Tax</th>
                                        <th class="text-right">Line Total</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <label>Global Discount Type</label>
                                <select id="omnipos-global-discount-type" class="form-control">
                                    <option value="">None</option>
                                    <option value="fixed">Fixed</option>
                                    <option value="percent">Percent</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Global Discount Value</label>
                                <input type="number" id="omnipos-global-discount-value" class="form-control" min="0" step="0.01" value="0">
                            </div>
                            <div class="col-md-4">
                                <label>Service Charge</label>
                                <input type="number" id="omnipos-service-charge" class="form-control" min="0" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="tw-mt-2">
                            <button type="button" class="btn btn-default omnipos-btn" id="omnipos-apply-adjustments">Apply Cart Adjustments</button>
                        </div>

                        <div class="panel panel-default tw-mt-3">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-3"><strong>Subtotal:</strong> <span id="omnipos-total-subtotal">0.00</span></div>
                                    <div class="col-md-3"><strong>Line Discount:</strong> <span id="omnipos-total-line-discount">0.00</span></div>
                                    <div class="col-md-2"><strong>Tax:</strong> <span id="omnipos-total-tax">0.00</span></div>
                                    <div class="col-md-2"><strong>Global Disc:</strong> <span id="omnipos-total-global-discount">0.00</span></div>
                                    <div class="col-md-2"><strong>Svc:</strong> <span id="omnipos-total-service">0.00</span></div>
                                </div>
                                <h3 class="text-right tw-mt-2">Grand Total: <span id="omnipos-grand-total">0.00</span></h3>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <label>Suspend Name</label>
                                <input type="text" class="form-control" id="omnipos-suspend-label" placeholder="e.g. Hold Line 2">
                                <button type="button" class="btn btn-info btn-block omnipos-btn tw-mt-2" id="omnipos-suspend-btn">Park / Suspend Cart</button>
                            </div>
                            <div class="col-md-8">
                                <label>Cash Received</label>
                                <input type="number" class="form-control" id="omnipos-cash-received" min="0" step="0.01" value="0">
                                <div class="tw-mt-2">
                                    <button type="button" class="btn btn-default omnipos-denom" data-value="5">$5</button>
                                    <button type="button" class="btn btn-default omnipos-denom" data-value="10">$10</button>
                                    <button type="button" class="btn btn-default omnipos-denom" data-value="20">$20</button>
                                    <button type="button" class="btn btn-default omnipos-denom" data-value="50">$50</button>
                                    <button type="button" class="btn btn-default omnipos-denom" data-value="100">$100</button>
                                </div>
                                <h4 class="tw-mt-2">Change Due: <span id="omnipos-change-due">0.00</span></h4>
                            </div>
                        </div>

                        <div class="row tw-mt-3">
                            <div class="col-md-3">
                                <button type="button" class="btn btn-success btn-block omnipos-btn" id="omnipos-checkout-cash">Checkout Cash</button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-primary btn-block omnipos-btn" data-toggle="modal" data-target="#omniposCardModal" data-mode="card">Checkout Card</button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-warning btn-block omnipos-btn" data-toggle="modal" data-target="#omniposCardModal" data-mode="split">Split Tender</button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-info btn-block omnipos-btn" id="omnipos-checkout-wallet">Checkout Wallet</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Shift Controls</h5>
                        <div class="form-group">
                            <label>Register Key</label>
                            <input type="text" class="form-control" id="omnipos-register-key" value="<?php echo e(get_option('pos_default_register')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Opening Float</label>
                            <input type="number" class="form-control" id="omnipos-opening-float" value="0" step="0.01">
                        </div>
                        <button type="button" class="btn btn-default btn-block omnipos-btn" id="omnipos-open-shift">Open Shift</button>

                        <hr>

                        <div class="form-group">
                            <label>Blind Cash Count</label>
                            <input type="number" class="form-control" id="omnipos-counted-cash" value="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Blind Card Count</label>
                            <input type="number" class="form-control" id="omnipos-counted-card" value="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Terminal Card Total</label>
                            <input type="number" class="form-control" id="omnipos-terminal-total" value="0" step="0.01">
                        </div>
                        <button type="button" class="btn btn-danger btn-block omnipos-btn" id="omnipos-close-shift">Close Shift</button>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Active Cart Recall</h5>
                        <ul class="list-group">
                            <?php foreach ($suspended_carts as $s) { ?>
                                <li class="list-group-item">
                                    <a href="<?php echo admin_url('omnipos/pos/recall_cart/' . (int) $s['id']); ?>"><?php echo e($s['label']); ?></a>
                                    <span class="pull-right"><?php echo _dt($s['suspended_at']); ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="omniposWalletModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Wallet PIN Challenge</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">Enter customer 4-digit wallet PIN to authorize deduction.</p>
                <div class="form-group">
                    <label>Wallet PIN</label>
                    <input type="password" id="omnipos-wallet-pin" class="form-control" maxlength="4" placeholder="****">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="omnipos-confirm-wallet-checkout">Confirm Wallet Checkout</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="omniposLineModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Line Item Modifier</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="omnipos-line-id" value="">
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" id="omnipos-line-qty" class="form-control" min="0.01" step="0.01">
                </div>
                <div class="form-group">
                    <label>Discount Type</label>
                    <select id="omnipos-line-discount-type" class="form-control">
                        <option value="">None</option>
                        <option value="fixed">Fixed</option>
                        <option value="percent">Percent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Discount Value</label>
                    <input type="number" id="omnipos-line-discount-value" class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Tax Rate (%)</label>
                    <input type="number" id="omnipos-line-tax-rate" class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" id="omnipos-line-notes" class="form-control" maxlength="255">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="omnipos-save-line">Save Line</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="omniposCardModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Card / Split Payment Details</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="omnipos-checkout-mode" value="card">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Card Scheme</label>
                            <select id="omnipos-card-brand" class="form-control">
                                <option value="Visa">Visa</option>
                                <option value="Mastercard">Mastercard</option>
                                <option value="AMEX">AMEX</option>
                                <option value="Discover">Discover</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Terminal Auth Code</label>
                            <input type="text" id="omnipos-card-auth" class="form-control" placeholder="Required">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Last 4 Digits</label>
                    <input type="text" id="omnipos-card-last4" class="form-control" maxlength="4" placeholder="1234">
                </div>
                <div id="omnipos-split-fields" class="hidden">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Split Cash Amount</label>
                                <input type="number" id="omnipos-split-cash" class="form-control" min="0" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Split Card Amount</label>
                                <input type="number" id="omnipos-split-card" class="form-control" min="0" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="omnipos-confirm-checkout">Confirm Checkout</button>
            </div>
        </div>
    </div>
</div>

<style>
.omnipos-btn {
    min-height: 48px;
    font-weight: 600;
}
.omnipos-stock-badge {
    display: inline-block;
    margin-top: 6px;
}
.omnipos-stock-locked {
    opacity: 0.55;
}
.omnipos-search-results {
    position: absolute;
    z-index: 25;
    left: 15px;
    right: 15px;
    max-height: 260px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #ddd;
}
.omnipos-denom {
    margin-right: 4px;
    margin-bottom: 4px;
}
</style>

<script>
(function () {
    'use strict';

    var zeroStockLocked = <?php echo $zero_stock_locked ? 'true' : 'false'; ?>;
    var selectedWallet = null;
    var lastTotals = {
        grand_total: 0
    };

    function csrfPayload(payload) {
        if (typeof csrfData !== 'undefined') {
            payload[csrfData.token_name] = csrfData.hash;
        }
        return payload;
    }

    function showMessage(message, type) {
        alert_float(type || 'info', message || 'Done');
    }

    function money(v) {
        return parseFloat(v || 0).toFixed(2);
    }

    function renderTotals(totals) {
        totals = totals || {};
        lastTotals = totals;
        $('#omnipos-total-subtotal').text(money(totals.subtotal_before_adjustments));
        $('#omnipos-total-line-discount').text(money(totals.line_discount_total));
        $('#omnipos-total-tax').text(money(totals.tax_total));
        $('#omnipos-total-global-discount').text(money(totals.global_discount));
        $('#omnipos-total-service').text(money(totals.service_charge));
        $('#omnipos-grand-total').text(money(totals.grand_total));
        refreshChangeDue();
    }

    function renderCart(items, cart, totals) {
        var tbody = $('#omnipos-cart-table tbody');
        tbody.empty();

        $.each(items || [], function (_, row) {
            var tr = '<tr>' +
                '<td>' + (row.description || '') + '</td>' +
                '<td class="text-right">' + money(row.qty) + '</td>' +
                '<td class="text-right">' + money(row.unit_price) + '</td>' +
                '<td class="text-right">' + money(row.line_discount_amount) + '</td>' +
                '<td class="text-right">' + money(row.line_tax_amount) + '</td>' +
                '<td class="text-right">' + money(row.line_total) + '</td>' +
                '<td class="text-right"><button class="btn btn-xs btn-default omnipos-edit-line" ' +
                'data-line-id="' + (row.id || '') + '" ' +
                'data-line-qty="' + (row.qty || 0) + '" ' +
                'data-line-discount-type="' + (row.modifier_discount_type || '') + '" ' +
                'data-line-discount-value="' + (row.modifier_discount_value || 0) + '" ' +
                'data-line-tax-rate="' + (row.modifier_tax_rate || 0) + '" ' +
                'data-line-notes="' + String(row.modifier_notes || '').replace(/"/g, '&quot;') + '">Edit</button></td>' +
                '</tr>';
            tbody.append(tr);
        });

        if (cart) {
            $('#omnipos-global-discount-type').val(cart.global_discount_type || '');
            $('#omnipos-global-discount-value').val(cart.global_discount_value || 0);
            $('#omnipos-service-charge').val(cart.service_charge_value || 0);
        }

        renderTotals(totals || {});
    }

    function reloadCart() {
        $.get(admin_url + 'omnipos/pos/cart', function (res) {
            if (!res || !res.success) {
                return;
            }
            renderCart(res.data.items, res.data.cart, res.data.totals);
        }, 'json');
    }

    function addItem(itemId) {
        $.post(admin_url + 'omnipos/pos/add_item', csrfPayload({ item_id: itemId, qty: 1 }), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Add failed', 'warning');
                return;
            }
            renderCart(res.data.cart_items, res.data.cart, res.data.totals);
        }, 'json');
    }

    function refreshChangeDue() {
        var cashReceived = parseFloat($('#omnipos-cash-received').val() || '0');
        var change = Math.max(0, cashReceived - parseFloat(lastTotals.grand_total || 0));
        $('#omnipos-change-due').text(money(change));
    }

    function checkout(paymentType) {
        var payload = {
            client_id: parseInt($('#omnipos-client-id').val() || '0', 10),
            payment_type: paymentType,
            points_to_use: parseInt($('#omnipos-points-use').val() || '0', 10),
            cash_received: parseFloat($('#omnipos-cash-received').val() || '0')
        };

        if (paymentType === 'card' || paymentType === 'split') {
            payload.card_brand = $('#omnipos-card-brand').val();
            payload.card_auth_code = $('#omnipos-card-auth').val();
            payload.card_last4 = $('#omnipos-card-last4').val();
        }

        if (paymentType === 'split') {
            payload.split_cash_amount = parseFloat($('#omnipos-split-cash').val() || '0');
            payload.split_card_amount = parseFloat($('#omnipos-split-card').val() || '0');
        }

        if (paymentType === 'wallet') {
            if (!selectedWallet) {
                showMessage('Lookup a wallet staff profile first.', 'warning');
                return;
            }

            payload.wallet_staff_id = selectedWallet.id;
            payload.wallet_pin = $('#omnipos-wallet-pin').val();
        }

        $.post(admin_url + 'omnipos/pos/checkout', csrfPayload(payload), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Checkout failed', 'danger');
                return;
            }
            showMessage('Checkout complete. Invoice #' + res.data.invoice_id + ' | Change: ' + money(res.data.change_due), 'success');
            reloadCart();
            $('#omniposCardModal').modal('hide');
        }, 'json');
    }

    function applyWalletProfile(walletStaff, clientId) {
        selectedWallet = walletStaff;
        $('#omnipos-client-id').val(clientId || 0);
        $('#omnipos-wallet-profile').show();
        $('#omnipos-wallet-name').text(walletStaff.full_name || 'Staff Wallet');
        $('#omnipos-wallet-employee').text(' (' + (walletStaff.employee_code || '') + ')');
        $('#omnipos-wallet-available').text(money(walletStaff.available_to_spend));
        $('#omnipos-wallet-remaining').text(money(walletStaff.remaining_limit));

        var dailyLeft = walletStaff.daily_limit > 0 ? Math.max(0, parseFloat(walletStaff.daily_limit) - parseFloat(walletStaff.daily_spent || 0)) : walletStaff.available_to_spend;
        $('#omnipos-wallet-daily-left').text(money(dailyLeft));
    }

    function lookupWallet(barcode) {
        $.post(admin_url + 'omnipos/pos/wallet_lookup', csrfPayload({ barcode: barcode }), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Wallet lookup failed', 'warning');
                return;
            }

            applyWalletProfile(res.data.wallet_staff, res.data.client_id);
            showMessage('Wallet profile loaded: ' + (res.data.wallet_staff.full_name || ''), 'success');
        }, 'json');
    }

    var searchTimer = null;

    function renderSearchResults(items) {
        var box = $('#omnipos-search-results');
        box.empty();

        if (!items || !items.length) {
            box.addClass('hidden');
            return;
        }

        $.each(items, function (_, item) {
            var disabled = item.stock_locked ? ' disabled' : '';
            var rowClass = item.stock_locked ? ' list-group-item-danger' : '';
            var el = $('<a href="#" class="list-group-item' + rowClass + '"></a>');
            el.text(item.description + ' | Stock: ' + money(item.stock_qty));
            el.attr('data-item-id', item.itemid);
            if (disabled) {
                el.addClass('disabled');
            }
            box.append(el);
        });

        box.removeClass('hidden');
    }

    $('#omnipos-search').on('keyup', function () {
        var q = $(this).val();

        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        if (!q || q.length < 2) {
            $('#omnipos-search-results').addClass('hidden');
            return;
        }

        searchTimer = setTimeout(function () {
            $.get(admin_url + 'omnipos/pos/search_items', { q: q }, function (res) {
                if (!res || !res.success) {
                    return;
                }
                renderSearchResults(res.data.items || []);
            }, 'json');
        }, 180);
    });

    $(document).on('click', '#omnipos-search-results a', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) {
            showMessage('Item is out of stock.', 'warning');
            return;
        }
        addItem(parseInt($(this).data('item-id'), 10));
        $('#omnipos-search-results').addClass('hidden');
        $('#omnipos-search').val('');
    });

    $(document).on('click', '.omnipos-add-item', function () {
        if ($(this).is(':disabled')) {
            showMessage('Zero-stock lockout is active for this product.', 'warning');
            return;
        }
        addItem(parseInt($(this).data('item-id'), 10));
    });

    $('#omnipos-wallet-lookup-btn').on('click', function () {
        var token = $.trim($('#omnipos-wallet-barcode').val());
        if (!token) {
            showMessage('Enter or scan a wallet barcode token.', 'warning');
            return;
        }
        lookupWallet(token);
    });

    $('#omnipos-wallet-barcode').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#omnipos-wallet-lookup-btn').click();
        }
    });

    $('#omnipos-category-tabs a').on('click', function (e) {
        e.preventDefault();
        $('#omnipos-category-tabs li').removeClass('active');
        $(this).parent().addClass('active');

        var gid = $(this).data('group');
        $('.omnipos-item-card').each(function () {
            if (gid === 'all' || parseInt($(this).data('group'), 10) === parseInt(gid, 10)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#omnipos-apply-adjustments').on('click', function () {
        $.post(admin_url + 'omnipos/pos/update_cart_adjustments', csrfPayload({
            discount_type: $('#omnipos-global-discount-type').val(),
            discount_value: $('#omnipos-global-discount-value').val(),
            service_charge: $('#omnipos-service-charge').val()
        }), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Failed to update adjustments', 'warning');
                return;
            }
            renderCart(res.data.cart_items, res.data.cart, res.data.totals);
        }, 'json');
    });

    $(document).on('click', '.omnipos-edit-line', function () {
        $('#omnipos-line-id').val($(this).data('line-id'));
        $('#omnipos-line-qty').val($(this).data('line-qty'));
        $('#omnipos-line-discount-type').val($(this).data('line-discount-type') || '');
        $('#omnipos-line-discount-value').val($(this).data('line-discount-value') || 0);
        $('#omnipos-line-tax-rate').val($(this).data('line-tax-rate') || 0);
        $('#omnipos-line-notes').val($(this).data('line-notes') || '');
        $('#omniposLineModal').modal('show');
    });

    $('#omnipos-save-line').on('click', function () {
        $.post(admin_url + 'omnipos/pos/update_line_item', csrfPayload({
            line_id: $('#omnipos-line-id').val(),
            qty: $('#omnipos-line-qty').val(),
            discount_type: $('#omnipos-line-discount-type').val(),
            discount_value: $('#omnipos-line-discount-value').val(),
            tax_rate: $('#omnipos-line-tax-rate').val(),
            notes: $('#omnipos-line-notes').val()
        }), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Failed to update line', 'warning');
                return;
            }
            renderCart(res.data.cart_items, res.data.cart, res.data.totals);
            $('#omniposLineModal').modal('hide');
        }, 'json');
    });

    $('#omnipos-suspend-btn').on('click', function () {
        $.post(admin_url + 'omnipos/pos/suspend_cart', csrfPayload({ label: $('#omnipos-suspend-label').val() }), function (res) {
            showMessage(res.message, res.success ? 'success' : 'warning');
            if (res.success) {
                setTimeout(function () { window.location.reload(); }, 500);
            }
        }, 'json');
    });

    $('.omnipos-denom').on('click', function () {
        var v = parseFloat($(this).data('value'));
        $('#omnipos-cash-received').val(v.toFixed(2));
        refreshChangeDue();
    });

    $('#omnipos-cash-received').on('input', refreshChangeDue);

    $('#omnipos-checkout-cash').on('click', function () {
        checkout('cash');
    });

    $('#omnipos-checkout-wallet').on('click', function () {
        if (!selectedWallet) {
            showMessage('Lookup wallet staff profile first.', 'warning');
            return;
        }
        $('#omnipos-wallet-pin').val('');
        $('#omniposWalletModal').modal('show');
    });

    $('#omnipos-confirm-wallet-checkout').on('click', function () {
        checkout('wallet');
        $('#omniposWalletModal').modal('hide');
    });

    $('#omniposCardModal').on('show.bs.modal', function (event) {
        var trigger = $(event.relatedTarget);
        var mode = trigger.data('mode') || 'card';
        $('#omnipos-checkout-mode').val(mode);
        $('#omnipos-split-fields').toggleClass('hidden', mode !== 'split');

        if (mode === 'split') {
            var grand = parseFloat(lastTotals.grand_total || 0);
            $('#omnipos-split-cash').val((grand / 2).toFixed(2));
            $('#omnipos-split-card').val((grand - (grand / 2)).toFixed(2));
        }
    });

    $('#omnipos-confirm-checkout').on('click', function () {
        checkout($('#omnipos-checkout-mode').val());
    });

    $('#omnipos-open-shift').on('click', function () {
        $.post(admin_url + 'omnipos/pos/open_shift', csrfPayload({
            register_key: $('#omnipos-register-key').val(),
            opening_float: $('#omnipos-opening-float').val()
        }), function (res) {
            showMessage(res.message, res.success ? 'success' : 'warning');
            if (res.success) {
                setTimeout(function () { window.location.reload(); }, 500);
            }
        }, 'json');
    });

    $('#omnipos-close-shift').on('click', function () {
        $.post(admin_url + 'omnipos/pos/close_shift', csrfPayload({
            counted_cash: $('#omnipos-counted-cash').val(),
            counted_card: $('#omnipos-counted-card').val(),
            terminal_total: $('#omnipos-terminal-total').val()
        }), function (res) {
            showMessage(res.message, res.success ? 'success' : 'warning');
            if (res.success) {
                setTimeout(function () { window.location.reload(); }, 500);
            }
        }, 'json');
    });

    window.addEventListener('omnipos:scan', function (event) {
        if (!event.detail || !event.detail.response) {
            return;
        }

        var response = event.detail.response;
        if (!response.success) {
            showMessage(response.message || 'Scan failed', 'warning');
            return;
        }

        if (response.data && response.data.wallet_staff) {
            applyWalletProfile(response.data.wallet_staff, response.data.client_id);
            showMessage('Wallet profile scanned: ' + (response.data.wallet_staff.full_name || ''), 'success');
            return;
        }

        renderCart(response.data.cart_items, response.data.cart, response.data.totals);
    });

    reloadCart();
})();
</script>
<?php init_tail(); ?>
