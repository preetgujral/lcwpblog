<?php

function backupGuardOutdatedBackupsCleanup($path)
{
	if (SGBoot::isFeatureAvailable('NUMBER_OF_BACKUPS_TO_KEEP')) {
		$amountOfBackupsToKeep = SGConfig::get('SG_AMOUNT_OF_BACKUPS_TO_KEEP')?SGConfig::get('SG_AMOUNT_OF_BACKUPS_TO_KEEP'):SG_NUMBER_OF_BACKUPS_TO_KEEP;
		$backups = backupGuardScanBackupsDirectory($path);
		while (count($backups) > $amountOfBackupsToKeep) {
			$backup = key($backups);
			array_shift($backups);
			deleteDirectory($path.$backup);
		}
	}
}

function backupGuardValidateApiCall($path, $token)
{
	if (!strlen($path) || !strlen($token)) {
		exit();
	}

	$statePath = dirname($path).'/'.SG_STATE_FILE_NAME;

	if (!file_exists($statePath)) {
		exit();
	}

	$state = file_get_contents($statePath);
	$state = json_decode($state, true);
	$stateToken = $state['token'];

	if ($stateToken != $token) {
		exit();
	}

	return true;
}

function backupGuardScanBackupsDirectory($path)
{
	$backups = scandir($path);
	$backupFolders = array();
	foreach ($backups as $key => $backup) {
		if ($backup == "." || $backup == "..") {
			continue;
		}

		if (is_dir($path.$backup)) {
			$backupFolders[$backup] = filemtime($path.$backup);
		}
	}
	// Sort(from low to high) backups by creation date
	asort($backupFolders);
	return $backupFolders;
}

function backupGuardSymlinksCleanup($dir)
{
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object == "." || $object == "..") {
				continue;
			}

			if (filetype($dir.$object) != "dir") {
				@unlink($dir.$object);
			}
			else {
				backupGuardSymlinksCleanup($dir.$object.'/');
				@rmdir($dir.$object);
			}
		}
	}
	else {
		@unlink($dir);
	}
	return;
}

function processReplacements($str, $replacements)
{
    foreach ($replacements as $find => $replace) {
        return str_replace($find, $replace, $str);
    }
}

function realFilesize($filename)
{
	$fp = fopen($filename, 'r');
	$return = false;
	if (is_resource($fp))
	{
		if (PHP_INT_SIZE < 8) // 32 bit
		{
			if (0 === fseek($fp, 0, SEEK_END))
			{
				$return = 0.0;
				$step = 0x7FFFFFFF;
				while ($step > 0)
				{
					if (0 === fseek($fp, - $step, SEEK_CUR))
					{
						$return += floatval($step);
					}
					else
					{
						$step >>= 1;
					}
				}
			}
		}
		else if (0 === fseek($fp, 0, SEEK_END)) // 64 bit
		{
			$return = ftell($fp);
		}
	}

	return $return;
}

function formattedDuration($startTs, $endTs)
{
	$unit = 'seconds';
	$duration = $endTs-$startTs;
	if ($duration>=60 && $duration<3600)
	{
		$duration /= 60.0;
		$unit = 'minutes';
	}
	else if ($duration>=3600)
	{
		$duration /= 3600.0;
		$unit = 'hours';
	}
	$duration = number_format($duration, 2, '.', '');

	return $duration.' '.$unit;
}

function deleteDirectory($dirName)
{
	$dirHandle = null;
	if (is_dir($dirName))
	{
		$dirHandle = opendir($dirName);
	}

	if (!$dirHandle)
	{
		return false;
	}

	while ($file = readdir($dirHandle))
	{
		if ($file != "." && $file != "..")
		{
			if (!is_dir($dirName."/".$file))
			{
				@unlink($dirName."/".$file);
			}
			else
			{
				deleteDirectory($dirName.'/'.$file);
			}
		}
	}

	closedir($dirHandle);
	return @rmdir($dirName);
}

function backupGuardDownloadFile($file, $type = 'application/octet-stream')
{
	if (file_exists($file))
	{
		header('Content-Description: File Transfer');
		header('Content-Type: '.$type);
		header('Content-Disposition: attachment; filename="'.basename($file).'";');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		readfile($file);
	}

	exit;
}

function downloadFileSymlink($safedir, $filename)
{
	$downloaddir = SG_SYMLINK_PATH;
	$downloadURL = SG_SYMLINK_URL;

	if (!file_exists($downloaddir))
	{
		mkdir($downloaddir, 0777);
	}

	$letters = 'abcdefghijklmnopqrstuvwxyz';
	srand((double) microtime() * 1000000);
	$string = '';

	for ($i = 1; $i <= rand(4,12); $i++)
	{
	   $q = rand(1,24);
	   $string = $string.$letters[$q];
	}

	$handle = opendir($downloaddir);
	while ($dir = readdir($handle))
	{
		if ($dir == "." || $dir == "..")
		{
			continue;
		}

		if (is_dir($downloaddir.$dir))
		{
			@unlink($downloaddir . $dir . "/" . $filename);
			@rmdir($downloaddir . $dir);
		}
	}

	closedir($handle);

	mkdir($downloaddir . $string, 0777);
	$res = @symlink($safedir . $filename, $downloaddir . $string . "/" . $filename);
	if ($res) {
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment;filename='.$filename);
		header("Location: " . $downloadURL . $string . "/" . $filename);
	}
	else{
		wp_die('Symlink / shortcut creation failed! Seems your server configurations don’t allow symlink creation, so we’re unable to provide you the direct download url. You can download your backup using any FTP client. All backups and related stuff we locate “/wp-content/uploads/backup-guard” directory. If you need this functionality, you should check out your server configurations and make sure you don’t have any limitation related to symlink creation.');
	}
	exit;
}
