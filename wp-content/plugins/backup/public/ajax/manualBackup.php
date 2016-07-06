<?php
	require_once(dirname(__FILE__) . '/../boot.php');
	require_once(SG_BACKUP_PATH . 'SGBackup.php');

	try {
		$state = false;
		$success = array('success' => 1);
		if (isAjax() && count($_POST)) {
			$options = $_POST;
			$error = array();
			$options = setManualBackupOptions($options);
		}
		else{
			$path = @$_GET['path'];
			$state = loadStateData($path);
			$action = SGBackup::getAction($state->getActionId());
			$options = json_decode($action['options'], true);
		}

		$sgBackup = new SGBackup();
		$sgBackup->backup($options, $state);

		die(json_encode($success));

	}
	catch (SGException $exception) {
		array_push($error, $exception->getMessage());
		die(json_encode($error));
	}

	function loadStateData($path)
	{
		$sgState = new SGState();

		$stateFile = file_get_contents(dirname($path).'/'.SG_STATE_FILE_NAME);
		$sgState = $sgState->factory($stateFile);

		return $sgState;
	}

	function setManualBackupOptions($options)
	{
		$backupOptions = array(
			'SG_BACKUP_IN_BACKGROUND_MODE' => 0,
			'SG_BACKUP_UPLOAD_TO_STORAGES' => '',
			'SG_ACTION_BACKUP_DATABASE_AVAILABLE' => 0,
			'SG_ACTION_BACKUP_FILES_AVAILABLE' => '',
			'SG_BACKUP_FILE_PATHS_EXCLUDE' => '',
			'SG_BACKUP_FILE_PATHS' => '',
		);

		//If background mode
		$isBackgroundMode = !empty($options['backgroundMode']) ? 1 : 0;
		$backupOptions['SG_BACKUP_IN_BACKGROUND_MODE'] = $isBackgroundMode;

		//If cloud backup
		if (!empty($options['backupCloud']) && count($options['backupStorages'])) {
			$clouds = $options['backupStorages'];
			$backupOptions['SG_BACKUP_UPLOAD_TO_STORAGES'] = implode(',', $clouds);
		}

		if ($options['backupType'] == SG_BACKUP_TYPE_FULL) {
			$backupOptions['SG_ACTION_BACKUP_DATABASE_AVAILABLE']= 1;
			$backupOptions['SG_ACTION_BACKUP_FILES_AVAILABLE'] = 1;
			$backupOptions['SG_BACKUP_FILE_PATHS_EXCLUDE'] = SG_BACKUP_FILE_PATHS_EXCLUDE;
			$backupOptions['SG_BACKUP_FILE_PATHS'] = 'wp-content';
		}
		else if ($options['backupType'] == SG_BACKUP_TYPE_CUSTOM) {
			//If database backup
			$isDatabaseBackup = !empty($options['backupDatabase']) ? 1 : 0;
			$backupOptions['SG_ACTION_BACKUP_DATABASE_AVAILABLE'] = $isDatabaseBackup;

			//If files backup
			if (!empty($options['backupFiles']) && count($options['directory'])) {
				$backupFiles = explode(',', SG_BACKUP_FILE_PATHS);
				$filesToExclude = @array_diff($backupFiles, $options['directory']);

				if (in_array('wp-content', $options['directory'])) {
					$options['directory'] = array('wp-content');
				}
				else {
					$filesToExclude = array_diff($filesToExclude, array('wp-content'));
				}

				$filesToExclude = implode(',', $filesToExclude);
				if (strlen($filesToExclude)) {
					$filesToExclude = ','.$filesToExclude;
				}

				$backupOptions['SG_BACKUP_FILE_PATHS_EXCLUDE'] = SG_BACKUP_FILE_PATHS_EXCLUDE.$filesToExclude;
				$backupOptions['SG_BACKUP_FILE_PATHS'] = implode(',', $options['directory']);
				$backupOptions['SG_ACTION_BACKUP_FILES_AVAILABLE'] = 1;
			}
			else {
				$backupOptions['SG_ACTION_BACKUP_FILES_AVAILABLE'] = 0;
				$backupOptions['SG_BACKUP_FILE_PATHS'] = 0;
			}
		}
		return $backupOptions;
	}
