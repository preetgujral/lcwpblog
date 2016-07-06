<?php

/**
*
*/
interface Entry
{
	public function getType();
	public function getPath();
	public function setPath($path);
	public function toArray();
}
