<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Project Webhooks
Description: Notifies an internal app via HTTP webhook when a project is created, edited, gets a member added, or changes status. Deliveries are queued and sent by cron.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('PROJECT_WEBHOOKS_MODULE_NAME', 'project_webhooks');

/**
 * Endpoint that receives the webhook POSTs.
 * TODO: replace this dummy URL with your internal app's real endpoint.
 */
if (!defined('PROJECT_WEBHOOK_URL')) {
    define('PROJECT_WEBHOOK_URL', 'https://wpvipdev.com/api/webhooks/projects');
}

/**
 * How many times a single event is retried before it is marked as failed.
 */
if (!defined('PROJECT_WEBHOOK_MAX_ATTEMPTS')) {
    define('PROJECT_WEBHOOK_MAX_ATTEMPTS', 5);
}

/**
 * How many queued events are delivered per cron run.
 */
if (!defined('PROJECT_WEBHOOK_BATCH_SIZE')) {
    define('PROJECT_WEBHOOK_BATCH_SIZE', 25);
}

/**
 * Verify the TLS certificate of the webhook endpoint.
 *
 * Keep this TRUE in production. Set it to FALSE for local development where
 * PHP's curl.cainfo may be misconfigured (e.g. Local points it at a
 * non-existent WordPress ca-bundle.crt, which makes every https:// POST fail
 * with "error setting certificate verify locations").
 */
if (!defined('PROJECT_WEBHOOK_VERIFY_SSL')) {
    define('PROJECT_WEBHOOK_VERIFY_SSL', false);
}

require_once __DIR__ . '/helpers/project_webhooks_helper.php';

/**
 * Create the queue + snapshot tables on activation.
 */
register_activation_hook(PROJECT_WEBHOOKS_MODULE_NAME, 'project_webhooks_activation_hook');

function project_webhooks_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__ . '/install.php';
}

/**
 * Project lifecycle hooks -> enqueue a webhook event.
 * All handlers are fast (a single INSERT); the HTTP POST happens later on cron.
 */
hooks()->add_action('after_add_project', 'pw_on_project_created');
hooks()->add_action('after_update_project', 'pw_on_project_updated');
hooks()->add_action('project_status_changed', 'pw_on_project_status_changed');
hooks()->add_action('after_project_staff_added_as_member', 'pw_on_member_added');

/**
 * Deliver queued webhooks. Runs after the core cron tasks finish.
 */
register_cron_task('pw_process_queue');
