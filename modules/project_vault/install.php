<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Vault entries. One row per credential / url / note.
 * The `password` column stores the CI-encrypted secret (never plaintext).
 */
if (!$CI->db->table_exists(db_prefix() . 'project_vault_entries')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "project_vault_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(191) NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT 'credential',
  `url` varchar(500) NULL,
  `username` varchar(191) NULL,
  `password` text NULL,
  `notes` text NULL,
  `shared_with` text NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `updated_by` int(11) NULL,
  `dateadded` datetime NOT NULL,
  `datemodified` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

/**
 * Attachments per vault entry.
 */
if (!$CI->db->table_exists(db_prefix() . 'project_vault_files')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "project_vault_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `file_name` varchar(191) NOT NULL,
  `original_file_name` varchar(191) NOT NULL,
  `filetype` varchar(191) NULL,
  `staffid` int(11) NOT NULL DEFAULT 0,
  `dateadded` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

/**
 * Audit history. `field_changes` is JSON and NEVER contains plaintext secrets.
 */
if (!$CI->db->table_exists(db_prefix() . 'project_vault_history')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "project_vault_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) NULL,
  `project_id` int(11) NOT NULL,
  `entry_title` varchar(191) NULL,
  `staff_id` int(11) NOT NULL DEFAULT 0,
  `action` varchar(32) NOT NULL,
  `field_changes` text NULL,
  `dateadded` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

/**
 * Migration for installs created before the "share with members" feature:
 * add the shared_with column if it is missing.
 */
if ($CI->db->table_exists(db_prefix() . 'project_vault_entries')
    && !$CI->db->field_exists('shared_with', db_prefix() . 'project_vault_entries')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'project_vault_entries` ADD `shared_with` TEXT NULL AFTER `notes`');
}
