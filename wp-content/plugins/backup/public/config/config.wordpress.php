<?php

require_once(dirname(__FILE__).'/config.php');
//Url
define('SG_PLUGIN_NAME', 'backup');
define('SG_PUBLIC_URL', plugins_url().'/'.SG_PLUGIN_NAME.'/public/');
define('SG_PUBLIC_AJAX_URL', SG_PUBLIC_URL.'ajax/');
define('SG_CLOUD_REDIRECT_URL', admin_url('admin.php?page=backup_guard_cloud'));
define('SG_REVIEW_URL', 'https://wordpress.org/support/view/plugin-reviews/backup?filter=5');

//Backup Guard Site URL
define('SG_BACKUP_SITE_URL','https://backup-guard.com/products/backup-wordpress');

define('SG_BACKUP_UPGRADE_URL','https://backup-guard.com/products/backup-wordpress/0');

//Backup Guard Support URL
define('SG_BACKUP_SUPPORT_URL','https://backup-guard.com/tickets/index.php?faq=0');
