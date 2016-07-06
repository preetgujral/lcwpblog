<?php
    require_once(dirname(__FILE__).'/../boot.php');
    require_once(SG_BACKUP_PATH.'SGBackup.php');
    if(isAjax())  {
        $runningAction = getRunningActions();
        if($runningAction) {
            die(json_encode($runningAction));
        }
        die('0');
    }
