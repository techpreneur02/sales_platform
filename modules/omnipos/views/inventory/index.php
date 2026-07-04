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
                        <h4 class="no-margin">OmniPOS Inventory Control</h4>
                        <p class="text-muted">Simple multi-warehouse stock control, procurement, receiving, transfers, shrinkage logging, and movement ledger.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Add Warehouse</h5>
                        <form method="post" action="<?php echo admin_url('omnipos/inventory/add_warehouse'); ?>">
                            <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Code</label>
                                <input type="text" name="code" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Warehouse</button>
                        </form>

                        <hr>

                        <h5>Seed Sample Products & Pricing</h5>
                        <form method="post" action="<?php echo admin_url('omnipos/inventory/seed_samples'); ?>">
                            <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                            <div class="form-group">
                                <label>Warehouse</label>
                                <select name="warehouse_id" class="form-control">
                                    <?php foreach ($warehouses as $w) { ?>
                                        <option value="<?php echo (int) $w['id']; ?>"><?php echo e($w['name']); ?> (<?php echo e($w['code']); ?>)</option>
                                    <?php } ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success">Seed Samples</button>
                        </form>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Transfer Stock</h5>
                        <form method="post" action="<?php echo admin_url('omnipos/inventory/transfer_stock'); ?>">
                            <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                            <div class="form-group">
                                <label>Source Warehouse</label>
                                <select name="source_warehouse_id" class="form-control" required>
                                    <?php foreach ($warehouses as $w) { ?>
                                        <option value="<?php echo (int) $w['id']; ?>"><?php echo e($w['name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Destination Warehouse</label>
                                <select name="destination_warehouse_id" class="form-control" required>
                                    <?php foreach ($warehouses as $w) { ?>
                                        <option value="<?php echo (int) $w['id']; ?>"><?php echo e($w['name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Item</label>
                                <select name="item_id" class="form-control" required>
                                    <?php foreach ($items as $item) { ?>
                                        <option value="<?php echo (int) $item['itemid']; ?>"><?php echo e($item['description']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Qty</label>
                                <input type="number" min="0.01" step="0.01" name="qty" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-info">Transfer</button>
                        </form>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Shrinkage / Loss Logger</h5>
                        <form method="post" action="<?php echo admin_url('omnipos/inventory/log_shrinkage'); ?>">
                            <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                            <div class="form-group">
                                <label>Warehouse</label>
                                <select name="warehouse_id" class="form-control" required>
                                    <?php foreach ($warehouses as $w) { ?>
                                        <option value="<?php echo (int) $w['id']; ?>"><?php echo e($w['name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Item</label>
                                <select name="item_id" class="form-control" required>
                                    <?php foreach ($items as $item) { ?>
                                        <option value="<?php echo (int) $item['itemid']; ?>"><?php echo e($item['description']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Qty</label>
                                <input type="number" min="0.01" step="0.01" name="qty" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Reason Code</label>
                                <select name="reason_code" class="form-control" required>
                                    <?php foreach ($shrinkage_reason_codes as $code) { ?>
                                        <option value="<?php echo e($code); ?>"><?php echo e($code); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger">Log Shrinkage</button>
                        </form>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5>CSV Import / Export</h5>
                        <p class="text-muted">Use template columns exactly: warehouse_code, item_name, unit, price, qty_on_hand, reorder_level, group_name.</p>
                        <p class="text-muted">Allowed units from settings: <?php echo e(implode(', ', $item_units)); ?></p>
                        <a href="<?php echo admin_url('omnipos/inventory/download_import_template'); ?>" class="btn btn-default btn-sm">Download Template</a>
                        <a href="<?php echo admin_url('omnipos/inventory/export_stock_csv'); ?>" class="btn btn-info btn-sm">Export Stock CSV</a>

                        <hr>

                        <form method="post" action="<?php echo admin_url('omnipos/inventory/import_stock_csv'); ?>" enctype="multipart/form-data">
                            <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                            <div class="form-group">
                                <label>Import CSV File</label>
                                <input type="file" name="stock_csv" accept=".csv,text/csv" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Import CSV</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Vendor Procurement Dashboard</h5>
                        <form method="post" action="<?php echo admin_url('omnipos/inventory/create_purchase_order'); ?>">
                            <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                            <div class="row">
                                <div class="col-md-3">
                                    <label>Warehouse</label>
                                    <select name="warehouse_id" class="form-control" required>
                                        <?php foreach ($warehouses as $w) { ?>
                                            <option value="<?php echo (int) $w['id']; ?>"><?php echo e($w['name']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label>Supplier</label>
                                    <input type="text" name="supplier_name" class="form-control" required>
                                </div>
                                <div class="col-md-2">
                                    <label>PO Ref</label>
                                    <input type="text" name="reference_no" class="form-control" required>
                                </div>
                                <div class="col-md-2">
                                    <label>Expected Date</label>
                                    <input type="date" name="expected_at" class="form-control">
                                </div>
                                <div class="col-md-2">
                                    <label>Item</label>
                                    <select name="item_id" class="form-control" required>
                                        <?php foreach ($items as $item) { ?>
                                            <option value="<?php echo (int) $item['itemid']; ?>"><?php echo e($item['description']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row tw-mt-2">
                                <div class="col-md-2">
                                    <label>Expected Qty</label>
                                    <input type="number" min="0.01" step="0.01" name="expected_qty" class="form-control" required>
                                </div>
                                <div class="col-md-2">
                                    <label>Unit Cost</label>
                                    <input type="number" min="0" step="0.01" name="unit_cost" class="form-control" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">Create PO</button>
                                </div>
                            </div>
                        </form>

                        <hr>

                        <h5>Inbound PO Quantity Auditor / Receiving Terminal</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Warehouse</th>
                                        <th>Supplier</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                        <th>Expected</th>
                                        <th>Receive</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchase_orders as $po) { ?>
                                        <tr>
                                            <td><?php echo (int) $po['id']; ?></td>
                                            <td><?php echo (int) $po['warehouse_id']; ?></td>
                                            <td><?php echo e($po['supplier_name']); ?></td>
                                            <td><?php echo e($po['reference_no']); ?></td>
                                            <td><?php echo e($po['status']); ?></td>
                                            <td><?php echo e($po['expected_at']); ?></td>
                                            <td>
                                                <form method="post" action="<?php echo admin_url('omnipos/inventory/receive_purchase_order/' . (int) $po['id']); ?>" class="form-inline">
                                                    <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                                                    <input type="number" min="0.01" step="0.01" name="received_qty" class="form-control" placeholder="Qty" required>
                                                    <button type="submit" class="btn btn-success btn-sm">Receive</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5>Central Stock Movement Ledger</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Warehouse</th>
                                        <th>To Warehouse</th>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Qty Change</th>
                                        <th>Qty After</th>
                                        <th>Reason</th>
                                        <th>Reference</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ledger as $row) { ?>
                                        <tr>
                                            <td><?php echo _dt($row['created_at']); ?></td>
                                            <td><?php echo (int) $row['warehouse_id']; ?></td>
                                            <td><?php echo (int) $row['to_warehouse_id']; ?></td>
                                            <td><?php echo (int) $row['item_id']; ?></td>
                                            <td><?php echo e($row['entry_type']); ?></td>
                                            <td><?php echo (float) $row['qty_change']; ?></td>
                                            <td><?php echo (float) $row['qty_after']; ?></td>
                                            <td><?php echo e($row['reason_code']); ?></td>
                                            <td><?php echo e(($row['reference_type'] ?: '-') . ' #' . ((int) $row['reference_id'])); ?></td>
                                            <td><?php echo e($row['notes']); ?></td>
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
<?php init_tail(); ?>
