<?php
require_once(dirname(__FILE__).'/../boot.php');

if(isAjax() && count($_POST)) {
	if ($_POST['notice'] == SG_NOTICE_EXECUTION_TIMEOUT) {
		SGConfig::set('SG_EXCEPTION_TIMEOUT_ERROR', '0');
	}
}
