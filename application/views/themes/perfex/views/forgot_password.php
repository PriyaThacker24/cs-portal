<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="mtop40">
    <div class="col-md-4 col-md-offset-4 text-center forgot-password-heading">
        <h1 class="tw-font-semibold mbot20">
            <?= _l('customer_forgot_password_heading'); ?>
        </h1>
    </div>
    <div class="col-md-4 col-md-offset-4">
        <div class="panel_s">
            <div class="panel-body">
                <?= form_open($this->uri->uri_string(), ['id' => 'forgot-password-form']); ?>
                <?php if ($this->session->flashdata('message-danger')) { ?>
                <div class="alert alert-danger">
                    <?= $this->session->flashdata('message-danger'); ?>
                </div>
                <?php } ?>
                <div class="form-group">
                    <label for="email" class="control-label">
                        <?= _l('customer_forgot_password_email'); ?>
                    </label>
                    <input type="email" id="email" name="email" class="form-control<?= form_error('email') ? ' is-invalid' : ''; ?>" value="<?= set_value('email'); ?>">
                    <?= form_error('email', '<div class="text-danger tw-mt-1 tw-text-sm">', '</div>'); ?>
                </div>
                <div class="form-group">
                    <button type="submit"
                        class="btn btn-primary btn-block"><?= _l('customer_forgot_password_submit'); ?></button>
                </div>
                <?= form_close(); ?>
            </div>
        </div>
    </div>
</div>