<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!-- Modal Customer (inline create from project form) -->
<div class="modal fade" id="client-modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?= form_open(admin_url('clients/form_client'), ['id' => 'client-form', 'class' => 'client-form', 'autocomplete' => 'off']); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="clientModalLabel">
                    <?= _l('add_new', _l('client')); ?>
                </h4>
            </div>
            <div class="modal-body">
                <?php hooks()->do_action('before_customer_profile_company_field', null); ?>
                <?= render_input('customer_name', 'Name', ''); ?>
                <?php hooks()->do_action('after_customer_profile_company_field', null); ?>
                <?= render_input('company', 'Company Name', '', 'text', ['autofocus' => true]); ?>
                <div id="company_exists_info" class="hide"></div>
                <?php hooks()->do_action('before_customer_profile_phone_field', null); ?>
                <?= render_input('phonenumber', 'Phone Number', ''); ?>
                <?php hooks()->do_action('after_customer_profile_company_phone', null); ?>
                <?= render_input('website', 'Website', ''); ?>
                <?= render_input('customer_email', 'Email Address', '', 'email'); ?>

                <?php
                $currency_attrs = ['data-none-selected-text' => _l('system_default_string')];
                echo render_select(
                    'default_currency',
                    $currencies ?? [],
                    ['id', 'name', 'symbol'],
                    'invoice_add_edit_currency',
                    '',
                    $currency_attrs
                );
                ?>

                <?= form_hidden('customer_admins_submitted', '1'); ?>
                <div class="form-group select-placeholder">
                    <label for="customer_admins" class="control-label"><?= _l('assign_admin'); ?></label>
                    <?php
                    echo render_select(
                        'customer_admins',
                        $staff ?? [],
                        ['staffid', ['firstname', 'lastname']],
                        '',
                        '',
                        ['data-live-search' => 'true', 'data-width' => '100%'],
                        [],
                        '',
                        '',
                        false
                    );
                    ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                    data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"
                    data-loading-text="<?= _l('wait_text'); ?>"
                    autocomplete="off"
                    data-form="#client-form"><?= _l('save'); ?></button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>
