<?php

global $wp_version, $wpdb;
define('SG_ENV_WORDPRESS', 'Wordpress');
define('SG_ENV_MAGENTO', 'Magento');
define('SG_ENV_VERSION', $wp_version);
define('SG_ENV_ADAPTER', SG_ENV_WORDPRESS);
define('SG_ENV_DB_PREFIX', $wpdb->prefix);

require_once(dirname(__FILE__).'/config.php');

define('SG_ENV_CORE_TABLE', SG_WORDPRESS_CORE_TABLE);
//Database
define('SG_DB_ADAPTER', SG_ENV_ADAPTER);
define('SG_DB_NAME', $wpdb->dbname);
define('SG_BACKUP_DATABASE_EXCLUDE', SG_ACTION_TABLE_NAME.','.SG_CONFIG_TABLE_NAME.','.SG_SCHEDULE_TABLE_NAME);

//Mail
define('SG_MAIL_TEMPLATES_PATH', SG_APP_PATH.'../public/templates/');
define('SG_MAIL_BACKUP_TEMPLATE', 'mail_backup.php');
define('SG_MAIL_RESTORE_TEMPLATE', 'mail_restore.php');

//Backup
$wpContent = basename(WP_CONTENT_DIR);
$wpPlugins = basename(WP_PLUGIN_DIR);
$upload_dir = wp_upload_dir();
$wpUploads = basename($upload_dir['basedir']);
$wpThemes = basename(get_theme_root());

define('SG_PING_FILE_PATH', $upload_dir['basedir'].'/backup-guard/ping.json');

//Symlink download
define('SG_SYMLINK_PATH', $upload_dir['basedir'].'/sg_symlinks/');
define('SG_SYMLINK_URL', $upload_dir['baseurl'].'/sg_symlinks/');

define('SG_APP_ROOT_DIRECTORY', ABSPATH); //Wordpress Define
define('SG_BACKUP_FILE_PATHS_EXCLUDE', $wpContent.'/'.$wpPlugins.'/backup/,'.$wpContent.'/'.$wpPlugins.'/backup-guard-pro/,'.$wpContent.'/'.$wpPlugins.'/backup-guard-silver/,'.$wpContent.'/'.$wpPlugins.'/backup-guard-gold/,'.$wpContent.'/'.$wpPlugins.'/backup-guard-platinum/,'.$wpContent.'/'.$wpUploads.'/backup-guard/,'.$wpContent.'/'.$wpUploads.'/sg_symlinks/');
define('SG_BACKUP_DIRECTORY', $upload_dir['basedir'].'/backup-guard/'); //backups will be stored here

//Storage
define('SG_STORAGE_UPLOAD_CRON', '');

define('SG_BACKUP_FILE_PATHS', $wpContent.','.$wpContent.'/'.$wpPlugins.','.$wpContent.'/'.$wpThemes.','.$wpContent.'/'.$wpUploads);
