<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $csrfName = $this->security->get_csrf_token_name(); ?>
<?php $csrfHash = $this->security->get_csrf_hash(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4 class="no-margin">Shift & Returns</h4>
                <p class="text-muted">Operations tab for opening/closing shifts, suspended cart recall, and refund processing.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Shift Controls</h5>
                        <div class="form-group">
                            <label>Active Warehouse</label>
                            <select class="form-control" id="omnipos-warehouse-id">
                                <?php foreach ($warehouses as $w) { ?>
                                    <option value="<?php echo (int) $w['id']; ?>" <?php echo (isset($current_shift['warehouse_id']) && (int) $current_shift['warehouse_id'] === (int) $w['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($w['name']); ?> (<?php echo e($w['code']); ?>)
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Register Key</label>
                            <input type="text" class="form-control" id="omnipos-register-key" value="<?php echo e(get_option('pos_default_register')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Opening Float</label>
                            <input type="number" class="form-control" id="omnipos-opening-float" value="0" step="0.01">
                        </div>
                        <button type="button" class="btn btn-default btn-block" id="omnipos-open-shift">Open Shift</button>

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
                        <button type="button" class="btn btn-danger btn-block" id="omnipos-close-shift">Close Shift</button>
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

            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Returns / Refunds</h5>
                        <p class="text-muted">Enter original transaction ID. Leave partial lines blank for full refund.</p>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Original Transaction ID</label>
                                    <input type="number" min="1" class="form-control" id="omnipos-refund-transaction-id" placeholder="e.g. 145">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Refund Method</label>
                                    <select class="form-control" id="omnipos-refund-type">
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                        <option value="wallet">Wallet</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Reason</label>
                                    <select class="form-control" id="omnipos-refund-reason">
                                        <?php foreach ($refund_reason_codes as $code) { ?>
                                            <option value="<?php echo e($code); ?>"><?php echo e($code); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Partial Lines (optional)</label>
                            <textarea class="form-control" id="omnipos-refund-lines" rows="3" placeholder="item_id:qty, item_id:qty"></textarea>
                        </div>
                        <button type="button" class="btn btn-danger" id="omnipos-process-refund">Process Refund</button>
                        <button type="button" class="btn btn-default" id="omnipos-load-recent-transactions">Load Recent Sales</button>

                        <hr>

                        <div class="table-responsive">
                            <table class="table table-condensed" id="omnipos-recent-transactions-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Invoice</th>
                                        <th>Total</th>
                                        <th>Type</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Shift History</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Register</th>
                                        <th>Opened</th>
                                        <th>Closed</th>
                                        <th>Status</th>
                                        <th>Opening Float</th>
                                        <th>Expected Cash</th>
                                        <th>Counted Cash</th>
                                        <th>Cash Variance</th>
                                        <th>Expected Card</th>
                                        <th>Counted Card</th>
                                        <th>Card Variance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row) { ?>
                                        <tr>
                                            <td><?php echo (int) $row['id']; ?></td>
                                            <td><?php echo e($row['register_key']); ?></td>
                                            <td><?php echo _dt($row['opened_at']); ?></td>
                                            <td><?php echo $row['closed_at'] ? _dt($row['closed_at']) : '-'; ?></td>
                                            <td><?php echo e(ucfirst($row['status'])); ?></td>
                                            <td><?php echo app_format_money((float) $row['opening_float'], get_base_currency()); ?></td>
                                            <td><?php echo app_format_money((float) $row['closing_expected_cash'], get_base_currency()); ?></td>
                                            <td><?php echo app_format_money((float) $row['closing_counted_cash'], get_base_currency()); ?></td>
                                            <td><?php echo app_format_money((float) $row['cash_variance'], get_base_currency()); ?></td>
                                            <td><?php echo app_format_money((float) $row['closing_expected_card'], get_base_currency()); ?></td>
                                            <td><?php echo app_format_money((float) $row['closing_counted_card'], get_base_currency()); ?></td>
                                            <td><?php echo app_format_money((float) $row['card_variance'], get_base_currency()); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    'use strict';

    function csrfPayload(payload) {
        payload = payload || {};
        payload['<?php echo e($csrfName); ?>'] = '<?php echo e($csrfHash); ?>';
        return payload;
    }

    function showMessage(message, type) {
        alert_float(type || 'info', message || 'Done');
    }

    function money(v) {
        return parseFloat(v || 0).toFixed(2);
    }

    function parseRefundLinesInput(raw) {
        var txt = String(raw || '').trim();
        if (!txt) {
            return [];
        }

        var parts = txt.split(',');
        var rows = [];

        $.each(parts, function (_, part) {
            var token = String(part || '').trim();
            if (!token) {
                return;
            }

            var pair = token.split(':');
            if (pair.length !== 2) {
                return;
            }

            var itemId = parseInt($.trim(pair[0]), 10);
            var qty = parseFloat($.trim(pair[1]));
            if (!itemId || !qty || qty <= 0) {
                return;
            }

            rows.push({ item_id: itemId, qty: qty });
        });

        return rows;
    }

    function renderRecentTransactions(rows) {
        var tbody = $('#omnipos-recent-transactions-table tbody');
        tbody.empty();

        $.each(rows || [], function (_, row) {
            var tr = '<tr>' +
                '<td>' + (row.id || '') + '</td>' +
                '<td>' + (row.invoice_id || '') + '</td>' +
                '<td>' + money(row.total) + '</td>' +
                '<td>' + (row.payment_type || '') + '</td>' +
                '<td><button type="button" class="btn btn-xs btn-default omnipos-pick-refund-transaction" data-transaction-id="' + (row.id || '') + '">Use</button></td>' +
                '</tr>';
            tbody.append(tr);
        });
    }

    function loadRecentTransactions() {
        $.get(admin_url + 'omnipos/pos/recent_transactions', function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Failed to load recent transactions', 'warning');
                return;
            }
            renderRecentTransactions(res.data.rows || []);
        }, 'json');
    }

    function processRefund() {
        var transactionId = parseInt($('#omnipos-refund-transaction-id').val() || '0', 10);
        if (!transactionId) {
            showMessage('Transaction ID is required for refund.', 'warning');
            return;
        }

        var lines = parseRefundLinesInput($('#omnipos-refund-lines').val());

        $.post(admin_url + 'omnipos/pos/refund_transaction', csrfPayload({
            transaction_id: transactionId,
            refund_type: $('#omnipos-refund-type').val(),
            reason: $('#omnipos-refund-reason').val(),
            refund_items: JSON.stringify(lines)
        }), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Refund failed', 'warning');
                return;
            }

            showMessage('Refund completed. Amount: ' + money(res.data.amount), 'success');
            $('#omnipos-refund-lines').val('');
            loadRecentTransactions();
        }, 'json');
    }

    $('#omnipos-open-shift').on('click', function () {
        $.post(admin_url + 'omnipos/pos/open_shift', csrfPayload({
            register_key: $('#omnipos-register-key').val(),
            opening_float: $('#omnipos-opening-float').val(),
            warehouse_id: $('#omnipos-warehouse-id').val()
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

    $('#omnipos-process-refund').on('click', processRefund);

    $('#omnipos-load-recent-transactions').on('click', loadRecentTransactions);

    $(document).on('click', '.omnipos-pick-refund-transaction', function () {
        $('#omnipos-refund-transaction-id').val($(this).data('transaction-id'));
    });

    loadRecentTransactions();
})();
</script>
<?php init_tail(); ?>
