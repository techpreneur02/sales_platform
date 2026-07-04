<div class="container">
    <?php $csrfName = $this->security->get_csrf_token_name(); ?>
    <?php $csrfHash = $this->security->get_csrf_hash(); ?>
    <h3 class="tw-mt-4 tw-mb-4">Company Wallet</h3>

    <div class="panel_s">
        <div class="panel-body">
            <div class="row">
                <div class="col-md-4">
                    <h4>Master Balance</h4>
                    <p class="h3"><?php echo app_format_money((float) $wallet['balance'], get_base_currency()); ?></p>
                </div>
                <div class="col-md-4">
                    <h4>Allocated To Staff</h4>
                    <p class="h3"><?php echo app_format_money((float) $master_allocated, get_base_currency()); ?></p>
                </div>
                <div class="col-md-4">
                    <h4>Available Pool</h4>
                    <p class="h3"><?php echo app_format_money((float) $master_available, get_base_currency()); ?></p>
                </div>
            </div>
            <p>Status: <?php echo e(ucfirst($wallet['status'])); ?></p>
            <a class="btn btn-default" href="<?php echo site_url('omnipos/wallet/export-ledger'); ?>">Export Ledger CSV</a>
        </div>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <h4>Programmatic Top-Up Requester</h4>
            <form method="post" action="<?php echo site_url('omnipos/wallet/request-topup'); ?>">
                <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                <div class="row">
                    <div class="col-md-4">
                        <label>Top-up Amount</label>
                        <input type="number" min="0.01" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">Generate Top-up Invoice</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <h4>Staff Sub-Account Builder</h4>
            <form method="post" action="<?php echo site_url('omnipos/wallet/create-staff'); ?>">
                <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                <div class="row">
                    <div class="col-md-3">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Title</label>
                        <input type="text" name="job_title" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label>Employee ID</label>
                        <input type="text" name="employee_code" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Contact ID</label>
                        <input type="number" name="contact_id" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-1">
                        <label>Limit</label>
                        <input type="number" name="spending_limit" class="form-control" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-1">
                        <label>Daily</label>
                        <input type="number" name="daily_limit" class="form-control" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-1">
                        <label>PIN</label>
                        <input type="password" name="pin" class="form-control" maxlength="4" required>
                    </div>
                </div>
                <div class="tw-mt-2">
                    <button type="submit" class="btn btn-success">Create Staff Wallet</button>
                </div>
            </form>
        </div>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <h4>Staff Wallet Accounts</h4>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Title</th>
                            <th>Employee ID</th>
                            <th>Barcode</th>
                            <th>Remaining</th>
                            <th>Daily Limit</th>
                            <th>Daily Spent</th>
                            <th>Status</th>
                            <th>Tools</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_accounts as $row) { ?>
                            <tr>
                                <td><?php echo (int) $row['id']; ?></td>
                                <td><?php echo e($row['full_name']); ?></td>
                                <td><?php echo e($row['job_title']); ?></td>
                                <td><?php echo e($row['employee_code']); ?></td>
                                <td><?php echo e($row['barcode']); ?></td>
                                <td><?php echo app_format_money((float) $row['remaining_limit'], get_base_currency()); ?></td>
                                <td><?php echo app_format_money((float) $row['daily_limit'], get_base_currency()); ?></td>
                                <td><?php echo app_format_money((float) $row['daily_spent'], get_base_currency()); ?></td>
                                <td><?php echo e(ucfirst($row['status'])); ?></td>
                                <td>
                                    <a class="btn btn-xs btn-default" href="<?php echo site_url('omnipos/wallet/code/' . (int) $row['id']); ?>">QR</a>

                                    <form method="post" action="<?php echo site_url('omnipos/wallet/toggle/' . (int) $row['id']); ?>" style="display:inline-block;">
                                        <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                                        <button type="submit" class="btn btn-xs btn-warning"><?php echo $row['status'] === 'active' ? 'Freeze' : 'Unfreeze'; ?></button>
                                    </form>

                                    <form method="post" action="<?php echo site_url('omnipos/wallet/pin/' . (int) $row['id']); ?>" style="display:inline-block;">
                                        <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                                        <input type="password" name="pin" maxlength="4" placeholder="New PIN" style="width:82px;">
                                        <button type="submit" class="btn btn-xs btn-info">Set PIN</button>
                                    </form>

                                    <form method="post" action="<?php echo site_url('omnipos/wallet/transfer-in/' . (int) $row['id']); ?>" style="display:inline-block;">
                                        <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                                        <input type="number" name="amount" min="0.01" step="0.01" placeholder="Amount" style="width:90px;">
                                        <button type="submit" class="btn btn-xs btn-success">Transfer In</button>
                                    </form>

                                    <form method="post" action="<?php echo site_url('omnipos/wallet/transfer-out/' . (int) $row['id']); ?>" style="display:inline-block;">
                                        <input type="hidden" name="<?php echo e($csrfName); ?>" value="<?php echo e($csrfHash); ?>">
                                        <input type="number" name="amount" min="0.01" step="0.01" placeholder="Amount" style="width:90px;">
                                        <button type="submit" class="btn btn-xs btn-danger">Transfer Out</button>
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
            <h4>Master Wallet Ledger</h4>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Staff Wallet</th>
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
                                <td><?php echo (int) $entry['staff_wallet_id']; ?></td>
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
