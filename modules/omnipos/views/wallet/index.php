<div class="container">
    <h3 class="tw-mt-4 tw-mb-4">Company Wallet</h3>

    <div class="panel_s">
        <div class="panel-body">
            <h4>Master Balance: <?php echo app_format_money((float) $wallet['balance'], get_base_currency()); ?></h4>
            <p>Status: <?php echo e(ucfirst($wallet['status'])); ?></p>
        </div>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <h4>Staff Wallet Accounts</h4>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Contact ID</th>
                            <th>Barcode</th>
                            <th>Spending Limit</th>
                            <th>Remaining Limit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_accounts as $row) { ?>
                            <tr>
                                <td><?php echo (int) $row['id']; ?></td>
                                <td><?php echo (int) $row['contact_id']; ?></td>
                                <td><?php echo e($row['barcode']); ?></td>
                                <td><?php echo app_format_money((float) $row['spending_limit'], get_base_currency()); ?></td>
                                <td><?php echo app_format_money((float) $row['remaining_limit'], get_base_currency()); ?></td>
                                <td><?php echo e(ucfirst($row['status'])); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <h4>Wallet Ledger</h4>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ledger as $entry) { ?>
                            <tr>
                                <td><?php echo _dt($entry['created_at']); ?></td>
                                <td><?php echo e($entry['entry_type']); ?></td>
                                <td><?php echo app_format_money((float) $entry['amount'], get_base_currency()); ?></td>
                                <td><?php echo e(($entry['reference_type'] ?: '-') . ' #' . ((int) $entry['reference_id'])); ?></td>
                                <td><?php echo e($entry['notes']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
