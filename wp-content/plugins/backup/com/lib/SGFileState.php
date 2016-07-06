<?php

require_once(dirname(__FILE__).'/SGState.php');

class SGFileState extends SGState
{
	private $index = 0;
	private $totalBackupFilesCount = 0;
	private $currentBackupFileCount = 0;
	private $cdrSize = 0;
	private $ranges = array();
	private $offset = 0;
	private $headerSize = 0;

	public function setIndex($index)
	{
		$this->index = $index;
	}

	public function setTotalBackupFilesCount($totalBackupFilesCount)
	{
		$this->totalBackupFilesCount = $totalBackupFilesCount;
	}

	public function setCurrentBackupFileCount($currentBackupFileCount)
	{
		$this->currentBackupFileCount = $currentBackupFileCount;
	}

	public function setCdrSize($cdrSize)
	{
		$this->cdrSize = $cdrSize;
	}

	public function setRanges($ranges)
	{
		$this->ranges = $ranges;
	}

	public function setOffset($offset)
	{
		$this->offset = $offset;
	}

	public function setHeaderSize($headerSize)
	{
		$this->headerSize = $headerSize;
	}

	public function getHeaderSize()
	{
		return $this->headerSize;
	}

	public function getOffset()
	{
		return $this->offset;
	}

	public function getRanges()
	{
		return $this->ranges;
	}

	public function getIndex()
	{
		return $this->index;
	}

	public function getTotalBackupFilesCount()
	{
		return $this->totalBackupFilesCount;
	}

	public function getCurrentBackupFileCount()
	{
		return $this->currentBackupFileCount;
	}

	public function getCdrSize()
	{
		return $this->cdrSize;
	}

	public function save()
	{
		file_put_contents(dirname($this->backupFilePath).'/'.SG_STATE_FILE_NAME, json_encode(array(
			'inprogress' => $this->inprogress,
			'headerSize' => $this->headerSize,
			'offset' => $this->offset,
			'ranges' => $this->ranges,
			'type' => $this->type,
			'token' => $this->token,
			'action' => $this->action,
			'actionId' => $this->actionId,
			'actionStartTs' => $this->actionStartTs,
			'backupFileName' => $this->backupFileName,
			'backupFilePath' => $this->backupFilePath,
			'progress' => $this->progress,
			'warningsFound' => $this->warningsFound,
			'index' => $this->index,
			'totalBackupFilesCount' => $this->totalBackupFilesCount,
			'currentBackupFileCount' => $this->currentBackupFileCount,
			'cdrSize' => $this->cdrSize,
			'queuedStorageUploads' => $this->queuedStorageUploads
		)));
	}
}
