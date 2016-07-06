<?php

require_once(dirname(__FILE__).'/SGState.php');

class SGDBState extends SGState
{
	public function save()
	{
		file_put_contents(dirname($this->backupFilePath).'/'.SG_STATE_FILE_NAME, json_encode(array(
			'type' => $this->type,
			'token' => $this->token,
			'action' => $this->action,
			'actionId' => $this->actionId,
			'actionStartTs' => $this->actionStartTs,
			'backupFileName' => $this->backupFileName,
			'backupFilePath' => $this->backupFilePath,
			'progress' => $this->progress,
			'warningsFound' => $this->warningsFound,
			'queuedStorageUploads' => $this->queuedStorageUploads
		)));
	}
}
