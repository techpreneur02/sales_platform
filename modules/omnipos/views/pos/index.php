<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="omnipos-shell">
            <div class="omnipos-main">
                <div class="omnipos-left">
                    <div class="omnipos-left-header">
                        <div class="omnipos-shift-meta">
                            <?php if ($current_shift) { ?>
                                <span class="omnipos-shift-pill omnipos-shift-open">Open Shift: <?php echo e($current_shift['register_key']); ?></span>
                            <?php } else { ?>
                                <span class="omnipos-shift-pill omnipos-shift-closed">No Shift Open</span>
                            <?php } ?>
                            <span class="omnipos-shift-time"><?php echo date('D d M Y'); ?></span>
                        </div>
                        <div class="omnipos-header-actions">
                            <a href="<?php echo admin_url('omnipos/pos/shifts'); ?>" class="btn btn-default btn-sm">Shift & Returns</a>
                            <button type="button" class="btn btn-primary btn-sm" id="omnipos-toggle-fullscreen">Full Screen</button>
                        </div>
                    </div>

                    <div class="omnipos-mode-tabs" id="omnipos-mode-tabs">
                        <button type="button" class="omnipos-mode-tab active" data-view="keypad">Keypad</button>
                        <button type="button" class="omnipos-mode-tab" data-view="library">Library</button>
                        <button type="button" class="omnipos-mode-tab" data-view="favorites">Favorites</button>
                    </div>

                    <div class="omnipos-toolbar">
                        <div class="omnipos-search-wrap">
                            <input type="text" id="omnipos-search" class="form-control" placeholder="Search products">
                            <div id="omnipos-search-results" class="list-group omnipos-search-results hidden"></div>
                        </div>
                        <div class="omnipos-qty-wrap-top">
                            <label for="omnipos-add-qty">Qty</label>
                            <input type="number" id="omnipos-add-qty" class="form-control" min="1" step="1" value="1">
                        </div>
                    </div>

                    <div class="omnipos-category-chips" id="omnipos-category-chips">
                        <button type="button" class="omnipos-chip active" data-group="all">All</button>
                        <?php foreach ($items_groups as $group) { ?>
                            <button type="button" class="omnipos-chip" data-group="<?php echo (int) $group['id']; ?>"><?php echo e($group['name']); ?></button>
                        <?php } ?>
                    </div>

                    <div class="omnipos-grid" id="omnipos-grid">
                        <?php $itemIndex = 0; ?>
                        <?php foreach ($items as $item) { ?>
                            <?php
                            $itemId = isset($item['itemid']) ? (int) $item['itemid'] : (isset($item['id']) ? (int) $item['id'] : 0);
                            $groupId = isset($item['group_id']) ? (int) $item['group_id'] : (isset($item['groupid']) ? (int) $item['groupid'] : 0);
                            $stockQty = isset($stock_map[$itemId]) ? (float) $stock_map[$itemId] : 0;
                            $locked = $zero_stock_locked && $stockQty <= 0;
                            $itemName = (string) $item['description'];
                            $nameLower = function_exists('mb_strtolower') ? mb_strtolower($itemName) : strtolower($itemName);
                            $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $itemName), 0, 2));
                            if ($initials === '') {
                                $initials = 'IT';
                            }
                            $isFavorite = $itemIndex < 10 ? '1' : '0';
                            $itemIndex++;
                            ?>
                            <button type="button" class="omnipos-tile omnipos-add-item <?php echo $locked ? 'omnipos-stock-locked' : ''; ?>" data-item-id="<?php echo $itemId; ?>" data-group="<?php echo $groupId; ?>" data-item-name="<?php echo e($nameLower); ?>" data-favorite="<?php echo $isFavorite; ?>" data-stock-locked="<?php echo $locked ? '1' : '0'; ?>">
                                <span class="omnipos-tile-media"><?php echo e($initials); ?></span>
                                <span class="omnipos-tile-name"><?php echo e($itemName); ?></span>
                                <span class="omnipos-tile-price"><?php echo app_format_money((float) $item['rate'], get_base_currency()); ?></span>
                                <span class="omnipos-tile-stock <?php echo $stockQty <= 0 ? 'omnipos-stock-low' : 'omnipos-stock-ok'; ?>">Stock: <?php echo number_format($stockQty, 2); ?></span>
                            </button>
                        <?php } ?>
                    </div>

                    <div class="omnipos-grid-pagination">
                        <button type="button" class="btn btn-default btn-sm" id="omnipos-page-prev">Prev</button>
                        <span id="omnipos-page-indicator">Page 1 / 1</span>
                        <button type="button" class="btn btn-default btn-sm" id="omnipos-page-next">Next</button>
                    </div>
                </div>

                <div class="omnipos-right">
                    <div class="omnipos-cart-header">
                        <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#omnipos-customer-panel" aria-expanded="false" aria-controls="omnipos-customer-panel">Add a customer</button>
                    </div>

                    <div id="omnipos-customer-panel" class="collapse omnipos-customer-panel">
                        <div class="form-group">
                            <label>Client ID</label>
                            <input type="number" id="omnipos-client-id" class="form-control" placeholder="Customer ID" value="<?php echo (int) $default_client_id; ?>">
                        </div>
                        <div class="form-group">
                            <label>Points to Use</label>
                            <input type="number" id="omnipos-points-use" class="form-control" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label>Wallet Barcode</label>
                            <div class="input-group">
                                <input type="text" id="omnipos-wallet-barcode" class="form-control" placeholder="Scan wallet barcode">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" id="omnipos-wallet-lookup-btn">Lookup</button>
                                </span>
                            </div>
                        </div>
                        <div class="well well-sm" id="omnipos-wallet-profile" style="display:none; margin-bottom:0;">
                            <strong id="omnipos-wallet-name"></strong>
                            <span id="omnipos-wallet-employee" class="text-muted"></span>
                            <div class="small tw-mt-1">
                                Available: <span id="omnipos-wallet-available">0.00</span> |
                                Remaining: <span id="omnipos-wallet-remaining">0.00</span> |
                                Daily Left: <span id="omnipos-wallet-daily-left">0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="omnipos-cart-lines" id="omnipos-cart-lines"></div>

                    <div class="omnipos-cart-links">
                        <button type="button" class="btn btn-link" id="omnipos-open-adjustments">Add discount</button>
                    </div>

                    <div class="omnipos-totals">
                        <div><span>Subtotal</span><strong id="omnipos-total-subtotal">0.00</strong></div>
                        <div><span>Line Discount</span><strong id="omnipos-total-line-discount">0.00</strong></div>
                        <div><span>Tax</span><strong id="omnipos-total-tax">0.00</strong></div>
                        <div><span>Global Disc</span><strong id="omnipos-total-global-discount">0.00</strong></div>
                        <div><span>Service</span><strong id="omnipos-total-service">0.00</strong></div>
                        <div class="omnipos-grand-row"><span>Grand Total</span><strong id="omnipos-grand-total">0.00</strong></div>
                    </div>

                    <div class="omnipos-cash-row">
                        <input type="number" class="form-control" id="omnipos-cash-received" min="0" step="0.01" value="0" placeholder="Cash received">
                        <div class="omnipos-change">Change: <span id="omnipos-change-due">0.00</span></div>
                    </div>

                    <button type="button" class="btn btn-primary btn-block omnipos-charge-btn" id="omnipos-pay-primary">Pay Now <span id="omnipos-charge-amount">0.00</span></button>

                    <div class="omnipos-pay-chooser hidden" id="omnipos-pay-chooser">
                        <div class="omnipos-pay-methods" id="omnipos-pay-methods">
                            <button type="button" class="btn btn-default omnipos-pay-method active" data-method="cash">Cash</button>
                            <button type="button" class="btn btn-default omnipos-pay-method" data-method="card">Card</button>
                            <button type="button" class="btn btn-default omnipos-pay-method" data-method="wallet">Wallet</button>
                        </div>
                        <div id="omnipos-pay-card-fields" class="omnipos-pay-fields hidden">
                            <div class="form-group">
                                <label>Card Scheme</label>
                                <select id="omnipos-card-brand" class="form-control">
                                    <?php foreach ($card_brands as $brand) { ?>
                                        <option value="<?php echo e($brand); ?>"><?php echo e($brand); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-xs-8">
                                    <div class="form-group">
                                        <label>Auth Code (optional)</label>
                                        <input type="text" id="omnipos-card-auth" class="form-control" placeholder="Manual terminal ref">
                                    </div>
                                </div>
                                <div class="col-xs-4">
                                    <div class="form-group">
                                        <label>Last 4</label>
                                        <input type="text" id="omnipos-card-last4" class="form-control" maxlength="4" placeholder="0000">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="omnipos-pay-wallet-fields" class="omnipos-pay-fields hidden">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Wallet PIN</label>
                                <input type="password" id="omnipos-wallet-pin" class="form-control" maxlength="4" placeholder="****">
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-block" id="omnipos-pay-confirm">Complete Payment</button>
                    </div>

                    <div class="omnipos-secondary-actions">
                        <input type="text" class="form-control" id="omnipos-suspend-label" placeholder="Ticket label">
                        <button type="button" class="btn btn-default btn-block" id="omnipos-suspend-btn">Save ticket</button>
                        <button type="button" class="btn btn-default btn-block" id="omnipos-open-pay-chooser">Payment Methods</button>
                    </div>
                </div>
            </div>

            <div class="omnipos-bottom-nav">
                <button type="button" class="active">Checkout</button>
                <button type="button" onclick="window.location.href='<?php echo admin_url('omnipos/pos/shifts'); ?>'">Shifts</button>
                <button type="button" onclick="window.location.href='<?php echo admin_url('omnipos/pos/shifts'); ?>#omnipos-suspended-list'">Suspended</button>
                <button type="button" onclick="window.location.href='<?php echo admin_url('omnipos/inventory'); ?>'">Inventory</button>
                <button type="button" onclick="window.location.href='<?php echo admin_url('omnipos/settings'); ?>'">Settings</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="omniposAdjustmentsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Cart Adjustments</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Global Discount Type</label>
                    <select id="omnipos-global-discount-type" class="form-control">
                        <option value="">None</option>
                        <option value="fixed">Fixed</option>
                        <option value="percent">Percent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Global Discount Value</label>
                    <input type="number" id="omnipos-global-discount-value" class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Service Charge</label>
                    <input type="number" id="omnipos-service-charge" class="form-control" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="omnipos-apply-adjustments">Apply</button>
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

<style>
:root {
    --pos-bg: #eef2f7;
    --pos-surface: #ffffff;
    --pos-border: #d8e0eb;
    --pos-text: #1f2937;
    --pos-muted: #6b7280;
    --pos-primary: #3366e8;
    --pos-success: #1f9d55;
    --pos-danger: #d04545;
}

#wrapper .content {
    background: var(--pos-bg);
}

.omnipos-shell {
    background: var(--pos-bg);
    border-radius: 14px;
}

.omnipos-main {
    display: grid;
    grid-template-columns: 1.85fr 1fr;
    gap: 12px;
}

.omnipos-left,
.omnipos-right {
    background: var(--pos-surface);
    border: 1px solid var(--pos-border);
    border-radius: 14px;
    padding: 12px;
}

.omnipos-left-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.omnipos-header-actions {
    display: flex;
    gap: 6px;
}

.omnipos-shift-meta {
    display: flex;
    gap: 8px;
    align-items: center;
}

.omnipos-shift-pill {
    font-size: 12px;
    border-radius: 999px;
    padding: 4px 10px;
    font-weight: 600;
}

.omnipos-shift-open {
    color: #0f7a3b;
    background: #e7f8ee;
}

.omnipos-shift-closed {
    color: #9a6b00;
    background: #fff5dc;
}

.omnipos-shift-time {
    color: var(--pos-muted);
    font-size: 12px;
}

.omnipos-mode-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 10px;
}

.omnipos-mode-tab {
    border: 1px solid var(--pos-border);
    background: #fff;
    color: #111827;
    border-radius: 8px;
    padding: 8px 10px;
    font-weight: 600;
}

.omnipos-mode-tab.active {
    background: #e9f0ff;
    border-color: #b8ccff;
    color: #1d4ed8;
}

.omnipos-toolbar {
    display: grid;
    grid-template-columns: 1fr 110px;
    gap: 8px;
    margin-bottom: 10px;
}

.omnipos-qty-wrap-top label {
    font-size: 12px;
    color: var(--pos-muted);
    margin-bottom: 3px;
}

.omnipos-search-wrap {
    position: relative;
}

.omnipos-search-results {
    position: absolute;
    z-index: 25;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 260px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid var(--pos-border);
}

.omnipos-category-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
}

.omnipos-chip {
    border: 1px solid var(--pos-border);
    background: #fff;
    border-radius: 18px;
    padding: 5px 10px;
    font-size: 12px;
}

.omnipos-chip.active {
    background: #edf3ff;
    border-color: #bdd0ff;
    color: #1d4ed8;
}

.omnipos-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    min-height: 430px;
}

.omnipos-tile {
    border: 1px solid var(--pos-border);
    background: #fff;
    border-radius: 10px;
    min-height: 116px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    text-align: center;
}

.omnipos-tile:focus,
.omnipos-tile:hover {
    border-color: #8bb0ff;
    box-shadow: 0 0 0 2px rgba(51, 102, 232, 0.08);
}

.omnipos-tile-media {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    background: #e5edf9;
}

.omnipos-tile-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--pos-text);
}

.omnipos-tile-price {
    font-size: 12px;
    color: #111827;
}

.omnipos-tile-stock {
    font-size: 11px;
    border-radius: 10px;
    padding: 2px 7px;
}

.omnipos-stock-ok {
    background: #e7f8ee;
    color: #0f7a3b;
}

.omnipos-stock-low {
    background: #fdecec;
    color: #a52828;
}

.omnipos-stock-locked {
    opacity: 0.45;
}

.omnipos-grid-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.omnipos-right {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.omnipos-cart-header {
    display: flex;
    justify-content: flex-end;
}

.omnipos-customer-panel {
    border: 1px solid var(--pos-border);
    border-radius: 10px;
    padding: 10px;
    background: #f8fbff;
}

.omnipos-cart-lines {
    border: 1px solid var(--pos-border);
    border-radius: 10px;
    min-height: 220px;
    max-height: 320px;
    overflow-y: auto;
    padding: 6px;
}

.omnipos-cart-line {
    display: grid;
    grid-template-columns: 1.35fr 92px 90px 66px;
    gap: 6px;
    align-items: center;
    padding: 7px 6px;
    border-bottom: 1px solid #edf1f7;
}

.omnipos-cart-line:last-child {
    border-bottom: 0;
}

.omnipos-cart-name {
    font-size: 13px;
    font-weight: 600;
}

.omnipos-cart-sub {
    font-size: 11px;
    color: var(--pos-muted);
}

.omnipos-qty-wrap {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.omnipos-qty-btn {
    width: 24px;
    height: 24px;
    border-radius: 7px;
    border: 1px solid var(--pos-border);
    background: #fff;
    line-height: 1;
}

.omnipos-qty-value {
    min-width: 34px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
}

.omnipos-line-total {
    text-align: right;
    font-size: 12px;
    font-weight: 700;
}

.omnipos-line-edit {
    text-align: right;
}

.omnipos-cart-links {
    text-align: left;
}

.omnipos-totals {
    border: 1px solid var(--pos-border);
    border-radius: 10px;
    padding: 8px;
}

.omnipos-totals > div {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
    color: #111827;
}

.omnipos-grand-row {
    font-size: 18px;
    font-weight: 700;
    margin-top: 4px;
    border-top: 1px solid #e9edf4;
    padding-top: 6px;
}

.omnipos-cash-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    align-items: center;
}

.omnipos-change {
    font-size: 12px;
    color: var(--pos-muted);
}

.omnipos-charge-btn {
    border-radius: 10px;
    height: 46px;
    font-size: 17px;
    font-weight: 700;
    background: var(--pos-primary);
    border-color: var(--pos-primary);
}

.omnipos-pay-chooser {
    border: 1px solid var(--pos-border);
    border-radius: 10px;
    padding: 8px;
    background: #f8fbff;
}

.omnipos-pay-methods {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
    margin-bottom: 8px;
}

.omnipos-pay-method.active {
    color: #1d4ed8;
    border-color: #b8ccff;
    background: #edf3ff;
}

.omnipos-pay-fields {
    margin-bottom: 8px;
}

.omnipos-secondary-actions .btn,
.omnipos-secondary-actions .form-control {
    margin-top: 6px;
    border-radius: 8px;
}

.omnipos-bottom-nav {
    margin-top: 10px;
    background: var(--pos-surface);
    border: 1px solid var(--pos-border);
    border-radius: 12px;
    padding: 8px;
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 6px;
}

.omnipos-bottom-nav button {
    border: 1px solid var(--pos-border);
    background: #fff;
    border-radius: 8px;
    padding: 8px 6px;
    font-weight: 600;
    font-size: 12px;
}

.omnipos-bottom-nav button.active {
    color: #1d4ed8;
    border-color: #b8ccff;
    background: #edf3ff;
}

@media (max-width: 1200px) {
    .omnipos-main {
        grid-template-columns: 1fr;
    }

    .omnipos-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        min-height: 340px;
    }

    .omnipos-cart-line {
        grid-template-columns: 1.25fr 92px 80px 56px;
    }
}

@media (max-width: 768px) {
    .omnipos-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .omnipos-toolbar {
        grid-template-columns: 1fr;
    }

    .omnipos-topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .omnipos-bottom-nav {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<?php init_tail(); ?>
<script>
(function bootstrapOmniPos(retryCount) {
    retryCount = retryCount || 0;

    if (typeof window.jQuery === 'undefined' || typeof window.admin_url !== 'string') {
        if (retryCount < 100) {
            setTimeout(function () {
                bootstrapOmniPos(retryCount + 1);
            }, 50);
        }
        return;
    }

    var $ = window.jQuery;
    'use strict';

    var ajaxBase = window.admin_url;

    var selectedWallet = null;
    var currentCategory = 'all';
    var currentView = 'keypad';
    var currentPage = 1;
    var pageSize = 16;
    var lastTotals = { grand_total: 0 };
    var selectedPaymentMethod = 'cash';

    function csrfPayload(payload) {
        payload = payload || {};
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
        var cards = [];

        $('.omnipos-tile').each(function () {
            var card = $(this);
            var group = parseInt(card.data('group'), 10);
            var fav = String(card.data('favorite')) === '1';

            if (currentCategory !== 'all' && group !== parseInt(currentCategory, 10)) {
                card.hide();
                return;
            }

            if (currentView === 'favorites' && !fav) {
                card.hide();
                return;
            }

            cards.push(card);
        });

        var totalPages = Math.max(1, Math.ceil(cards.length / pageSize));
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        var start = (currentPage - 1) * pageSize;
        var end = start + pageSize;

        $.each(cards, function (idx, card) {
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
        $('#omnipos-charge-amount').text(money(totals.grand_total));
        refreshChangeDue();
    }

    function renderCart(items, cart, totals) {
        var box = $('#omnipos-cart-lines');
        box.empty();

        if (!items || !items.length) {
            box.append('<div class="text-muted" style="padding:10px;">No items yet.</div>');
        }

        $.each(items || [], function (_, row) {
            var line = '<div class="omnipos-cart-line">' +
                '<div><div class="omnipos-cart-name">' + (row.description || '') + '</div><div class="omnipos-cart-sub">Tax: ' + money(row.line_tax_amount) + ' | Disc: ' + money(row.line_discount_amount) + '</div></div>' +
                '<div class="omnipos-qty-wrap">' +
                '<button type="button" class="omnipos-qty-btn omnipos-qty-minus" data-line-id="' + (row.id || '') + '">-</button>' +
                '<span class="omnipos-qty-value">' + money(row.qty) + '</span>' +
                '<button type="button" class="omnipos-qty-btn omnipos-qty-plus" data-line-id="' + (row.id || '') + '">+</button>' +
                '</div>' +
                '<div class="omnipos-line-total">' + money(row.line_total) + '</div>' +
                '<div class="omnipos-line-edit"><button class="btn btn-xs btn-default omnipos-edit-line" ' +
                'data-line-id="' + (row.id || '') + '" ' +
                'data-line-qty="' + (row.qty || 0) + '" ' +
                'data-line-discount-type="' + (row.modifier_discount_type || '') + '" ' +
                'data-line-discount-value="' + (row.modifier_discount_value || 0) + '" ' +
                'data-line-tax-rate="' + (row.modifier_tax_rate || 0) + '" ' +
                'data-line-notes="' + String(row.modifier_notes || '').replace(/"/g, '&quot;') + '">Edit</button></div>' +
                '</div>';
            box.append(line);
        });

        if (cart) {
            $('#omnipos-global-discount-type').val(cart.global_discount_type || '');
            $('#omnipos-global-discount-value').val(cart.global_discount_value || 0);
            $('#omnipos-service-charge').val(cart.service_charge_value || 0);
        }

        renderTotals(totals || {});
    }

    function reloadCart() {
        $.get(ajaxBase + 'omnipos/pos/cart', function (res) {
            if (!res || !res.success) {
                return;
            }
            renderCart(res.data.items, res.data.cart, res.data.totals);
        }, 'json');
    }

    function addItem(itemId, qty) {
        $.post(ajaxBase + 'omnipos/pos/add_item', csrfPayload({ item_id: itemId, qty: qty || 1 }), function (res) {
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

        $.post(ajaxBase + 'omnipos/pos/update_line_item', csrfPayload({
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

        if (paymentType === 'card') {
            payload.card_brand = $('#omnipos-card-brand').val();
            payload.card_auth_code = $('#omnipos-card-auth').val();
            payload.card_last4 = $('#omnipos-card-last4').val();
        }

        if (paymentType === 'wallet') {
            if (!selectedWallet) {
                showMessage('Lookup a wallet staff profile first.', 'warning');
                return;
            }
            payload.wallet_staff_id = selectedWallet.id;
            payload.wallet_pin = $('#omnipos-wallet-pin').val();
        }

        $.post(ajaxBase + 'omnipos/pos/checkout', csrfPayload(payload), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Checkout failed', 'danger');
                return;
            }
            showMessage('Checkout complete. Invoice #' + res.data.invoice_id + ' | Change: ' + money(res.data.change_due), 'success');
            reloadCart();
            $('#omnipos-pay-chooser').addClass('hidden');
        }, 'json');
    }

    function setPaymentMethod(method) {
        selectedPaymentMethod = method;
        $('#omnipos-pay-methods .omnipos-pay-method').removeClass('active');
        $('#omnipos-pay-methods .omnipos-pay-method[data-method="' + method + '"]').addClass('active');
        $('#omnipos-pay-card-fields').toggleClass('hidden', method !== 'card');
        $('#omnipos-pay-wallet-fields').toggleClass('hidden', method !== 'wallet');
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
        $.post(ajaxBase + 'omnipos/pos/wallet_lookup', csrfPayload({ barcode: barcode }), function (res) {
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

    function toggleFullscreen() {
        var target = document.getElementById('wrapper') || document.documentElement;
        var isFull = !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);

        if (!isFull) {
            if (target.requestFullscreen) {
                target.requestFullscreen();
                return;
            }
            if (target.webkitRequestFullscreen) {
                target.webkitRequestFullscreen();
                return;
            }
            if (target.msRequestFullscreen) {
                target.msRequestFullscreen();
                return;
            }
            showMessage('Fullscreen is not supported in this browser mode.', 'warning');
            return;
        }

        if (document.exitFullscreen) {
            document.exitFullscreen();
            return;
        }
        if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
            return;
        }
        if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
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
            $.get(ajaxBase + 'omnipos/pos/search_items', { q: q }, function (res) {
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

    $(document).on('click', '.omnipos-add-item', function (e) {
        e.preventDefault();
        if ($(this).data('stock-locked') === 1 || String($(this).data('stock-locked')) === '1') {
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

    $('#omnipos-mode-tabs').on('click', '.omnipos-mode-tab', function (e) {
        e.preventDefault();
        $('#omnipos-mode-tabs .omnipos-mode-tab').removeClass('active');
        $(this).addClass('active');
        currentView = $(this).data('view');
        currentPage = 1;
        $('#omnipos-category-chips').show();
        if (currentView === 'keypad') {
            currentCategory = 'all';
            $('#omnipos-category-chips .omnipos-chip').removeClass('active');
            $('#omnipos-category-chips .omnipos-chip[data-group="all"]').addClass('active');
        }
        renderGridPage();
    });

    $('#omnipos-category-chips').on('click', '.omnipos-chip', function (e) {
        e.preventDefault();
        $('#omnipos-category-chips .omnipos-chip').removeClass('active');
        $(this).addClass('active');
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

    $('#omnipos-open-adjustments').on('click', function () {
        $('#omniposAdjustmentsModal').modal('show');
    });

    $('#omnipos-apply-adjustments').on('click', function () {
        $.post(ajaxBase + 'omnipos/pos/update_cart_adjustments', csrfPayload({
            discount_type: $('#omnipos-global-discount-type').val(),
            discount_value: $('#omnipos-global-discount-value').val(),
            service_charge: $('#omnipos-service-charge').val()
        }), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Failed to update adjustments', 'warning');
                return;
            }
            renderCart(res.data.cart_items, res.data.cart, res.data.totals);
            $('#omniposAdjustmentsModal').modal('hide');
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
        $.post(ajaxBase + 'omnipos/pos/update_line_item', csrfPayload({
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
        $.post(ajaxBase + 'omnipos/pos/suspend_cart', csrfPayload({ label: $('#omnipos-suspend-label').val() }), function (res) {
            showMessage(res.message, res.success ? 'success' : 'warning');
            if (res.success) {
                setTimeout(function () { window.location.reload(); }, 500); }
        }, 'json');
    });

    $('#omnipos-cash-received').on('input', refreshChangeDue);

    $('#omnipos-pay-primary, #omnipos-open-pay-chooser').on('click', function () {
        $('#omnipos-pay-chooser').toggleClass('hidden');
    });

    $('#omnipos-pay-methods').on('click', '.omnipos-pay-method', function () {
        setPaymentMethod($(this).data('method'));
    });

    $('#omnipos-pay-confirm').on('click', function () {
        if (selectedPaymentMethod === 'wallet' && !selectedWallet) {
            showMessage('Lookup wallet staff profile first.', 'warning');
            return;
        }
        checkout(selectedPaymentMethod);
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
    $('#omnipos-category-chips').show();
    setPaymentMethod('cash');
    renderGridPage();
})(0);
</script>
