<div class="container">
    <h3 class="tw-mt-4 tw-mb-3">Staff Wallet QR</h3>

    <div class="panel_s">
        <div class="panel-body text-center">
            <h4><?php echo e($staff['full_name']); ?> (<?php echo e($staff['employee_code']); ?>)</h4>
            <p>Barcode Token: <strong><?php echo e($staff['barcode']); ?></strong></p>
            <img src="<?php echo e($qr_url); ?>" alt="Staff Wallet QR" style="max-width:260px; width:100%; height:auto;">
            <div class="tw-mt-3">
                <a class="btn btn-default" href="<?php echo e($qr_url); ?>" download="wallet-staff-<?php echo (int) $staff['id']; ?>.png">Download QR</a>
                <a class="btn btn-primary" href="<?php echo site_url('omnipos/wallet'); ?>">Back to Wallet</a>
            </div>
        </div>
    </div>
</div>
