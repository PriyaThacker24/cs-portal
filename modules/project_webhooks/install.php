<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Queue of webhook events waiting to be (or already) delivered.
 */
if (!$CI->db->table_exists(db_prefix() . 'project_webhook_queue')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "project_webhook_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event` varchar(64) NOT NULL,
  `project_id` int(11) NOT NULL DEFAULT 0,
  `payload` longtext NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_attempt_at` datetime NULL,
  `response_code` int(11) NULL,
  `response_body` text NULL,
  `created_at` datetime NOT NULL,
  `sent_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

/**
 * Last-seen set of member staff ids per project, used to detect additions.
 */
if (!$CI->db->table_exists(db_prefix() . 'project_webhook_member_snapshot')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "project_webhook_member_snapshot` (
  `project_id` int(11) NOT NULL,
  `staff_ids` text NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

/**
 * Website URL is stored as a real column on tblprojects (shown on the project
 * form below the customer field), not as a custom field.
 */
if (!in_array('website_url', $CI->db->list_fields(db_prefix() . 'projects'))) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'projects` ADD `website_url` VARCHAR(255) NULL AFTER `clientid`;');
}
