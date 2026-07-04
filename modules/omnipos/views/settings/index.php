<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $csrfName = $this->security->get_csrf_token_name(); ?>
<?php $csrfHash = $this->security->get_csrf_hash(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">OmniPOS Settings</h4>
                        <p class="text-muted">Use this page to keep all POS dynamic values and core behavior settings in one dedicated place.</p>

                        <ul class="nav nav-tabs" role="tablist">
                            <li role="presentation" class="<?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                                <a href="#omnipos-general" aria-controls="omnipos-general" role="tab" data-toggle="tab">General</a>
                            </li>
                            <li role="presentation" class="<?php echo $active_tab === 'dynamic' ? 'active' : ''; ?>">
                                <a href="#omnipos-dynamic" aria-controls="omnipos-dynamic" role="tab" data-toggle="tab">Dynamic Values</a>
                            </li>
                        </ul>

                        <div class="tab-content tw-mt-3">
                            <div role="tabpanel" class="tab-pane <?php echo $active_tab === 'general' ? 'active' : ''; ?>" id="omnipos-general">
                                <form method="post" action="<?php echo admin_url('omnipos/settings/save_general'); ?>">
                                    <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Default Register Key</label>
                                                <input type="text" name="default_register" class="form-control" value="<?php echo e($settings['default_register']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Default Warehouse</label>
                                                <select name="default_warehouse_id" class="form-control" required>
                                                    <?php foreach ($warehouses as $warehouse) { ?>
                                                        <option value="<?php echo (int) $warehouse['id']; ?>" <?php echo (int) $settings['default_warehouse_id'] === (int) $warehouse['id'] ? 'selected' : ''; ?>>
                                                            <?php echo e($warehouse['name']); ?> (<?php echo e($warehouse['code']); ?>)
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Default Item Unit</label>
                                                <select name="default_item_unit" class="form-control" required>
                                                    <?php foreach ($settings['item_units'] as $unit) { ?>
                                                        <option value="<?php echo e($unit); ?>" <?php echo $settings['default_item_unit'] === $unit ? 'selected' : ''; ?>><?php echo e($unit); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Default Tax Rate (%)</label>
                                                <input type="number" min="0" step="0.01" name="default_tax_rate" class="form-control" value="<?php echo e($settings['default_tax_rate']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Loyalty Earn Rate</label>
                                                <input type="number" min="0" step="0.01" name="loyalty_earn_rate" class="form-control" value="<?php echo e($settings['loyalty_earn_rate']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Loyalty Point Value</label>
                                                <input type="number" min="0" step="0.0001" name="loyalty_point_value" class="form-control" value="<?php echo e($settings['loyalty_point_value']); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="allow-zero-stock" name="allow_zero_stock_sales" <?php echo $settings['allow_zero_stock_sales'] === '1' ? 'checked' : ''; ?>>
                                        <label for="allow-zero-stock">Allow selling when stock is zero or below</label>
                                    </div>

                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="wallet-require-pin" name="wallet_require_pin" <?php echo $settings['wallet_require_pin'] === '1' ? 'checked' : ''; ?>>
                                        <label for="wallet-require-pin">Require 4-digit PIN for wallet checkout</label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Save General Settings</button>
                                </form>
                            </div>

                            <div role="tabpanel" class="tab-pane <?php echo $active_tab === 'dynamic' ? 'active' : ''; ?>" id="omnipos-dynamic">
                                <form method="post" action="<?php echo admin_url('omnipos/settings/save_dynamic_values'); ?>">
                                    <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Item Units (one per line)</label>
                                                <textarea name="item_units" class="form-control" rows="8"><?php echo e(implode("\n", $settings['item_units'])); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Card Brands (one per line)</label>
                                                <textarea name="card_brands" class="form-control" rows="8"><?php echo e(implode("\n", $settings['card_brands'])); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Shrinkage Reason Codes (one per line)</label>
                                                <textarea name="shrinkage_reason_codes" class="form-control" rows="6"><?php echo e(implode("\n", $settings['shrinkage_reason_codes'])); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Refund Reason Codes (one per line)</label>
                                                <textarea name="refund_reason_codes" class="form-control" rows="6"><?php echo e(implode("\n", $settings['refund_reason_codes'])); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-success">Save Dynamic Values</button>
                                </form>
                            </div>
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

    var activeTab = '<?php echo e($active_tab); ?>';
    if (activeTab === 'dynamic') {
        $('.nav-tabs a[href="#omnipos-dynamic"]').tab('show');
    } else {
        $('.nav-tabs a[href="#omnipos-general"]').tab('show');
    }
})();
</script>
<?php init_tail(); ?>
