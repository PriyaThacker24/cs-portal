<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php if (isset($client)) { ?>
<h4 class="customer-profile-group-heading">
    <?= _l('client_add_edit_profile'); ?>
</h4>
<?php } ?>

<div class="row">
    <?= form_open($this->uri->uri_string(), ['class' => 'client-form', 'autocomplete' => 'off']); ?>
    <div class="additional"></div>
    <div class="col-md-12">
        <div class="horizontal-scrollable-tabs panel-full-width-tabs">
            <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
            <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
            <div class="horizontal-tabs">
                <ul class="nav nav-tabs customer-profile-tabs nav-tabs-horizontal" role="tablist">
                    <li role="presentation"
                        class="<?= ! $this->input->get('tab') ? 'active' : ''; ?>">
                        <a href="#contact_info" aria-controls="contact_info" role="tab" data-toggle="tab">
                            Basic Information
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#address_tab" aria-controls="address_tab" role="tab" data-toggle="tab">
                            Address
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#billing_tab" aria-controls="billing_tab" role="tab" data-toggle="tab">
                            Billing
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#login_account_tab" aria-controls="login_account_tab" role="tab" data-toggle="tab">
                            Login Account
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="tab-content mtop15">
            <?php hooks()->do_action('after_custom_profile_tab_content', $client ?? false); ?>
            <?php if ($customer_custom_fields) { ?>
            <div role="tabpanel"
                class="tab-pane<?= $this->input->get('tab') == 'custom_fields' ? ' active' : ''; ?>"
                id="custom_fields">
                <div class="row">
                    <div class="col-md-8">
                        <?php $rel_id = (isset($client) ? $client->userid : false); ?>
                        <?= render_custom_fields('customers', $rel_id); ?>
                    </div>
                </div>
            </div>
            <?php } ?>
            <div role="tabpanel"
                class="tab-pane<?= ! $this->input->get('tab') ? ' active' : ''; ?>"
                id="contact_info">
                <div class="row">
                    <div class="col-md-12<?= isset($client) && (! is_empty_customer_company($client->userid) && total_rows(db_prefix() . 'contacts', ['userid' => $client->userid, 'is_primary' => 1]) > 0) ? '' : ' hide'; ?>"
                        id="client-show-primary-contact-wrapper">
                        <div class="checkbox checkbox-info mbot20 no-mtop">
                            <input type="checkbox" name="show_primary_contact"
                                <?= isset($client) && $client->show_primary_contact == 1 ? 'checked' : ''; ?>
                            value="1" id="show_primary_contact">
                            <label
                                for="show_primary_contact"><?= _l('show_primary_contact', _l('invoices') . ', ' . _l('estimates') . ', ' . _l('payments') . ', ' . _l('credit_notes')); ?></label>
                        </div>
                    </div>
                    <div
                        class="col-md-12">
                        <?php // Basic Information tab fields for both new and existing customers ?>
                        <?php
                        $primary_name  = '';
                        $primary_email = '';
                        if (isset($client)) {
                            // Prefer the primary contact if it exists
                            $primary_id = get_primary_contact_user_id($client->userid);
                            $primary_contact = null;

                            if ($primary_id) {
                                $primary_contact = $this->clients_model->get_contact($primary_id);
                            }

                            // Fallback: if there is no primary contact yet, use the first active contact
                            if (!$primary_contact) {
                                $contacts = $this->clients_model->get_contacts($client->userid);
                                if (!empty($contacts)) {
                                    // get_contacts returns an array of arrays
                                    $primary_contact = (object) $contacts[0];
                                }
                            }

                            if ($primary_contact) {
                                $primary_name  = trim(($primary_contact->firstname ?? '') . ' ' . ($primary_contact->lastname ?? ''));
                                $primary_email = $primary_contact->email ?? '';
                            }
                        }
                        ?>
                        <?php echo render_input('customer_name', 'Name', $primary_name); ?>
                        <?php $value = (isset($client) ? $client->company : ''); ?>
                        <?php $attrs = (isset($client) ? [] : ['autofocus' => true]); ?>
                        <?= render_input('company', 'Company Name', $value, 'text', $attrs); ?>
                        <div id="company_exists_info" class="hide"></div>
                        <?php $value = (isset($client) ? $client->phonenumber : ''); ?>
                        <?= render_input('phonenumber', 'Phone Number', $value); ?>
                        <?php if (isset($client) && !empty($client->website)) { ?>
                        <div class="form-group">
                            <label for="website">Website</label>
                            <div class="input-group">
                                <input type="text" name="website" id="website"
                                    value="<?= e($client->website); ?>"
                                    class="form-control">
                                <span class="input-group-btn">
                                    <a href="<?= e(maybe_add_http($client->website)); ?>"
                                        class="btn btn-default" target="_blank" tabindex="-1">
                                        <i class="fa fa-globe"></i></a>
                                </span>
                            </div>
                        </div>
                        <?php } else { ?>
                        <?php $value = (isset($client) ? $client->website : ''); ?>
                        <?= render_input('website', 'Website', $value); ?>
                        <?php } ?>
                        <?php echo render_input('customer_email', 'Email Address', $primary_email, 'email'); ?>
                        
                        <?php // Assign Admin field ?>
                        <div class="form-group select-placeholder">
                            <label for="customer_admins" class="control-label"><?= _l('assign_admin'); ?></label>
                            <?php
                            $selected = [];
                            if (isset($client) && isset($customer_admins)) {
                                foreach ($customer_admins as $c_admin) {
                                    array_push($selected, $c_admin['staff_id']);
                                }
                            }
                            echo render_select('customer_admins[]', $staff ?? [], ['staffid', ['firstname', 'lastname']], '', $selected, ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="address_tab">
                <div class="row">
                    <div class="col-md-12">
                        <?php $countries = get_all_countries();
                        if (isset($client)) {
                            $selectedCountry = $client->country ?? get_option('customer_default_country');
                        } else {
                            $customer_default_country = get_option('customer_default_country');
                            $selectedCountry = $customer_default_country;
                        }
                        ?>
                        <?= render_textarea('address', 'Address Line 1', isset($client) ? ($client->address ?? '') : ''); ?>
                        <?= render_input('address_line_2', 'Address Line 2', isset($client) ? ($client->address_line_2 ?? '') : ''); ?>
                        <?= render_input('city', 'City', isset($client) ? ($client->city ?? '') : ''); ?>
                        <?= render_input('state', 'State', isset($client) ? ($client->state ?? '') : ''); ?>
                        <?php echo render_select('country', $countries, ['country_id', ['short_name']], 'Country', $selectedCountry, ['data-none-selected-text' => _l('dropdown_non_selected_tex')]); ?>
                        <?= render_input('zip', 'Postal Code', isset($client) ? ($client->zip ?? '') : ''); ?>
                        <?= render_textarea('formatted_address', 'Formatted Address', '', ['readonly' => true]); ?>
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="billing_tab">
                <div class="row">
                    <div class="col-md-12">
                        <h4 class="tw-font-semibold tw-text-base tw-text-neutral-700 tw-flex tw-justify-between tw-items-center tw-mt-0 tw-mb-6">
                            Billing Address
                            <a href="#" class="billing-copy-from-address tw-text-sm tw-text-neutral-500 hover:tw-text-neutral-700 active:tw-text-neutral-700">
                                Copy from Address
                            </a>
                        </h4>
                        <?php $countries = get_all_countries();
                        $selected = isset($client) ? ($client->billing_country ?? '') : ''; ?>
                        <?= render_textarea('billing_street', 'Address Line 1', isset($client) ? ($client->billing_street ?? '') : ''); ?>
                        <?= render_input('billing_street_2', 'Address Line 2', isset($client) ? ($client->billing_street_2 ?? '') : ''); ?>
                        <?= render_input('billing_city', 'City', isset($client) ? ($client->billing_city ?? '') : ''); ?>
                        <?= render_input('billing_state', 'State', isset($client) ? ($client->billing_state ?? '') : ''); ?>
                        <?php echo render_select('billing_country', $countries, ['country_id', ['short_name']], 'Country', $selected, ['data-none-selected-text' => _l('dropdown_non_selected_tex')]); ?>
                        <?= render_input('billing_zip', 'Postal Code', isset($client) ? ($client->billing_zip ?? '') : ''); ?>
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="login_account_tab">
                <div class="row">
                    <div class="col-md-12">
                        <?php // Password field for all customers ?>
                        <?php 
                        $password_value = '';
                        if (isset($client)) {
                            // Get password from primary contact (passwords are hashed, so we show empty but indicate if one exists)
                            $primary_id = get_primary_contact_user_id($client->userid);
                            if ($primary_id) {
                                $primary_contact = $this->clients_model->get_contact($primary_id);
                                if ($primary_contact && !empty($primary_contact->password)) {
                                    // Password exists but is hashed, so we leave it empty
                                    // User can set a new password if needed
                                    $password_value = '';
                                }
                            }
                        }
                        ?>
                        <?php echo render_input('login_password', 'Password', $password_value, 'password', ['placeholder' => 'Leave blank to keep current password']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?= form_close(); ?>
</div>
<?php if (isset($client)) { ?>
<?php if (staff_can('create', 'customers') || staff_can('edit', 'customers')) { ?>
<div class="modal fade" id="customer_admins_assign" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <?= form_open(admin_url('clients/assign_admins/' . $client->userid)); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <?= _l('assign_admin'); ?>
                </h4>
            </div>
            <div class="modal-body">
                <?php
               $selected = [];

    foreach ($customer_admins as $c_admin) {
        array_push($selected, $c_admin['staff_id']);
    }
    echo render_select('customer_admins[]', $staff, ['staffid', ['firstname', 'lastname']], '', $selected, ['multiple' => true], [], '', '', false); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                    data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit"
                    class="btn btn-primary"><?= _l('submit'); ?></button>
            </div>
        </div>
        <!-- /.modal-content -->
        <?= form_close(); ?>
    </div>
    <!-- /.modal-dialog -->
</div>
<!-- /.modal -->
<?php } ?>
<?php } ?>
<?php $this->load->view('admin/clients/client_group'); ?>
<script>
    (function() {
        // Auto-generate formatted address for both new and existing customers
        function updateFormattedAddress() {
            var parts = [];
            var a1 = $('textarea[name="address"]').val();
            var a2 = $('input[name="address_line_2"]').val();
            var city = $('input[name="city"]').val();
            var state = $('input[name="state"]').val();
            var country = $('select[name="country"]').val();
            var zip = $('input[name="zip"]').val();
            
            if (a1) parts.push(a1);
            if (a2) parts.push(a2);
            if (city) parts.push(city);
            if (state) parts.push(state);
            if (zip) parts.push(zip);
            if (country) {
                var countryText = $('select[name="country"] option:selected').text();
                if (countryText) parts.push(countryText);
            }
            
            $('textarea[name="formatted_address"]').val(parts.join(', '));
        }

        $(function() {
            // Update formatted address on input changes
            $('body').on('input change', 'textarea[name="address"], input[name="address_line_2"], input[name="city"], input[name="state"], select[name="country"], input[name="zip"]', updateFormattedAddress);
            updateFormattedAddress();

            // Copy from Address to Billing
            $('.billing-copy-from-address').on('click', function(e) {
                e.preventDefault();
                $('textarea[name="billing_street"]').val($('textarea[name="address"]').val());
                $('input[name="billing_street_2"]').val($('input[name="address_line_2"]').val());
                $('input[name="billing_city"]').val($('input[name="city"]').val());
                $('input[name="billing_state"]').val($('input[name="state"]').val());
                $('input[name="billing_zip"]').val($('input[name="zip"]').val());
                $('select[name="billing_country"]').selectpicker('val', $('select[name="country"]').selectpicker('val'));
                $('select[name="billing_country"]').selectpicker('refresh');
            });
        });
    })();
</script>