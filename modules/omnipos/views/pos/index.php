<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="omnipos-topbar">
                            <div>
                                <h4 class="no-margin">OmniPOS Smart Register</h4>
                                <div class="text-muted">Clean cashier workflow with progressive disclosure and fast checkout.</div>
                            </div>
                            <div class="omnipos-topbar-actions">
                                <button type="button" class="btn btn-default" data-toggle="collapse" data-target="#omnipos-customer-panel" aria-expanded="false" aria-controls="omnipos-customer-panel">Attach Customer</button>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Actions <span class="caret"></span></button>
                                    <ul class="dropdown-menu dropdown-menu-right">
                                        <li><a href="<?php echo admin_url('omnipos/pos/shifts'); ?>">Shift & Returns</a></li>
                                        <li><a href="<?php echo admin_url('omnipos/inventory'); ?>">Inventory</a></li>
                                        <li><a href="<?php echo admin_url('omnipos/settings'); ?>">Settings</a></li>
                                    </ul>
                                </div>
                                <button type="button" class="btn btn-primary" id="omnipos-toggle-fullscreen">Full Screen</button>
                            </div>
                        </div>

                        <?php if ($current_shift) { ?>
                            <div class="alert alert-success omnipos-status-alert">Open shift: <?php echo e($current_shift['register_key']); ?>, started <?php echo _dt($current_shift['opened_at']); ?></div>
                        <?php } else { ?>
                            <div class="alert alert-warning omnipos-status-alert">No shift open. Open a shift from Shift & Returns tab to begin sales.</div>
                        <?php } ?>

                        <div id="omnipos-customer-panel" class="collapse">
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
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="well well-sm" id="omnipos-wallet-profile" style="display:none;">
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
                        </div>

                        <div class="row omnipos-control-row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Add Qty</label>
                                    <input type="number" id="omnipos-add-qty" class="form-control" min="1" step="1" value="1">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group position-relative">
                                    <label>Instant Search</label>
                                    <input type="text" id="omnipos-search" class="form-control" placeholder="Search by name, SKU, barcode, custom fields">
                                    <div id="omnipos-search-results" class="list-group omnipos-search-results hidden"></div>
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
                                $itemId = isset($item['itemid']) ? (int) $item['itemid'] : (isset($item['id']) ? (int) $item['id'] : 0);
                                $groupId = isset($item['group_id']) ? (int) $item['group_id'] : (isset($item['groupid']) ? (int) $item['groupid'] : 0);
                                $stockQty = isset($stock_map[$itemId]) ? (float) $stock_map[$itemId] : 0;
                                $locked = $zero_stock_locked && $stockQty <= 0;
                                $itemNameFilter = function_exists('mb_strtolower') ? mb_strtolower((string) $item['description']) : strtolower((string) $item['description']);
                            ?>
                                <div class="col-md-3 col-xs-6 omnipos-item-card" data-group="<?php echo $groupId; ?>" data-item-name="<?php echo e($itemNameFilter); ?>">
                                    <button class="btn btn-default btn-block omnipos-btn omnipos-add-item <?php echo $locked ? 'omnipos-stock-locked' : ''; ?>" data-item-id="<?php echo $itemId; ?>" <?php echo $locked ? 'disabled' : ''; ?>>
                                        <span class="omnipos-item-name"><?php echo e($item['description']); ?></span><br>
                                        <small><?php echo app_format_money((float) $item['rate'], get_base_currency()); ?></small>
                                        <span class="badge omnipos-stock-badge <?php echo $stockQty <= 0 ? 'badge-danger' : 'badge-success'; ?>">Stock: <?php echo number_format($stockQty, 2); ?></span>
                                    </button>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="omnipos-grid-pagination">
                            <button type="button" class="btn btn-default btn-sm" id="omnipos-page-prev">Prev</button>
                            <span id="omnipos-page-indicator">Page 1 / 1</span>
                            <button type="button" class="btn btn-default btn-sm" id="omnipos-page-next">Next</button>
                        </div>

                        <hr>

                        <div class="table-responsive">
                            <table class="table table-striped omnipos-cart-table" id="omnipos-cart-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-right">Each</th>
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
                            <div class="col-md-12 tw-mb-2">
                                <button type="button" class="btn btn-success btn-lg btn-block omnipos-pay-primary" id="omnipos-pay-primary">PAY NOW (Cash)</button>
                            </div>
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
                                <?php foreach ($card_brands as $brand) { ?>
                                    <option value="<?php echo e($brand); ?>"><?php echo e($brand); ?></option>
                                <?php } ?>
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
:root {
    --omni-bg: #f5f7fb;
    --omni-surface: #ffffff;
    --omni-border: #d9e1ec;
    --omni-text: #1f2a37;
    --omni-muted: #6b7280;
    --omni-primary: #2563eb;
}

#wrapper .content {
    background: var(--omni-bg);
}

.panel_s > .panel-body {
    background: var(--omni-surface);
    border: 1px solid var(--omni-border);
    border-radius: 12px;
    padding: 16px;
}

.omnipos-topbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}

.omnipos-topbar-actions {
    display: flex;
    gap: 8px;
}

.omnipos-status-alert {
    margin-bottom: 12px;
}

.omnipos-control-row {
    margin-bottom: 8px;
}

.omnipos-btn {
    min-height: 44px;
    font-weight: 600;
    border-radius: 10px;
}

.omnipos-pay-primary {
    min-height: 52px;
    border-radius: 12px;
    font-size: 18px;
    font-weight: 700;
}

.omnipos-cart-table thead th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--omni-muted);
}

.omnipos-qty-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.omnipos-qty-btn {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    border: 1px solid var(--omni-border);
    background: #fff;
    color: var(--omni-text);
    line-height: 1;
    padding: 0;
}

.omnipos-qty-value {
    min-width: 48px;
    text-align: center;
    font-weight: 600;
}

.omnipos-stock-badge {
    display: inline-block;
    margin-top: 6px;
}

.omnipos-stock-locked {
    opacity: 0.55;
}

.omnipos-grid-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 8px;
    margin-bottom: 8px;
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
    border-radius: 10px;
}

#omnipos-grid .omnipos-item-card {
    margin-bottom: 12px;
}

#omnipos-grid .omnipos-add-item {
    border: 1px solid var(--omni-border);
    border-radius: 12px;
    min-height: 110px;
    white-space: normal;
}

#omnipos-grid .omnipos-add-item:focus,
#omnipos-grid .omnipos-add-item:hover {
    border-color: var(--omni-primary);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.08);
}
</style>

<script>
(function () {
    'use strict';

    var selectedWallet = null;
    var currentCategory = 'all';
    var currentPage = 1;
    var pageSize = 16;
    var lastTotals = { grand_total: 0 };

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

    function getAddQty() {
        var qty = parseInt($('#omnipos-add-qty').val() || '1', 10);
        if (!qty || qty < 1) {
            qty = 1;
        }
        return qty;
    }

    function renderGridPage() {
        var visibleItems = [];
        $('.omnipos-item-card').each(function () {
            var card = $(this);
            var group = parseInt(card.data('group'), 10);
            if (currentCategory !== 'all' && group !== parseInt(currentCategory, 10)) {
                card.hide();
                return;
            }
            visibleItems.push(card);
        });

        var totalPages = Math.max(1, Math.ceil(visibleItems.length / pageSize));
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        var start = (currentPage - 1) * pageSize;
        var end = start + pageSize;

        $.each(visibleItems, function (idx, card) {
            if (idx >= start && idx < end) {
                card.show();
            } else {
                card.hide();
            }
        });

        $('#omnipos-page-indicator').text('Page ' + currentPage + ' / ' + totalPages);
        $('#omnipos-page-prev').prop('disabled', currentPage <= 1);
        $('#omnipos-page-next').prop('disabled', currentPage >= totalPages);
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
                '<td><div><strong>' + (row.description || '') + '</strong></div><div class="text-muted small">Tax: ' + money(row.line_tax_amount) + ' | Disc: ' + money(row.line_discount_amount) + '</div></td>' +
                '<td class="text-center"><div class="omnipos-qty-wrap">' +
                '<button type="button" class="omnipos-qty-btn omnipos-qty-minus" data-line-id="' + (row.id || '') + '">-</button>' +
                '<span class="omnipos-qty-value">' + money(row.qty) + '</span>' +
                '<button type="button" class="omnipos-qty-btn omnipos-qty-plus" data-line-id="' + (row.id || '') + '">+</button>' +
                '</div></td>' +
                '<td class="text-right">' + money(row.unit_price) + '</td>' +
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

    function toggleFullscreen() {
        var el = document.documentElement;
        if (!document.fullscreenElement) {
            if (el.requestFullscreen) {
                el.requestFullscreen();
            }
            return;
        }

        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }

    function addItem(itemId, qty) {
        $.post(admin_url + 'omnipos/pos/add_item', csrfPayload({ item_id: itemId, qty: qty || 1 }), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Add failed', 'warning');
                return;
            }
            renderCart(res.data.cart_items, res.data.cart, res.data.totals);
        }, 'json');
    }

    function adjustLineQty(lineId, delta) {
        var btn = $('.omnipos-edit-line[data-line-id="' + lineId + '"]');
        if (!btn.length) {
            return;
        }

        var currentQty = parseFloat(btn.data('line-qty') || '0');
        var targetQty = Math.max(0.01, currentQty + delta);

        $.post(admin_url + 'omnipos/pos/update_line_item', csrfPayload({
            line_id: lineId,
            qty: targetQty,
            discount_type: btn.data('line-discount-type') || '',
            discount_value: btn.data('line-discount-value') || 0,
            tax_rate: btn.data('line-tax-rate') || 0,
            notes: btn.data('line-notes') || ''
        }), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Failed to update qty', 'warning');
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
        addItem(parseInt($(this).data('item-id'), 10), getAddQty());
        $('#omnipos-search-results').addClass('hidden');
        $('#omnipos-search').val('');
    });

    $(document).on('click', '.omnipos-add-item', function () {
        if ($(this).is(':disabled')) {
            showMessage('Zero-stock lockout is active for this product.', 'warning');
            return;
        }
        addItem(parseInt($(this).data('item-id'), 10), getAddQty());
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
        currentCategory = $(this).data('group');
        currentPage = 1;
        renderGridPage();
    });

    $('#omnipos-page-prev').on('click', function () {
        if (currentPage > 1) {
            currentPage -= 1;
            renderGridPage();
        }
    });

    $('#omnipos-page-next').on('click', function () {
        currentPage += 1;
        renderGridPage();
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

    $(document).on('click', '.omnipos-qty-minus', function () {
        adjustLineQty(parseInt($(this).data('line-id'), 10), -1);
    });

    $(document).on('click', '.omnipos-qty-plus', function () {
        adjustLineQty(parseInt($(this).data('line-id'), 10), 1);
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

    $('#omnipos-pay-primary, #omnipos-checkout-cash').on('click', function () {
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

    $('#omnipos-toggle-fullscreen').on('click', toggleFullscreen);

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
    renderGridPage();
})();
</script>
<?php init_tail(); ?>
