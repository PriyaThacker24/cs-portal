<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-4">
                        <?php echo render_date_input('activity_log_date', 'utility_activity_log_filter_by_date', '', [], [], '', 'activity-log-date'); ?>
                    </div>
                    <div class="col-md-8 text-right mtop20">
                        <a class="btn btn-danger _delete"
                            href="<?php echo admin_url('utilities/clear_activity_log'); ?>"><?php echo _l('clear_activity_log'); ?></a>
                    </div>
                </div>
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="panel-table-full">
                            <?php render_datatable([
                            _l('utility_activity_log_dt_description'),
                            _l('utility_activity_log_dt_date'),
                            _l('utility_activity_log_dt_staff'),
                            ], 'activity-log'); ?>
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
        // Override language settings for activity log table to use "activities" instead of "entries"
        function applyActivityLogLanguageOverride() {
            if ($('.table-activity-log').length > 0) {
                var table = $('.table-activity-log').DataTable();
                if (table && table.settings && table.settings().length > 0) {
                    var settings = table.settings()[0];
                    if (settings && settings.oLanguage) {
                        var originalInfo = settings.oLanguage.sInfo;
                        var originalInfoEmpty = settings.oLanguage.sInfoEmpty;
                        var originalInfoFiltered = settings.oLanguage.sInfoFiltered;
                        
                        settings.oLanguage.sInfo = originalInfo.replace(/entries/g, 'activities');
                        settings.oLanguage.sInfoEmpty = originalInfoEmpty.replace(/entries/g, 'activities');
                        settings.oLanguage.sInfoFiltered = originalInfoFiltered.replace(/entries/g, 'activities');
                    }
                    
                    // Update the info text in the DOM whenever table is drawn
                    table.on('draw.dt', function() {
                        var infoElement = $('.table-activity-log').closest('.dataTables_wrapper').find('.dataTables_info');
                        if (infoElement.length) {
                            var currentText = infoElement.text();
                            if (currentText.indexOf('entries') !== -1) {
                                infoElement.text(currentText.replace(/entries/g, 'activities'));
                            }
                        }
                    });
                    // Trigger initial update
                    table.draw(false);
                }
            }
        }
        
        // Try to apply override after a short delay to ensure table is initialized
        setTimeout(applyActivityLogLanguageOverride, 500);
    });
</script>
</body>

</html>