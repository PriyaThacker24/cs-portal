<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div id="vueApp">
            <div class="row">
                <div class="col-md-12">
                    <div class="tw-mb-2 sm:tw-mb-4">
                        <div class="_buttons">
                            <div class="display-block pull-right tw-space-x-0 sm:tw-space-x-1.5">
                                <app-filters
                                    id="<?php echo $payments_table->id(); ?>"
                                    view="<?php echo $payments_table->viewName(); ?>"
                                    :rules="extra.paymentsRules || []"
                                    :saved-filters="<?php echo $payments_table->filtersJs(); ?>"
                                    :available-rules="<?php echo $payments_table->rulesJs(); ?>">
                                </app-filters>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body panel-table-full">
                            <?php $this->load->view('admin/payments/table_html'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-12 text-right">
                                    <strong><?php echo _l('total_amount'); ?>: $</strong>
                                    <span id="payments_total_amount">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function() {
    initDataTable('.table-payments', admin_url + 'payments/table', undefined, undefined, 'undefined',
        <?php echo hooks()->apply_filters('payments_table_default_order', json_encode([0, 'desc'])); ?>);
    
    // Update total amount when table is redrawn (filters, pagination, search, etc.)
    $('.table-payments').on('draw.dt', function() {
        var paymentsTable = $(this).DataTable();
        if (paymentsTable && paymentsTable.ajax) {
            try {
                var jsonData = paymentsTable.ajax.json();
                if (jsonData && typeof jsonData.total_amount !== 'undefined') {
                    var totalAmount = parseFloat(jsonData.total_amount) || 0;
                    $('#payments_total_amount').html(totalAmount.toFixed(2));
                } else {
                    $('#payments_total_amount').html('0.00');
                }
            } catch (e) {
                $('#payments_total_amount').html('0.00');
            }
        }
    });
    
    // Update on initial load after table is ready
    $('.table-payments').on('init.dt', function() {
        setTimeout(function() {
            var paymentsTable = $('.table-payments').DataTable();
            if (paymentsTable && paymentsTable.ajax) {
                try {
                    var jsonData = paymentsTable.ajax.json();
                    if (jsonData && typeof jsonData.total_amount !== 'undefined') {
                        var totalAmount = parseFloat(jsonData.total_amount) || 0;
                        $('#payments_total_amount').html(totalAmount.toFixed(2));
                    }
                } catch (e) {
                    // Table might not be ready yet
                }
            }
        }, 300);
    });
});
</script>
</body>

</html>