<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="tw-flex tw-flex-row tw-flex-nowrap tw-gap-2 tw-overflow-x-auto tw-pb-1">
    <?php foreach (tasks_summary_data(($rel_id ?? null), ($rel_type ?? null)) as $summary) { ?>
    <button type="button"
        data-status-id="<?= e($summary['status_id']); ?>"
        @click="extra.tasksRules = <?= app\services\utilities\Js::from($tasks_table->findRule('status')->setValue([$summary['status_id']])); ?>"
        style="min-width:165px;max-width:165px;flex-shrink:0;"
        class="tw-bg-white tw-border tw-border-solid tw-border-neutral-300/80 tw-shadow-sm tw-py-2 tw-px-3.5 tw-rounded-lg tw-text-sm hover:tw-bg-neutral-100 tw-text-neutral-600 hover:tw-text-neutral-600 focus:tw-text-neutral-600 text-left">
        <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1">
            <?= e($summary['total_tasks']); ?>
        </span>
        <span
            style="color:<?= e($summary['color']); ?>">
            <?= e($summary['name']); ?>
        </span>
        <span class="tw-text-sm tw-text-neutral-800 tw-block">
            <span
                class="tw-text-neutral-500"><?= _l('home_my_tasks'); ?>:</span>
            <?= e($summary['total_my_tasks']); ?>
        </span>
    </button>
    <?php } ?>
</div>