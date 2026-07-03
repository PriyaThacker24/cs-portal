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

    // Show + require the Wise payment link field only when the Wise payment
    // method is selected in "Allowed payment modes".
    function toggle_wise_payment_link() {
        var val = $('select[name="allowed_payment_modes[]"]').val();
        var modes = Array.isArray(val) ? val : (val ? [val] : []);
        var hasWise = modes.indexOf('wise') !== -1;
        $('#wise_payment_link_wrapper').toggleClass('hide', !hasWise);
        $('#wise_payment_link').prop('required', hasWise);
    }
    $(document).on('change', 'select[name="allowed_payment_modes[]"]', toggle_wise_payment_link);
    toggle_wise_payment_link();


    var organizationCompanyManuallySelected = false;

    function refresh_invoice_organization_company_preview(companyId) {
        var $preview = $('#invoice-organization-company-preview');
        if ($preview.length === 0) {
            return;
        }
        requestGetJSON('organization_companies/preview_info/' + (companyId || 0)).done(function(response) {
            if (response && response.html) {
                $preview.html(response.html);
            }
        });
    }

    $('body').on('change changed.bs.select', 'select[name="organization_company_id"]', function() {
        organizationCompanyManuallySelected = true;
        refresh_invoice_organization_company_preview($(this).val());
    });

    $(document).on('ajaxSuccess.invoiceOrganizationCompany', function(event, xhr, settings) {
        if (!settings.url || settings.url.indexOf('invoices/client_change_data') === -1) {
            return;
        }
        var response = xhr.responseJSON;
        if (!response || !response.organization_company_id) {
            return;
        }
        var $company = $('select[name="organization_company_id"]');
        if ($company.length === 0) {
            return;
        }
        var currentVal = $company.val();
        if (!organizationCompanyManuallySelected || !currentVal) {
            $company.selectpicker('val', response.organization_company_id);
            $company.selectpicker('refresh');
            refresh_invoice_organization_company_preview(response.organization_company_id);
        } else {
            refresh_invoice_organization_company_preview(currentVal);
        }
    });

    if ($('select[name="organization_company_id"]').length) {
        refresh_invoice_organization_company_preview($('select[name="organization_company_id"]').val());
    }

    $('#invoice-form').on('submit', function() {
        var $company = $('select[name="organization_company_id"]');
        if ($company.length) {
            var selectedCompany = $company.val();
            if (selectedCompany) {
                var $hiddenCompany = $(this).find('input[name="organization_company_id_hidden"]');
                if ($hiddenCompany.length === 0) {
                    $hiddenCompany = $('<input type="hidden" name="organization_company_id_hidden">').appendTo(this);
                }
                $hiddenCompany.val(selectedCompany);
            }
        }
    });

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