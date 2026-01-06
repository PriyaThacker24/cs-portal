<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!-- Modal Customer -->
<div class="modal fade" id="client-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <?= form_open(admin_url('clients/form_client'), ['id' => 'client-form', 'class' => 'client-form', 'autocomplete' => 'off']); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <?= _l('add_new', _l('client')); ?>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <?php hooks()->do_action('before_customer_profile_company_field', null); ?>
                        <?= render_input('company', 'client_company', '', 'text', ['autofocus' => true]); ?>
                        <div id="company_exists_info" class="hide"></div>
                        <?php hooks()->do_action('after_customer_profile_company_field', null); ?>
                        <?php if (get_option('company_requires_vat_number_field') == 1) {
                            echo render_input('vat', 'client_vat_number', '');
                        } ?>
                        <?php hooks()->do_action('before_customer_profile_phone_field', null); ?>
                        <?= render_input('phonenumber', 'client_phonenumber', ''); ?>
                        <?php hooks()->do_action('after_customer_profile_company_phone', null); ?>
                        <?= render_input('website', 'client_website', ''); ?>
                        
                        <?php
                        $selected = [];
                        if (is_admin() || get_option('staff_members_create_inline_customer_groups') == '1') {
                            echo render_select_with_input_group('groups_in[]', $groups, ['id', 'name'], 'customer_groups', $selected, '<div class="input-group-btn"><a href="#" class="btn btn-default" data-toggle="modal" data-target="#customer_group_modal"><i class="fa fa-plus"></i></a></div>', ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
                        } else {
                            echo render_select('groups_in[]', $groups, ['id', 'name'], 'customer_groups', $selected, ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
                        }
                        ?>
                        
                        <div class="row">
                            <div class="col-md-<?= ! is_language_disabled() ? 6 : 12; ?>">
                                <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1"
                                    data-toggle="tooltip"
                                    data-title="<?= _l('customer_currency_change_notice'); ?>"></i>
                                <?php
                                $s_attrs  = ['data-none-selected-text' => _l('system_default_string')];
                                $selected = '';
                                echo render_select('default_currency', $currencies, ['id', 'name', 'symbol'], 'invoice_add_edit_currency', $selected, $s_attrs);
                                ?>
                            </div>
                            <?php if (! is_language_disabled()) { ?>
                            <div class="col-md-6">
                                <div class="form-group select-placeholder">
                                    <label for="default_language"
                                        class="control-label"><?= _l('localization_default_language'); ?>
                                    </label>
                                    <select name="default_language" id="default_language"
                                        class="form-control selectpicker"
                                        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                        <option value="">
                                            <?= _l('system_default_string'); ?>
                                        </option>
                                        <?php 
                                        $CI =& get_instance();
                                        $availableLanguages = $CI->app->get_available_languages();
                                        foreach ($availableLanguages as $availableLanguage) { ?>
                                        <option value="<?= e($availableLanguage); ?>">
                                            <?= e(ucfirst($availableLanguage)); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <?php } ?>
                        </div>

                        <hr />

                        <?= render_textarea('address', 'client_address', ''); ?>
                        <?= render_input('city', 'client_city', ''); ?>
                        <?= render_input('state', 'client_state', ''); ?>
                        <?= render_input('zip', 'client_postal_code', ''); ?>
                        <?php 
                        $countries = get_all_countries();
                        $customer_default_country = get_option('customer_default_country');
                        $selected = $customer_default_country;
                        echo render_select('country', $countries, ['country_id', ['short_name']], 'clients_country', $selected, ['data-none-selected-text' => _l('dropdown_non_selected_tex')]);
                        ?>
                        
                        <?php $rel_id = false; ?>
                        <?= render_custom_fields('customers', $rel_id); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                    data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"
                    data-loading-text="<?= _l('wait_text'); ?>"
                    autocomplete="off"
                    data-form="#client-form"><?= _l('submit'); ?></button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

