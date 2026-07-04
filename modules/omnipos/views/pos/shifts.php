<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4 class="no-margin">Shift History</h4>
                <hr>
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
<?php init_tail(); ?>
