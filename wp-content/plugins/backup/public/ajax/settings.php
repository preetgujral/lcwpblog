<?php
require_once(dirname(__FILE__).'/../boot.php');
$error = array();
$success = array('success'=>1);

if(isAjax() && isset($_POST['cancel']))
{
    SGConfig::set('SG_AMOUNT_OF_BACKUPS_TO_KEEP', SG_NUMBER_OF_BACKUPS_TO_KEEP);
    SGConfig::set('SG_NOTIFICATIONS_ENABLED', '0');
    SGConfig::set('SG_NOTIFICATIONS_EMAIL_ADDRESS', '');
    SGConfig::set('SG_DELETE_BACKUP_AFTER_UPLOAD', '0');
    SGConfig::set('SG_BACKUP_FILE_NAME_PREFIX', '');
    die(json_encode($success));
}

if(isAjax() && count($_POST))
{
    $amountOfBackupsToKeep = SG_NUMBER_OF_BACKUPS_TO_KEEP;
    if (isset($_POST['amount-of-backups-to-keep']) && (int)$_POST['amount-of-backups-to-keep']) {
        $amountOfBackupsToKeep = $_POST['amount-of-backups-to-keep'];
    }
    SGConfig::set('SG_AMOUNT_OF_BACKUPS_TO_KEEP', $amountOfBackupsToKeep);

    SGConfig::set('SG_NOTIFICATIONS_ENABLED', '0');
    $email = '';
    if(isset($_POST['sgIsEmailNotification']))
    {
        $email = @$_POST['sgUserEmail'];
        if (empty($email)) {
            array_push($error, _t('Email is required.', true));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            array_push($error, _t('Invalid email address.', true));
        }

        SGConfig::set('SG_NOTIFICATIONS_ENABLED', '1');
    }
    $ajaxInterval = (int)$_POST['ajaxInterval'];

    if(count($error))
    {
        die(json_decode($error));
    }

    SGConfig::set('SG_DELETE_BACKUP_AFTER_UPLOAD', '0');
    if (isset($_POST['delete-backup-after-upload'])) {
        SGConfig::set('SG_DELETE_BACKUP_AFTER_UPLOAD', '1');
    }

    $backupFileName = 'sg_backup_';
    if (isset($_POST['backup-file-name'])) {
        $backupFileName = $_POST['backup-file-name'];
    }

    SGConfig::set('SG_BACKUP_FILE_NAME_PREFIX', $backupFileName);
    SGConfig::set('SG_AJAX_REQUEST_FREQUENCY', $ajaxInterval);
    SGConfig::set('SG_NOTIFICATIONS_EMAIL_ADDRESS', $email);
    die(json_encode($success));
}
