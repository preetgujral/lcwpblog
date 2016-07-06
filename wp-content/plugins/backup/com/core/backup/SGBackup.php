<?php
require_once(SG_BACKUP_PATH.'SGBackupLog.php');
require_once(SG_LIB_PATH.'SGState.php');
require_once(SG_LIB_PATH.'SGFileState.php');
require_once(SG_LIB_PATH.'SGDBState.php');
@include_once(SG_LIB_PATH.'SGBackgroundMode.php');
require_once(SG_BACKUP_PATH.'SGBackupFiles.php');
require_once(SG_BACKUP_PATH.'SGBackupDatabase.php');
@include_once(SG_BACKUP_PATH.'SGBackupStorage.php');
@include_once(SG_BACKUP_PATH.'SGBackupMailNotification.php');
require_once(SG_LOG_PATH.'SGFileLogHandler.php');

//close session for writing
@session_write_close();

class SGBackup implements SGIBackupDelegate
{
	private $backupFiles = null;
	private $backupDatabase = null;
	private $actionId = null;
	private $filesBackupAvailable = false;
	private $databaseBackupAvailable = false;
	private $actionStartTs = 0;
	private $fileName = '';
	private $filesBackupPath = '';
	private $databaseBackupPath = '';
	private $backupLogPath = '';
	private $restoreLogPath = '';
	private $backgroundMode = false;
	private $queuedStorageUploads = array();
	private $state = null;
	private $token = '';

	public function __construct()
	{
		$this->backupFiles = new SGBackupFiles();
		$this->backupFiles->setDelegate($this);

		$this->backupDatabase = new SGBackupDatabase();
		$this->backupDatabase->setDelegate($this);
	}

	public function getScheduleParamsById($id)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT * FROM '.SG_SCHEDULE_TABLE_NAME.' WHERE id=%d', array($id));
		if (empty($res)) {
			return '';
		}
		return $res[0];
	}

	private function handleBackupExecutionTimeout()
	{
		$filesBackupPath = SG_BACKUP_DIRECTORY.$this->fileName.'/'.$this->fileName.'.sgbp';
		$databaseBackupPath = SG_BACKUP_DIRECTORY.$this->fileName.'/'.$this->fileName.'.sql';

		$this->backupDatabase->setFilePath($databaseBackupPath);
		$this->backupDatabase->cancel();

		$this->backupFiles->setFilePath($filesBackupPath);
		$this->backupFiles->cancel();

		if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
			SGBackupMailNotification::sendBackupNotification(false);
		}
	}

	private function handleRestoreExecutionTimeout()
	{
		if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
			SGBackupMailNotification::sendRestoreNotification(false);
		}
	}

	public function handleExecutionTimeout($actionId)
	{
		$this->actionId = $actionId;
		$action = self::getAction($actionId);
		$this->fileName = $action['name'];
		$actionType = $action['type'];
		$backupPath = SG_BACKUP_DIRECTORY.$this->fileName;

		if ($actionType == SG_ACTION_TYPE_RESTORE) {
			$this->handleRestoreExecutionTimeout();
			$this->prepareRestoreLogFile($backupPath);
		}
		elseif ($actionType == SG_ACTION_TYPE_BACKUP) {
			$this->handleBackupExecutionTimeout();
			$this->prepareBackupLogFile($backupPath, true);
		}
		else{
			$this->setBackupStatusToWarning($this->fileName);
			$this->prepareBackupLogFile($backupPath, true);
		}

		//Stop all the running actions related to the specific backup, like backup, upload...
		$allActions = self::getRunningActions();
		foreach ($allActions as $action) {
			self::changeActionStatus($action['id'], SG_ACTION_STATUS_ERROR);
		}

		$exception = new SGExceptionExecutionTimeError();
		SGBackupLog::writeExceptionObject($exception);
		SGConfig::set('SG_EXCEPTION_TIMEOUT_ERROR', '1', true);
	}

	public function setBackupStatusToWarning($actionName)
	{
		$type = SG_ACTION_TYPE_BACKUP;
		$sgdb = SGDatabase::getInstance();
		$status = SG_ACTION_STATUS_FINISHED_WARNINGS;
		$res = $sgdb->query('UPDATE '.SG_ACTION_TABLE_NAME.' SET status=%d,'.$progress.' update_date=%s WHERE name=%d AND type=%d', array($status, @date('Y-m-d H:i:s'), $actionName, $type));
	}

	public function listStorage($storage)
	{
		if (SGBoot::isFeatureAvailable('DOWNLOAD_FROM_CLOUD')) {
			$listOfFiles = SGBackupStorage::getInstance()->listStorage($storage);
			return $listOfFiles;
		}

		return array();
	}

	public function downloadBackupArchiveFromCloud($archive, $storage, $size)
	{
		$result = false;
		if (SGBoot::isFeatureAvailable('DOWNLOAD_FROM_CLOUD')) {
			$result = SGBackupStorage::getInstance()->downloadBackupArchiveFromCloud($storage, $archive, $size);
		}

		return $result;
	}

	public function getState()
	{
		return $this->state;
	}

	private function prepareFilesStateFile()
	{
		$this->state = new SGFileState();
		$this->state->setRanges(array());
		$this->state->setOffset(0);
		$this->state->setToken($this->token);
		$this->state->setAction(SG_STATE_ACTION_PREPARING_STATE_FILE);
		$this->state->setType(SG_STATE_TYPE_FILE);
		$this->state->setActionId($this->actionId);
		$this->state->setActionStartTs($this->actionStartTs);
		$this->state->setBackupFileName($this->fileName);
		$this->state->setBackupFilePath($this->filesBackupPath);
		$this->state->setQueuedStorageUploads($this->queuedStorageUploads);
	}

	private function prepareDBStateFile()
	{
		$this->state = new SGDBState();
		$this->state->setToken($this->token);
		$this->state->setAction(SG_STATE_ACTION_PREPARING_STATE_FILE);
		$this->state->setType(SG_STATE_TYPE_DB);
		$this->state->setActionId($this->actionId);
		$this->state->setActionStartTs($this->actionStartTs);
		$this->state->setBackupFileName($this->fileName);
		$this->state->setBackupFilePath($this->filesBackupPath);
		$this->state->setQueuedStorageUploads($this->queuedStorageUploads);
	}

	public function getReloadUrl()
	{
		$url = @$_SERVER['REQUEST_URI'];

		if (SG_ENV_ADAPTER == SG_ENV_WORDPRESS) {
			if(strpos($url, 'wp-cron.php')) {
				$url = substr($url, 0, strpos($url, 'wp-cron.php'));
				$url .= 'wp-admin/admin-ajax.php';
			}

			$url = explode('?', $url);
			$url = $url[0].'?action=backup_guard_manualBackup&path='.$this->filesBackupPath.'&token='.$this->token;
		}

		return $url;
	}

	private function reload()
	{
		$url = $this->getReloadUrl();

		$reloadHandler = new SGReloadHandler($url);
		$reloadHandler->reload();
		die();
	}

	private function saveStateFile()
	{
		$this->state->save();
	}

	public function getToken()
	{
		return $this->token;
	}

	/* Backup implementation */

	public function backup($options, $state = false)
	{
		SGPing::update();
		$this->token = md5(time());

		$this->filesBackupAvailable = isset($options['SG_ACTION_BACKUP_FILES_AVAILABLE'])?$options['SG_ACTION_BACKUP_FILES_AVAILABLE']:false;
		$this->databaseBackupAvailable = isset($options['SG_ACTION_BACKUP_DATABASE_AVAILABLE'])?$options['SG_ACTION_BACKUP_DATABASE_AVAILABLE']:false;

		if (!$state) {
			$this->fileName = self::getBackupFileName();
			$this->prepareBackupFolder(SG_BACKUP_DIRECTORY.$this->fileName);
			$this->prepareForBackup($options);

			if ($this->databaseBackupAvailable) {
				$this->prepareDBStateFile();
			}
			else {
				$this->prepareFilesStateFile();
			}

			$this->saveStateFile();
			$this->reload();
		}

		$this->state = $state;
		$this->fileName = $state->getBackupFileName();
		$this->actionId = $state->getActionId();
		$this->actionStartTs = $state->getActionStartTs();
		$this->queuedStorageUploads = $state->getQueuedStorageUploads();

		$this->prepareBackupLogFile(SG_BACKUP_DIRECTORY.$this->fileName, true);
		$this->setBackupPaths();
		$this->prepareAdditionalConfigurations();

		SGPing::update();

		try
		{
			$this->backgroundMode = isset($options['SG_BACKUP_IN_BACKGROUND_MODE'])?$options['SG_BACKUP_IN_BACKGROUND_MODE']:false;

			if ($this->databaseBackupAvailable) {
				$this->backupDatabase->setFilePath($this->databaseBackupPath);
				$this->backupDatabase->setQueuedStorageUploads($this->queuedStorageUploads);
				$this->backupFiles->addDontExclude(realpath($this->databaseBackupPath));

				if (!$this->filesBackupAvailable) {
					$options['SG_BACKUP_FILE_PATHS'] = '';
				}

				if ($state->getType() == SG_STATE_TYPE_DB) {
					$this->backupDatabase->backup($this->databaseBackupPath);
					$this->prepareFilesStateFile();
					$this->saveStateFile();
					self::changeActionStatus($this->actionId, SG_ACTION_STATUS_IN_PROGRESS_FILES);
					$this->reload();
				}

				$rootDirectory = realpath(SGConfig::get('SG_APP_ROOT_DIRECTORY')).'/';
				$path = substr(realpath($this->databaseBackupPath), strlen($rootDirectory));
                $this->backupFiles->addDontExclude(realpath($this->databaseBackupPath));
				$backupItems = $options['SG_BACKUP_FILE_PATHS'];
				$allItems = $backupItems?explode(',', $backupItems):array();
				$allItems[] = $path;
				$options['SG_BACKUP_FILE_PATHS'] = implode(',', $allItems);

				$currentStatus = $this->getCurrentActionStatus();
				if ($currentStatus==SG_ACTION_STATUS_CANCELLING || $currentStatus==SG_ACTION_STATUS_CANCELLED) {
					$this->cancel();
				}
			}

			$this->backupFiles->setQueuedStorageUploads($this->queuedStorageUploads);
			$this->backupFiles->backup($this->filesBackupPath, $options, $state);
			$this->didFinishBackup();
		}
		catch (SGException $exception)
		{
			if ($exception instanceof SGExceptionSkip)
			{
				$this->setCurrentActionStatusCancelled();
			}
			else
			{
				SGBackupLog::writeExceptionObject($exception);

				if ($this->databaseBackupAvailable)
				{
					$this->backupDatabase->cancel();
				}

				$this->backupFiles->cancel();

				if (SGBoot::isFeatureAvailable('NOTIFICATIONS'))
				{
					SGBackupMailNotification::sendBackupNotification(false);
				}

				self::changeActionStatus($this->actionId, SG_ACTION_STATUS_ERROR);
			}
		}

		//continue uploading backup to storages
		try
		{
			$this->backupUploadToStorages();
		}
		catch (SGException $exception)
		{
			SGBackupLog::writeExceptionObject($exception);
		}
	}

	private function shouldDeleteBackupAfterUpload()
	{
		return SGConfig::get('SG_DELETE_BACKUP_AFTER_UPLOAD')?true:false;
	}

	private function backupUploadToStorages()
	{
		//check list of storages to upload if any
		$uploadToStorages = count($this->queuedStorageUploads)?true:false;
		$isDeleteLocalBackupFeatureAvailable = SGBoot::isFeatureAvailable('DELETE_LOCAL_BACKUP_AFTER_UPLOAD');
		if (SGBoot::isFeatureAvailable('STORAGE') && $uploadToStorages)
		{
			while (count($this->queuedStorageUploads))
			{
				$actionId = $this->queuedStorageUploads[0];
				SGBackupStorage::getInstance()->startUploadByActionId($actionId);
				array_shift($this->queuedStorageUploads);
			}

			if ($this->shouldDeleteBackupAfterUpload() && $uploadToStorages && $isDeleteLocalBackupFeatureAvailable) {
				@unlink(SG_BACKUP_DIRECTORY.$this->fileName.'/'.$this->fileName.'.'.SGBP_EXT);
			}
		}
	}

	private function cleanUp()
	{
		//delete sql file
		if ($this->databaseBackupAvailable) {
			@unlink($this->databaseBackupPath);
		}
	}

	private static function getBackupFileName()
	{
		$sgBackupPrefix = SGConfig::get('SG_BACKUP_FILE_NAME_PREFIX')?SGConfig::get('SG_BACKUP_FILE_NAME_PREFIX'):SG_BACKUP_FILE_NAME_DEFAULT_PREFIX;
		return $sgBackupPrefix.(@date('YmdHis'));
	}

	private function prepareBackupFolder($backupPath)
	{
		if (!is_writable(SG_BACKUP_DIRECTORY))
		{
			throw new SGExceptionForbidden('Permission denied. Directory is not writable: '.$backupPath);
		}

		//create backup folder
		if (!@mkdir($backupPath))
		{
			throw new SGExceptionMethodNotAllowed('Cannot create folder: '.$backupPath);
		}

		if (!is_writable($backupPath))
		{
			throw new SGExceptionForbidden('Permission denied. Directory is not writable: '.$backupPath);
		}

		//create backup log file
		$this->prepareBackupLogFile($backupPath);
	}

	private function prepareBackupLogFile($backupPath, $exists = false)
	{
		$file = $backupPath.'/'.$this->fileName.'_backup.log';
		$this->backupLogPath = $file;

		if (!$exists)
		{
			$content = self::getLogFileHeader();

			$types = array();
			if ($this->filesBackupAvailable)
			{
				$types[] = 'files';
			}
			if ($this->databaseBackupAvailable)
			{
				$types[] = 'database';
			}

			$content .= 'Backup type: '.implode(',', $types).PHP_EOL.PHP_EOL;

			if (!file_put_contents($file, $content))
			{
				throw new SGExceptionMethodNotAllowed('Cannot create backup log file: '.$file);
			}
		}

		//create file log handler
		$fileLogHandler = new SGFileLogHandler($file);
		SGLog::registerLogHandler($fileLogHandler, SG_LOG_LEVEL_LOW, true);
	}

	private function setBackupPaths()
	{
		$this->filesBackupPath = SG_BACKUP_DIRECTORY.$this->fileName.'/'.$this->fileName.'.sgbp';
		$this->databaseBackupPath = SG_BACKUP_DIRECTORY.$this->fileName.'/'.$this->fileName.'.sql';
	}

	private function prepareUploadToStorages($options)
	{
		$uploadToStorages = $options['SG_BACKUP_UPLOAD_TO_STORAGES'];

		if (SGBoot::isFeatureAvailable('STORAGE') && $uploadToStorages) {
			$storages = explode(',', $uploadToStorages);
			$arr = array();
			foreach ($storages as $storageId) {
				$actionId = SGBackupStorage::queueBackupForUpload($this->fileName, $storageId, $options);
				$arr[] = $actionId;
			}
			$this->queuedStorageUploads = $arr;
		}
	}

	private function prepareAdditionalConfigurations()
	{
		$this->backupFiles->setFilePath($this->filesBackupPath);
		SGConfig::set('SG_RUNNING_ACTION', 1, true);
	}

	private function prepareForBackup($options)
	{
		//start logging
		SGBackupLog::writeAction('backup', SG_BACKUP_LOG_POS_START);

		//save timestamp for future use
		$this->actionStartTs = time();

		//create action inside db
		$status = $this->databaseBackupAvailable?SG_ACTION_STATUS_IN_PROGRESS_DB:SG_ACTION_STATUS_IN_PROGRESS_FILES;
		$this->actionId = self::createAction($this->fileName, SG_ACTION_TYPE_BACKUP, $status, 0, json_encode($options));

		//set paths
		$this->setBackupPaths();

		//prepare sgbp file
		@file_put_contents($this->filesBackupPath, '');

		if (!is_writable($this->filesBackupPath))
		{
			throw new SGExceptionForbidden('Could not create backup file: '.$this->filesBackupPath);
		}

		//additional configuration
		$this->prepareAdditionalConfigurations();

		//check if upload to storages is needed
		$this->prepareUploadToStorages($options);
	}

	public function cancel()
	{
		$dir = SG_BACKUP_DIRECTORY.$this->fileName;
		if ($dir != SG_BACKUP_DIRECTORY) {
			deleteDirectory($dir);
		}

		throw new SGExceptionSkip();
	}

	private function didFinishBackup()
	{
		if(SGConfig::get('SG_REVIEW_POPUP_STATE') != SG_NEVER_SHOW_REVIEW_POPUP)
		{
			SGConfig::set('SG_REVIEW_POPUP_STATE', SG_SHOW_REVIEW_POPUP);
		}

		$action = $this->didFindWarnings()?SG_ACTION_STATUS_FINISHED_WARNINGS:SG_ACTION_STATUS_FINISHED;
		self::changeActionStatus($this->actionId, $action);

		SGBackupLog::writeAction('backup', SG_BACKUP_LOG_POS_END);

		if (SGBoot::isFeatureAvailable('NOTIFICATIONS'))
		{
			SGBackupMailNotification::sendBackupNotification(true);
		}

		SGBackupLog::write('Total duration: '.formattedDuration($this->actionStartTs, time()));

		$this->cleanUp();
	}

	/* Restore implementation */

	public function restore($backupName)
	{
		$this->prepareForRestore($backupName);
		SGPing::update();

		try
		{
			$this->backupFiles->restore($this->filesBackupPath);
			$this->didFinishFilesRestore();
		}
		catch (SGException $exception)
		{
			if (!$exception instanceof SGExceptionSkip)
			{
				SGBackupLog::writeExceptionObject($exception);

				if (SGBoot::isFeatureAvailable('NOTIFICATIONS'))
				{
					SGBackupMailNotification::sendRestoreNotification(false);
				}

				self::changeActionStatus($this->actionId, SG_ACTION_STATUS_ERROR);
			}
			else
			{
				self::changeActionStatus($this->actionId, SG_ACTION_STATUS_CANCELLED);
			}
		}
	}

	private function prepareForRestore($backupName)
	{
		//prepare file name
		$this->fileName = $backupName;

		//set paths
		$restorePath = SG_BACKUP_DIRECTORY.$this->fileName;
		$this->filesBackupPath = $restorePath.'/'.$this->fileName.'.sgbp';
		$this->databaseBackupPath = $restorePath.'/'.$this->fileName.'.sql';

		//prepare folder
		$this->prepareRestoreFolder($restorePath);

		//start logging
		SGBackupLog::writeAction('restore', SG_BACKUP_LOG_POS_START);

		SGConfig::set('SG_RUNNING_ACTION', 1, true);

		//save timestamp for future use
		$this->actionStartTs = time();

		//create action inside db
		$this->actionId = self::createAction($this->fileName, SG_ACTION_TYPE_RESTORE, SG_ACTION_STATUS_IN_PROGRESS_FILES);
	}

	private function prepareRestoreFolder($restorePath)
	{
		if (!is_writable($restorePath))
		{
			throw new SGExceptionForbidden('Permission denied. Directory is not writable: '.$restorePath);
		}

		$this->filesBackupAvailable = file_exists($this->filesBackupPath);

		//create restore log file
		$this->prepareRestoreLogFile($restorePath);
	}

	private function prepareRestoreLogFile($backupPath)
	{
		$file = $backupPath.'/'.$this->fileName.'_restore.log';
		$this->restoreLogPath = $file;

		$content = self::getLogFileHeader();

		$content .= PHP_EOL;

		if (!file_put_contents($file, $content))
		{
			throw new SGExceptionMethodNotAllowed('Cannot create restore log file: '.$file);
		}

		//create file log handler
		$fileLogHandler = new SGFileLogHandler($file);
		SGLog::registerLogHandler($fileLogHandler, SG_LOG_LEVEL_LOW, true);
	}

	private function didFinishRestore()
	{
		$action = $this->didFindWarnings()?SG_ACTION_STATUS_FINISHED_WARNINGS:SG_ACTION_STATUS_FINISHED;
		self::changeActionStatus($this->actionId, $action);

		SGBackupLog::writeAction('restore', SG_BACKUP_LOG_POS_END);

		if (SGBoot::isFeatureAvailable('NOTIFICATIONS'))
		{
			SGBackupMailNotification::sendRestoreNotification(true);
		}

		SGBackupLog::write('Total duration: '.formattedDuration($this->actionStartTs, time()));

		$this->cleanUp();
	}

	private function didFinishFilesRestore()
	{
		$this->databaseBackupAvailable = file_exists($this->databaseBackupPath);

		if ($this->databaseBackupAvailable)
		{
			self::changeActionStatus($this->actionId, SG_ACTION_STATUS_IN_PROGRESS_DB);
			$this->backupDatabase->restore($this->databaseBackupPath);
		}

		$this->didFinishRestore();
	}

	/* General methods */

	public static function getLogFileHeader()
	{
		$confs = array();
		$confs['sg_backup_guard_version'] = SG_BACKUP_GUARD_VERSION;
		$confs['sg_archive_version'] = SG_ARCHIVE_VERSION;
		$confs['os'] = PHP_OS;
		$confs['server'] = @$_SERVER['SERVER_SOFTWARE'];
		$confs['php_version'] = PHP_VERSION;
		$confs['sapi'] = PHP_SAPI;
		$confs['codepage'] = setlocale(LC_CTYPE, '');
		$confs['int_size'] = PHP_INT_SIZE;

		if (extension_loaded('gmp')) $lib = 'gmp';
		else if (extension_loaded('bcmath')) $lib = 'bcmath';
		else $lib = 'BigInteger';

		$confs['int_lib'] = $lib;
		$confs['memory_limit'] = ini_get('memory_limit');
		$confs['max_execution_time'] = SGBoot::$executionTimeLimit;
		$confs['env'] = SG_ENV_ADAPTER.' '.SG_ENV_VERSION;

		$content = '';
		$content .= 'Date: '.@date('Y-m-d H:i').PHP_EOL;
		$content .= 'SG Backup Guard version: '.$confs['sg_backup_guard_version'].PHP_EOL;
		$content .= 'SG archive version: '.$confs['sg_archive_version'].PHP_EOL;
		$content .= 'OS: '.$confs['os'].PHP_EOL;
		$content .= 'Server: '.$confs['server'].PHP_EOL;
		$content .= 'User agent: '.@$_SERVER['HTTP_USER_AGENT'].PHP_EOL;
		$content .= 'PHP version: '.$confs['php_version'].PHP_EOL;
		$content .= 'SAPI: '.$confs['sapi'].PHP_EOL;
		$content .= 'Codepage: '.$confs['codepage'].PHP_EOL;
		$content .= 'Int size: '.$confs['int_size'].PHP_EOL;
		$content .= 'Int lib: '.$confs['int_lib'].PHP_EOL;
		$content .= 'Memory limit: '.$confs['memory_limit'].PHP_EOL;
		$content .= 'Max execution time: '.$confs['max_execution_time'].PHP_EOL;
		$content .= 'Environment: '.$confs['env'].PHP_EOL;

		return $content;
	}

	private function didFindWarnings()
	{
		$warningsDatabase = $this->databaseBackupAvailable?$this->backupDatabase->didFindWarnings():false;
		$warningsFiles = $this->backupFiles->didFindWarnings();
		return ($warningsFiles||$warningsDatabase);
	}

	public static function createAction($name, $type, $status, $subtype = 0, $options = '')
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('INSERT INTO '.SG_ACTION_TABLE_NAME.' (name, type, subtype, status, start_date, options) VALUES (%s, %d, %d, %d, %s, %s)', array($name, $type, $subtype, $status, @date('Y-m-d H:i:s'), $options));
		if (!$res)
		{
			throw new SGExceptionDatabaseError('Could not create action');
		}
		return $sgdb->lastInsertId();
	}

	private function getCurrentActionStatus()
	{
		return self::getActionStatus($this->actionId);
	}

	private function setCurrentActionStatusCancelled()
	{
		$sgdb = SGDatabase::getInstance();
		$sgdb->query('UPDATE '.SG_ACTION_TABLE_NAME.' SET status=%d, update_date=%s WHERE name=%s', array(SG_ACTION_STATUS_CANCELLED, @date('Y-m-d H:i:s'), $this->fileName));
	}

	public static function changeActionStatus($actionId, $status)
	{
		$sgdb = SGDatabase::getInstance();

		$progress = '';
		if ($status==SG_ACTION_STATUS_FINISHED || $status==SG_ACTION_STATUS_FINISHED_WARNINGS)
		{
			$progress = 100;
		}
		else if ($status==SG_ACTION_STATUS_CREATED || $status==SG_ACTION_STATUS_IN_PROGRESS_FILES || $status==SG_ACTION_STATUS_IN_PROGRESS_DB)
		{
			$progress = 0;
		}

		if ($progress!=='')
		{
			$progress = ' progress='.$progress.',';
		}

		$res = $sgdb->query('UPDATE '.SG_ACTION_TABLE_NAME.' SET status=%d,'.$progress.' update_date=%s WHERE id=%d', array($status, @date('Y-m-d H:i:s'), $actionId));
	}

	public static function changeActionProgress($actionId, $progress)
	{
		$sgdb = SGDatabase::getInstance();
		$sgdb->query('UPDATE '.SG_ACTION_TABLE_NAME.' SET progress=%d, update_date=%s WHERE id=%d', array($progress, @date('Y-m-d H:i:s'), $actionId));
	}

	/* Methods for frontend use */

	public static function getAction($actionId)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT * FROM '.SG_ACTION_TABLE_NAME.' WHERE id=%d', array($actionId));
		if (empty($res))
		{
			return false;
		}
		return $res[0];
	}

	public static function getActionByName($name)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT * FROM '.SG_ACTION_TABLE_NAME.' WHERE name=%s', array($name));
		if (empty($res)) {
			return false;
		}
		return $res[0];
	}

	public static function getActionProgress($actionId)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT progress FROM '.SG_ACTION_TABLE_NAME.' WHERE id=%d', array($actionId));
		if (empty($res))
		{
			return false;
		}
		return (int)$res[0]['progress'];
	}

	public static function getActionStatus($actionId)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT status FROM '.SG_ACTION_TABLE_NAME.' WHERE id=%d', array($actionId));
		if (empty($res))
		{
			return false;
		}
		return (int)$res[0]['status'];
	}

	public static function getRunningActions()
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT * FROM '.SG_ACTION_TABLE_NAME.' WHERE status=%d OR status=%d OR status=%d ORDER BY status DESC', array(SG_ACTION_STATUS_IN_PROGRESS_FILES, SG_ACTION_STATUS_IN_PROGRESS_DB, SG_ACTION_STATUS_CREATED));
		return $res;
	}

	public static function getBackupFileInfo($file)
	{
		return pathinfo(SG_BACKUP_DIRECTORY.$file);
	}

	public static function autodetectBackups()
	{
		$path = SG_BACKUP_DIRECTORY;
		$files = scandir(SG_BACKUP_DIRECTORY);
		$backupLogPostfix = "_backup.log";
		$restoreLogPostfix = "_restore.log";

		foreach ($files as $file) {
			$fileInfo = self::getBackupFileInfo($file);

			if (@$fileInfo['extension'] == SGBP_EXT) {
				@mkdir($path.$fileInfo['filename'], 0777);

				if(file_exists($path.$fileInfo['filename'])) {
					rename($path.$file, $path.$fileInfo['filename'].'/'.$file);
				}

				if(file_exists($path.$fileInfo['filename'].$backupLogPostfix)){
					rename($path.$fileInfo['filename'].$backupLogPostfix, $path.$fileInfo['filename'].'/'.$fileInfo['filename'].$backupLogPostfix);
				}

				if (file_exists($path.$fileInfo['filename'].$restoreLogPostfix)) {
					rename($path.$fileInfo['filename'].$restoreLogPostfix, $path.$fileInfo['filename'].'/'.$fileInfo['filename'].$restoreLogPostfix);
				}
			}
		}
	}

	public static function getAllBackups()
	{
		$backups = array();

		$path = SG_BACKUP_DIRECTORY;
		self::autodetectBackups();
		clearstatcache();

		if (SGBoot::isFeatureAvailable('NUMBER_OF_BACKUPS_TO_KEEP')) {
			backupGuardOutdatedBackupsCleanup($path);
		}

		if ($handle = @opendir($path)) {
			$sgdb = SGDatabase::getInstance();
			$data = $sgdb->query('SELECT id, name, type, subtype, status, progress, update_date, options FROM '.SG_ACTION_TABLE_NAME);
			$allBackups = array();
			foreach ($data as $row) {
				$allBackups[$row['name']][] = $row;
			}

			while (($entry = readdir($handle)) !== false) {
				if ($entry === '.' || $entry === '..' || !is_dir($path.$entry)) {
					continue;
				}

				$backup = array();
				$backup['name'] = $entry;
				$backup['id'] = '';
				$backup['status'] = '';
				$backup['files'] = file_exists($path.$entry.'/'.$entry.'.sgbp')?1:0;
				$backup['backup_log'] = file_exists($path.$entry.'/'.$entry.'_backup.log')?1:0;
				$backup['restore_log'] = file_exists($path.$entry.'/'.$entry.'_restore.log')?1:0;
				$backup['options'] = '';
				if (!$backup['files'] && !$backup['backup_log'] && !$backup['restore_log']) {
					continue;
				}
				$backupRow = null;
				if (isset($allBackups[$entry])) {
					$skip = false;
					foreach ($allBackups[$entry] as $row) {
						if ($row['status']==SG_ACTION_STATUS_IN_PROGRESS_FILES || $row['status']==SG_ACTION_STATUS_IN_PROGRESS_DB) {
							$backupRow = $row;
							break;
						}
						else if (($row['status']==SG_ACTION_STATUS_CANCELLING || $row['status']==SG_ACTION_STATUS_CANCELLED) && $row['type']!=SG_ACTION_TYPE_UPLOAD) {
							$skip = true;
							break;
						}

						$backupRow = $row;

						if ($row['status']==SG_ACTION_STATUS_FINISHED_WARNINGS || $row['status']==SG_ACTION_STATUS_ERROR) {
							if ($row['type'] == SG_ACTION_TYPE_UPLOAD && file_exists(SG_BACKUP_DIRECTORY.$entry.'/'.$entry.'.sgbp')) {
								$backupRow['status'] = SG_ACTION_STATUS_FINISHED_WARNINGS;
							}
						}
					}

					if ($skip===true) {
						continue;
					}
				}

				if ($backupRow) {
					$backup['active'] = ($backupRow['status']==SG_ACTION_STATUS_IN_PROGRESS_FILES||
					$backupRow['status']==SG_ACTION_STATUS_IN_PROGRESS_DB||
					$backupRow['status']==SG_ACTION_STATUS_CREATED)?1:0;

					$backup['status'] = $backupRow['status'];
					$backup['type'] = (int)$backupRow['type'];
					$backup['subtype'] = (int)$backupRow['subtype'];
					$backup['progress'] = (int)$backupRow['progress'];
					$backup['id'] = (int)$backupRow['id'];
					$backup['options'] = $backupRow['options'];
				}
				else {
					$backup['active'] = 0;
				}

				$size = '';
				if ($backup['files']) {
					$size = number_format(realFilesize($path.$entry.'/'.$entry.'.sgbp')/1024.0/1024.0, 2, '.', '').' MB';
				}

				$backup['size'] = $size;

				$modifiedTime = filemtime($path.$entry.'/.');
				$backup['date'] = @date('Y-m-d H:i', $modifiedTime);
				$backup['modifiedTime'] = $modifiedTime;
				$backups[] = $backup;
			}
			closedir($handle);
		}

		usort($backups, array('SGBackup', 'sort'));
		return array_values($backups);
	}

	public static function sort($arg1, $arg2)
	{
		return $arg1['modifiedTime']>$arg2['modifiedTime']?-1:1;
	}

	public static function deleteBackup($backupName, $deleteAction = true)
	{
		deleteDirectory(SG_BACKUP_DIRECTORY.$backupName);

		if ($deleteAction)
		{
			$sgdb = SGDatabase::getInstance();
			$sgdb->query('DELETE FROM '.SG_ACTION_TABLE_NAME.' WHERE name=%s', array($backupName));
		}
	}

	public static function cancelAction($actionId)
	{
		self::changeActionStatus($actionId, SG_ACTION_STATUS_CANCELLING);
	}

	public static function upload($filesUploadSgbp)
	{
		$filename = self::getBackupFileName();
		$backupDirectory = $filename.'/';
		$uploadPath = SG_BACKUP_DIRECTORY.$backupDirectory;
		$filename = $uploadPath.$filename;

		if (!@file_exists($uploadPath))
		{
			if (!@mkdir($uploadPath))
			{
				throw new SGExceptionForbidden('Upload folder is not accessible');
			}
		}

		if (!empty($filesUploadSgbp) && $filesUploadSgbp['name'] != '')
		{
			if ($filesUploadSgbp['type'] != 'application/octet-stream')
			{
				throw new SGExceptionBadRequest('Not a valid backup file');
			}
			if (!@move_uploaded_file($filesUploadSgbp['tmp_name'], $filename.'.sgbp'))
			{
				throw new SGExceptionForbidden('Error while uploading file');
			}
		}
	}

	public static function download($filename, $type)
	{
		$backupDirectory = SG_BACKUP_DIRECTORY.$filename.'/';

		switch ($type)
		{
			case SG_BACKUP_DOWNLOAD_TYPE_SGBP:
				$filename .= '.sgbp';
				downloadFileSymlink($backupDirectory, $filename);
				break;
			case SG_BACKUP_DOWNLOAD_TYPE_BACKUP_LOG:
				$filename .= '_backup.log';
				backupGuardDownloadFile($backupDirectory.$filename, 'text/plain');
				break;
			case SG_BACKUP_DOWNLOAD_TYPE_RESTORE_LOG:
				$filename .= '_restore.log';
				backupGuardDownloadFile($backupDirectory.$filename, 'text/plain');
				break;
		}

		exit;
	}

	/* SGIBackupDelegate implementation */

	public function isCancelled()
	{
		$status = $this->getCurrentActionStatus();

		if ($status==SG_ACTION_STATUS_CANCELLING)
		{
			$this->cancel();
			return true;
		}

		return false;
	}

	public function didUpdateProgress($progress)
	{
		$progress = max($progress, 0);
		$progress = min($progress, 100);

		self::changeActionProgress($this->actionId, $progress);
	}

	public function isBackgroundMode()
	{
		return $this->backgroundMode;
	}
}
