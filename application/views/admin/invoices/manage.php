<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div id="vueApp">
			<div class="row">
				<?php include_once(APPPATH.'views/admin/invoices/filter_params.php'); ?>
				<?php $this->load->view('admin/invoices/list_template'); ?>
			</div>
		</div>
	</div>
</div>
<?php $this->load->view('admin/includes/modals/sales_attach_file'); ?>
<div id="modal-wrapper"></div>
<script>var hidden_columns = [2,6,7,8];</script>
<style>
/* Invoice list only: search bar to the top-left, pagination to the top-right */
#invoices_wrapper .dataTables_filter {
	float: left;
	text-align: left;
}
#invoices_wrapper .dataTables_filter.moved-to-top-left {
	margin-right: 15px;
}
#invoices_wrapper .col-md-5 .dataTables_paginate {
	float: right;
	margin-top: 0;
}
</style>
<?php init_tail(); ?>
<script>
$(function(){
	init_invoice();

	// Reposition the invoice list toolbar: move the search box to the left and
	// bring the pagination up to where the search box used to sit (top-right).
	// Scoped to #invoices_wrapper so other datatables are unaffected.
	(function relocate_invoice_toolbar(attempts){
		var $wrap = $('#invoices_wrapper');
		var $filter = $wrap.find('.dataTables_filter');
		var $paginate = $wrap.find('.dataTables_paginate');
		var $topRow = $wrap.children('.row').eq(1);
		var $topLeft = $topRow.children('.col-md-7');
		var $topRight = $topRow.children('.col-md-5');

		if (!$wrap.length || !$filter.length || !$paginate.length || !$topLeft.length || !$topRight.length) {
			if (attempts > 0) {
				setTimeout(function(){ relocate_invoice_toolbar(attempts - 1); }, 150);
			}
			return;
		}

		// Search box -> far left of the top row
		$filter.addClass('moved-to-top-left');
		$topLeft.prepend($filter);

		// Pagination -> top-right (the search box's original column)
		$topRight.append($paginate);
	})(20);
});
</script>
</body>
</html>