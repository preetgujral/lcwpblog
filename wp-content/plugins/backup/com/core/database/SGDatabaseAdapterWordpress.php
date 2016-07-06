<?php
require_once(SG_DATABASE_PATH.'SGIDatabaseAdapter.php');

class SGDatabaseAdapterWordpress implements SGIDatabaseAdapter
{
    private $fetchRowIndex = 0;
    private $lastResult = array();

	public function query($query, $params=array())
	{
		global $wpdb;

        $op = strtoupper(substr(trim($query), 0, 6));
		if ($op!='INSERT' && $op!='UPDATE' && $op!='DELETE')
        {
            if(!empty($params))
            {
                return @$wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
            }
            return @$wpdb->get_results($query, ARRAY_A);
		}
		else
        {
            if(!empty($params))
            {
                return $wpdb->query($wpdb->prepare($query, $params));
            }
            return $wpdb->query($query);
		}
	}

	public function exec($query, $params=array())
    {
        global $wpdb;

        $this->fetchRowIndex = 0;
        $res = $wpdb->query($query);

        if ($res === false)
        {
            return false;
        }
        return $query;
    }

    public function fetch($st)
    {
        global $wpdb;

        if ($this->fetchRowIndex==0) {
            $this->lastResult = $wpdb->last_result;
        }

        $res = @$this->lastResult[$this->fetchRowIndex];
        if (!$res) return false;

        $this->fetchRowIndex++;
        return get_object_vars($res);
    }

	public function lastInsertId()
	{
		global $wpdb;
		return $wpdb->insert_id;
	}

	public function printLastError()
	{
		global $wpdb;
		$wpdb->print_error();
	}
}
