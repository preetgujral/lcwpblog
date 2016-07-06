<?php
    require_once(dirname(__FILE__).'/../boot.php');
    require_once(SG_BACKUP_PATH.'SGBackup.php');
    if(isAjax()) {
        $error = array();
        try {
            @unlink(SG_BACKUP_DIRECTORY.'sg_backup.state');
            SGConfig::set('SG_RUNNING_ACTION', 0, true);
            $key = sha1(microtime(true));
            SGConfig::set('SG_BACKUP_CURRENT_KEY', $key, true);
            die('{"success":1}');
        }
        catch(SGException $exception) {
            array_push($error, $exception->getMessage());
            die(json_encode($error));
        }
    }
