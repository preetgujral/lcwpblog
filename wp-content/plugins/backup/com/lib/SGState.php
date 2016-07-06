<?php

require_once(SG_LIB_PATH.'SGFileState.php');
require_once(SG_LIB_PATH.'SGDBState.php');

class SGState
{
	public $inprogress = false;
	public $type = '';
	public $token = '';
	public $action = '';
	public $actionId = null;
	public $actionStartTs = 0;
	public $backupFileName = '';
	public $backupFilePath = '';
	public $progress = 0;
	public $warningsFound = false;
	public $queuedStorageUploads = array();

	function __construct()
	{
	}

	public function setInprogress($inprogress)
	{
		$this->inprogress = $inprogress;
	}

	public function setToken($token)
	{
		$this->token = $token;
	}

	public function setAction($action)
	{
		$this->action = $action;
	}

	public function setType($type)
	{
		$this->type = $type;
	}

	public function setActionId($actionId)
	{
		$this->actionId = $actionId;
	}

	public function setActionStartTs($actionStartTs)
	{
		$this->actionStartTs = $actionStartTs;
	}

	public function setBackupFileName($name)
	{
		$this->backupFileName = $name;
	}

	public function setBackupFilePath($backupFilePath)
	{
		$this->backupFilePath = $backupFilePath;
	}

	public function setProgress($progress)
	{
		$this->progress = $progress;
	}

	public function setWarningsFound($warningsFound)
	{
		$this->warningsFound = $warningsFound;
	}

	public function setQueuedStorageUploads($queuedStorageUploads)
	{
		$this->queuedStorageUploads = $queuedStorageUploads;
	}

	public function getInprogress()
	{
		return $this->inprogress;
	}

	public function getToken()
	{
		return $this->token;
	}

	public function getAction()
	{
		return $this->action;
	}

	public function getType()
	{
		return $this->type;
	}

	public function getActionId()
	{
		return $this->actionId;
	}

	public function getActionStartTs()
	{
		return $this->actionStartTs;
	}

	public function getBackupFileName()
	{
		return $this->backupFileName;
	}

	public function getBackupFilePath()
	{
		return $this->backupFilePath;
	}

	public function getProgress()
	{
		return $this->progress;
	}

	public function getWarningsFound()
	{
		return $this->warningsFound;
	}

	public function getQueuedStorageUploads()
	{
		return $this->queuedStorageUploads;
	}

	public function factory($stateJson)
	{
		$stateJson = json_decode($stateJson, true);

		$type = $stateJson['type'];

		if ($type == SG_STATE_TYPE_FILE) {
			$sgState = new SGFileState();
			$sgState->setIndex($stateJson['index']);
			$sgState->setTotalBackupFilesCount($stateJson['totalBackupFilesCount']);
			$sgState->setCurrentBackupFileCount($stateJson['currentBackupFileCount']);
			$sgState->setCdrSize($stateJson['cdrSize']);
			$sgState->setRanges($stateJson['ranges']);
			$sgState->setOffset($stateJson['offset']);
			$sgState->setHeaderSize($stateJson['headerSize']);
			$sgState->setInprogress($stateJson['inprogress']);
		}
		else {
			$sgState = new SGDBState();
		}

		$sgState->setType($type);
		$sgState->setAction($stateJson['action']);
		$sgState->setActionId($stateJson['actionId']);
		$sgState->setActionStartTs($stateJson['actionStartTs']);
		$sgState->setBackupFileName($stateJson['backupFileName']);
		$sgState->setBackupFilePath($stateJson['backupFilePath']);
		$sgState->setProgress($stateJson['progress']);
		$sgState->setWarningsFound($stateJson['warningsFound']);
		$sgState->setQueuedStorageUploads($stateJson['queuedStorageUploads']);

		return $sgState;
	}
}
