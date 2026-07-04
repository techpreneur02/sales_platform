<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">OmniPOS Terminal</h4>
                        <p class="text-muted">Scanner-ready terminal with suspend/recall, cash/card checkout, and invoice posting.</p>

                        <?php if ($current_shift) { ?>
                            <div class="alert alert-success">Open shift: <?php echo e($current_shift['register_key']); ?>, started <?php echo _dt($current_shift['opened_at']); ?></div>
                        <?php } else { ?>
                            <div class="alert alert-warning">No shift open. Open a shift to start scanning.</div>
                        <?php } ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Client ID</label>
                                    <input type="number" id="omnipos-client-id" class="form-control" placeholder="Customer ID for invoice">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Points to Use</label>
                                    <input type="number" id="omnipos-points-use" class="form-control" min="0" value="0">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped" id="omnipos-cart-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-right">Qty</th>
                                        <th class="text-right">Unit</th>
                                        <th class="text-right">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $row) { ?>
                                        <tr>
                                            <td><?php echo e($row['description']); ?></td>
                                            <td class="text-right"><?php echo (float) $row['qty']; ?></td>
                                            <td class="text-right"><?php echo app_format_money((float) $row['unit_price'], get_base_currency()); ?></td>
                                            <td class="text-right"><?php echo app_format_money((float) $row['line_total'], get_base_currency()); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <h4 id="omnipos-subtotal" class="text-right">Subtotal: <?php echo app_format_money(array_sum(array_map(function ($r) { return (float) $r['line_total']; }, $cart_items)), get_base_currency()); ?></h4>

                        <div class="row">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-info btn-block omnipos-btn" id="omnipos-suspend-btn">Suspend Cart</button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-success btn-block omnipos-btn" id="omnipos-checkout-cash">Checkout Cash</button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-primary btn-block omnipos-btn" data-toggle="modal" data-target="#omniposCardModal">Checkout Card</button>
                            </div>
                        </div>

                        <hr>

                        <h5>Quick Product Grid</h5>
                        <div class="row">
                            <?php foreach ($items as $item) { ?>
                                <div class="col-md-3 col-xs-6 tw-mb-2">
                                    <button class="btn btn-default btn-block omnipos-btn omnipos-quick-item" data-code="<?php echo e($item['description']); ?>">
                                        <?php echo e($item['description']); ?><br>
                                        <small><?php echo app_format_money((float) $item['rate'], get_base_currency()); ?></small>
                                    </button>
                                </div>
                            <?php } ?>
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
                        <h5>Suspended Carts</h5>
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

<div class="modal fade" id="omniposCardModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Card Payment Details</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Card Brand</label>
                    <input type="text" id="omnipos-card-brand" class="form-control" placeholder="Visa / Mastercard / AMEX">
                </div>
                <div class="form-group">
                    <label>Terminal Auth Code</label>
                    <input type="text" id="omnipos-card-auth" class="form-control" placeholder="Auth Code">
                </div>
                <div class="form-group">
                    <label>Last 4 Digits</label>
                    <input type="text" id="omnipos-card-last4" class="form-control" maxlength="4" placeholder="1234">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="omnipos-checkout-card">Confirm Card Checkout</button>
            </div>
        </div>
    </div>
</div>

<style>
.omnipos-btn {
    min-height: 48px;
    font-weight: 600;
}
</style>

<script>
(function () {
    'use strict';

    function csrfPayload(payload) {
        if (typeof csrfData !== 'undefined') {
            payload[csrfData.token_name] = csrfData.hash;
        }
        return payload;
    }

    function showMessage(message, type) {
        alert_float(type || 'info', message || 'Done');
    }

    function renderCart(items, totals) {
        var tbody = $('#omnipos-cart-table tbody');
        tbody.empty();

        $.each(items || [], function (_, row) {
            var tr = '<tr>' +
                '<td>' + (row.description || '') + '</td>' +
                '<td class="text-right">' + parseFloat(row.qty || 0).toFixed(2) + '</td>' +
                '<td class="text-right">' + parseFloat(row.unit_price || 0).toFixed(2) + '</td>' +
                '<td class="text-right">' + parseFloat(row.line_total || 0).toFixed(2) + '</td>' +
                '</tr>';
            tbody.append(tr);
        });

        var subtotal = totals && totals.subtotal ? totals.subtotal : 0;
        $('#omnipos-subtotal').text('Subtotal: ' + parseFloat(subtotal).toFixed(2));
    }

    function reloadCart() {
        $.get(admin_url + 'omnipos/pos/cart', function (res) {
            if (!res || !res.success) {
                return;
            }
            renderCart(res.data.items, res.data.totals);
        }, 'json');
    }

    function checkout(paymentType, cardData) {
        var payload = {
            client_id: parseInt($('#omnipos-client-id').val() || '0', 10),
            payment_type: paymentType,
            points_to_use: parseInt($('#omnipos-points-use').val() || '0', 10)
        };

        if (paymentType === 'card') {
            payload.card_brand = cardData.brand;
            payload.card_auth_code = cardData.auth;
            payload.card_last4 = cardData.last4;
        }

        $.post(admin_url + 'omnipos/pos/checkout', csrfPayload(payload), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Checkout failed', 'danger');
                return;
            }
            showMessage('Checkout complete. Invoice #' + res.data.invoice_id, 'success');
            reloadCart();
        }, 'json');
    }

    $(document).on('click', '.omnipos-quick-item', function () {
        var code = $(this).data('code');
        $.post(admin_url + 'omnipos/pos/scan_item', csrfPayload({ barcode: code }), function (res) {
            if (!res || !res.success) {
                showMessage(res && res.message ? res.message : 'Scan failed', 'warning');
                return;
            }
            renderCart(res.data.cart_items, res.data.totals);
        }, 'json');
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

    $('#omnipos-suspend-btn').on('click', function () {
        $.post(admin_url + 'omnipos/pos/suspend_cart', csrfPayload({ label: 'Front Counter' }), function (res) {
            showMessage(res.message, res.success ? 'success' : 'warning');
            if (res.success) {
                setTimeout(function () { window.location.reload(); }, 500);
            }
        }, 'json');
    });

    $('#omnipos-checkout-cash').on('click', function () {
        checkout('cash', {});
    });

    $('#omnipos-checkout-card').on('click', function () {
        checkout('card', {
            brand: $('#omnipos-card-brand').val(),
            auth: $('#omnipos-card-auth').val(),
            last4: $('#omnipos-card-last4').val()
        });
        $('#omniposCardModal').modal('hide');
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

        renderCart(response.data.cart_items, response.data.totals);
    });
})();
</script>
<?php init_tail(); ?>
