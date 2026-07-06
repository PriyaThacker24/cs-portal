<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $this->load->view('authentication/includes/head.php'); ?>

<body class="tw-bg-neutral-100 authentication two-factor-authentication-code">
    <div class="tw-max-w-md tw-mx-auto tw-pt-24 authentication-form-wrapper tw-relative tw-z-20">
        <div class="company-logo text-center">
            <?= get_dark_company_logo(); ?>
        </div>

        <h1 class="tw-text-2xl tw-text-neutral-800 text-center tw-font-semibold tw-mb-1">
            <?= _l('admin_two_factor_auth_heading'); ?>
        </h1>

        <h3 class="tw-text-xl tw-text-neutral-600 text-center tw-font-medium tw-mb-5 tw-mt-0">
            <?= _l('two_factor_authentication'); ?>
        </h3>

        <div
            class="tw-bg-white tw-mx-2 sm:tw-mx-6 tw-py-8 tw-px-6 sm:tw-px-8 tw-shadow-sm tw-rounded-lg tw-border tw-border-solid tw-border-neutral-600/20">

            <?= form_open($this->uri->uri_string()); ?>
            <?= validation_errors('<div class="alert alert-danger text-center">', '</div>'); ?>
            <?php $this->load->view('authentication/includes/alerts'); ?>
            <?php if (isset($type) && $type === 'app') { ?>
            <label class="control-label tw-block tw-mb-3 tw-font-medium tw-text-neutral-700">
                <?= _l('enter_two_factor_auth_code_from_mobile'); ?>
            </label>
            <?php } ?>
            <?= render_input('code', '', '', 'text', ['placeholder' => '* * * * * *']); ?>
            <p class="text-muted tw-text-sm tw-mt-1 tw-mb-4">
                <?= _l('two_factor_backup_code_login_hint'); ?>
            </p>
            <div class="form-group">
                <a
                    href="<?= admin_url('authentication'); ?>">
                    <?= _l('back_to_login'); ?>
                </a>
            </div>
            <button type="submit" class="btn btn-primary btn-block tw-font-semibold tw-py-2">
                <?= _l('confirm'); ?>
            </button>
            <?= form_close(); ?>
        </div>
    </div>
</body>

</html>