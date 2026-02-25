<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <?php
            echo form_open($this->uri->uri_string(), ['id' => 'invoice-form', 'class' => '_transaction_form invoice-form']);
            if (isset($invoice)) {
                echo form_hidden('isedit');
            }
            ?>
            <div class="col-md-12">
                <h4
                    class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-700 tw-flex tw-items-center tw-space-x-2">
                    <span>
                        <?php echo e(isset($invoice) ? format_invoice_number($invoice) : _l('create_new_invoice')); ?>
                    </span>
                    <?php echo isset($invoice) ? format_invoice_status($invoice->status) : ''; ?>
                </h4>
                <?php $this->load->view('admin/invoices/invoice_template'); ?>
            </div>
            <?php echo form_close(); ?>
            <?php $this->load->view('admin/invoice_items/item'); ?>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function() {
    validate_invoice_form();
    // Init accountacy currency symbol
    init_currency();
    // Project ajax search
    init_ajax_project_search_by_customer_id();
    // Maybe items ajax search
    init_ajax_search('items', '#item_select.ajax-search', undefined, admin_url + 'items/search');
    
    
    // Auto-select and disable sales agent when customer changes
    $('body').on('change', '.f_client_id #clientid', function() {
        var val = $(this).val();
        if (!val) {
            return;
        }
        requestGetJSON('clients/get_customer_sale_agent/' + val).done(function(response) {
            var saleAgentId = parseInt(response.sale_agent || 0);
            var $saleAgent = $('select#sale_agent');
            if (saleAgentId > 0) {
                $saleAgent.val(saleAgentId);
                $saleAgent.prop('disabled', true);
            } else {
                $saleAgent.prop('disabled', false);
            }
            if ($.fn.selectpicker && $saleAgent.hasClass('selectpicker')) {
                $saleAgent.selectpicker('refresh');
            }
        });
    });
});
</script>
</body>

</html>