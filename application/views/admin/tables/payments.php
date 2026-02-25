<?php

defined('BASEPATH') or exit('No direct script access allowed');

$this->ci->load->model('payments_model');
$this->ci->load->model('payment_modes_model');

return App_table::find('payments')
    ->outputUsing(function ($params) {
        extract($params);
        $hasPermissionDelete = staff_can('delete', 'payments');

        $aColumns = [
            db_prefix() . 'invoicepaymentrecords.id as id',
            'invoiceid',
            'paymentmode',
            'transactionid',
            get_sql_select_client_company(),
            'amount',
            db_prefix() . 'invoicepaymentrecords.amount_rupees',
            db_prefix() . 'invoicepaymentrecords.date as date',
        ];

        $sIndexColumn = 'id';
        $sTable       = db_prefix() . 'invoicepaymentrecords';

        $join = [
            'LEFT JOIN ' . db_prefix() . 'invoices ON ' . db_prefix() . 'invoices.id = ' . db_prefix() . 'invoicepaymentrecords.invoiceid',
            'LEFT JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'invoices.clientid',
            'LEFT JOIN ' . db_prefix() . 'currencies ON ' . db_prefix() . 'currencies.id = ' . db_prefix() . 'invoices.currency',
            'LEFT JOIN ' . db_prefix() . 'payment_modes ON ' . db_prefix() . 'payment_modes.id = ' . db_prefix() . 'invoicepaymentrecords.paymentmode',
        ];

        $where = [];

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        if ($clientid != '') {
            array_push($where, 'AND ' . db_prefix() . 'clients.userid=' . $this->ci->db->escape_str($clientid));
        }

        if (staff_cant('view', 'payments')) {
            $whereUser = '';
            $whereUser .= 'AND (invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE (addedfrom=' . get_staff_user_id() . ' AND addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature = "invoices" AND capability="view_own")))';
            if (get_option('allow_staff_view_invoices_assigned') == 1) {
                $whereUser .= ' OR invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE sale_agent=' . get_staff_user_id() . ')';
            }
            $whereUser .= ')';
            array_push($where, $whereUser);
        }

        $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
            'clientid',
            db_prefix() . 'currencies.name as currency_name',
            db_prefix() . 'payment_modes.name as payment_mode_name',
            db_prefix() . 'payment_modes.id as paymentmodeid',
            'paymentmethod',
            db_prefix() . 'invoicepaymentrecords.amount_rupees',
        ]);

        $output  = $result['output'];
        $rResult = $result['rResult'];

        // Calculate total amount for all filtered records (not just current page)
        $totalAmount = 0;
        try {
            // Build WHERE clause for total calculation
            $whereSql = implode(' ', $where);
            
            if (trim($whereSql) != '') {
                if (startsWith($whereSql, 'AND ')) {
                    $whereSql = substr($whereSql, 4);
                } elseif (startsWith($whereSql, 'OR ')) {
                    $whereSql = substr($whereSql, 3);
                }
                if (!startsWith($whereSql, 'WHERE ')) {
                    $whereSql = 'WHERE ' . $whereSql;
                }
            } else {
                $whereSql = '';
            }
            
            // Calculate total - sum all amounts without currency conversion
            $totalQuery = 'SELECT SUM(' . db_prefix() . 'invoicepaymentrecords.amount) as total_amount
                FROM ' . $sTable . ' 
                ' . implode(' ', $join) . '
                ' . $whereSql;
            
            // Suppress errors for this query to never break the main response
            $originalErrorReporting = error_reporting(0);
            $originalDebug = $this->ci->db->db_debug;
            $this->ci->db->db_debug = false;
            
            $queryResult = $this->ci->db->query($totalQuery);
            
            // Restore settings
            $this->ci->db->db_debug = $originalDebug;
            error_reporting($originalErrorReporting);
            
            if ($queryResult !== false && is_object($queryResult)) {
                $totalResult = $queryResult->row();
                if ($totalResult && isset($totalResult->total_amount) && $totalResult->total_amount !== null) {
                    $totalAmount = (float)$totalResult->total_amount;
                }
            }
        } catch (Throwable $e) {
            // Catch any error - never break the main response
            $totalAmount = 0;
        }
        
        $output['total_amount'] = $totalAmount;

        $this->ci->load->model('payment_modes_model');
        $payment_gateways = $this->ci->payment_modes_model->get_payment_gateways(true);

        foreach ($rResult as $aRow) {
            $row = [];

            $link = admin_url('payments/payment/' . $aRow['id']);

            $numberOutput = '<a href="' . $link . '">' . e($aRow['id']) . '</a>';

            $numberOutput .= '<div class="row-options">';
            $numberOutput .= '<a href="' . $link . '">' . _l('view') . '</a>';
            if ($hasPermissionDelete) {
                $numberOutput .= ' | <a href="' . admin_url('payments/delete/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
            }
            $numberOutput .= '</div>';

            $row[] = $numberOutput;

            $row[] = '<a href="' . admin_url('invoices/list_invoices/' . $aRow['invoiceid']) . '">' . e(format_invoice_number($aRow['invoiceid'])) . '</a>';

            $outputPaymentMode = e($aRow['payment_mode_name']);

            // Since version 1.0.1
            if (is_null($aRow['paymentmodeid'])) {
                foreach ($payment_gateways as $gateway) {
                    if ($aRow['paymentmode'] == $gateway['id']) {
                        $outputPaymentMode = e($gateway['name']);
                    }
                }
            }

            if (!empty($aRow['paymentmethod'])) {
                $outputPaymentMode .= ' - ' . e($aRow['paymentmethod']);
            }
            $row[] = $outputPaymentMode;

            $row[] = e($aRow['transactionid']);

            $row[] = '<a href="' . admin_url('clients/client/' . $aRow['clientid']) . '">' . e($aRow['company']) . '</a>';

            $row[] = e(app_format_money($aRow['amount'], $aRow['currency_name']));

            $row[] = !empty($aRow['amount_rupees']) ? e($aRow['amount_rupees']) : '-';

            $row[] = e(_d($aRow['date']));

            $row['DT_RowClass'] = 'has-row-options';

            $output['aaData'][] = $row;
        }
        return $output;
    })->setRules([
        App_table_filter::new('id', 'NumberRule')->label(_l('payments_table_number_heading')),
        App_table_filter::new('invoiceid', 'NumberRule')->label(_l('payments_table_invoicenumber_heading')),
        App_table_filter::new('amount', 'NumberRule')->label(_l('payments_table_amount_heading')),
        App_table_filter::new('date', 'DateRule')->label(_l('payments_table_date_heading')),
        App_table_filter::new('transactionid', 'TextRule')->label(_l('payment_transaction_id')),
        App_table_filter::new('paymentmode', 'MultiSelectRule')
            ->label(_l('payments_table_mode_heading'))
            ->options(function ($ci) {
                return collect($ci->payment_modes_model->get('', [], true))->map(fn ($mode) => [
                    'value' => $mode['id'],
                    'label' => $mode['name'],
                ])->all();
            }),
        App_table_filter::new('year', 'MultiSelectRule')
            ->label(_l('year'))
            ->raw(function ($value, $operator) {
                if ($operator == 'in') {
                    return "YEAR(" . db_prefix() . "invoicepaymentrecords.date) IN (" . implode(',', $value) . ")";
                } else {
                    return "YEAR(" . db_prefix() . "invoicepaymentrecords.date) NOT IN (" . implode(',', $value) . ")";
                }
            })
            ->options(function ($ci) {
                return collect($ci->payments_model->get_payments_years())->map(fn ($data) => [
                    'value' => $data['year'],
                    'label' => $data['year'],
                ])->all();
            }),
    ]);
