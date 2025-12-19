<?php
class Connection {
   public $conn;
	public $error;
	public $config;
	
	function __construct($config) {
		// print_r($config); exit;
		$this->config = $config;
		try {
            if (empty($config['mysql']) || empty($config['mysql']['host']) 
            || empty($config['mysql']['user']) || empty($config['mysql']['data'])) {
                throw new Exception("Error Processing Request", 1);
            }
			if (!$this->conn = new \mysqli($config['host'],$config['user'],$config['pass'],$config['data'])) {
				throw new \Exception($this->conn->connect_error, 1);
			}
		} catch (\Exception $e) {
			$this->error = $e->getMessage();
			http_response_code(500);
			header('Content-Type: application/json');
			$data = array('done'=> 0, 'response' => $this->error, 'error' => 'Internal server Error 500', 'code' => 500, 'status' => 'error');
			echo json_encode($data); exit;
		}

	}

	public function error($die=0) {
		$this->error = $this->conn->error;
		return $this->error;
	}

	public function query($query) {
		try {
			if ($query) {
				$qry = $this->conn->query($query);
				if ($qry) { return $qry; } else {
					throw new \Exception($this->conn->error, 1);
					return false;
				}
			}
		} catch (\Exception $e) {
			$this->error = $e->getMessage();
			echo  $this->error;
			return false;
		}
	}

	public function multi_query($query) {
		return $this->conn->multi_query($query);
	}

	public function store_result() {
		return $this->conn->store_result();
	}

	public function next_result() {
		return $this->conn->next_result();
	}

	public function more_results() {
		return $this->conn->more_results();
	}
	
	public function insert_id() {
		return $this->conn->insert_id;
	}
	
	public function fetch_assoc($result) {
		return $result->fetch_assoc();
	}
	
	public function fetch_array($result) {
		return $result->fetch_array();
	}

	public function fetch_row($result) {
		return $result->fetch_row();
	}
	
	public function fetch_field($result) {
		return $result->fetch_field();
	}
	
	public function fetch_fields($result) {
		return $result->fetch_fields();
	}
	
	public function num_rows($result) {
		return $result->num_rows;
	}
	
	public function fetch_length($result) {
		return $result->length;
	}
	
	public function escape_string($string) {
		return $this->conn->real_escape_string($string);
	}

	function __destruct() {
		$this->conn->close();
	}
}

?>