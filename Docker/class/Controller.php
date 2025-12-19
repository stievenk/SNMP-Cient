<?php
class Controller extends Connection {

    public $error;
    public $config;
    public $conn;

    public function __construct($config) {
        try {
            if (empty($config['mysql']) || empty($config['mysql']['host']) 
            || empty($config['mysql']['user']) || empty($config['mysql']['data'])) {
                throw new Exception("Error Processing Request", 1);
            }
			if (!$this->conn = new \mysqli(
                $config['mysql']['host'],
                $config['mysql']['user'],
                $config['mysql']['pass'],
                $config['mysql']['data'])) {
				throw new \Exception($this->conn->connect_error, 1);
			}
		} catch (\Throwable $e) {
			$this->error = $e->getMessage();
			http_response_code(500);
			header('Content-Type: application/json');
			$data = array('done'=> 0, 'response' => $this->error, 'error' => 'Internal server Error 500', 'code' => 500, 'status' => 'error');
			echo json_encode($data); exit;
		}
    }

    public function select($query) {
        return $this->query($query);
    }

    public function fetch($result,$t = 'assoc') {
        if ($t === 'assoc') {
            return $this->fetch_assoc($result);
        } else if ($t === 'array') {
            return $this->fetch_array($result);
        } else if ($t === 'row') {
            return $this->fetch_row($result);
        } else if ($t === 'field') {
            return $this->fetch_field($result);
        } else if ($t === 'fields') {
            return $this->fetch_fields($result);
        } else {
            return false;
        }
    }

    function debug($text,$linebreak = true) {
        $tgl = date("Y-m-d H:i:s");
        echo "[{$tgl}] {$text}" . ($linebreak ? PHP_EOL : '');
    }

    function timeticksToReadable(string $timeticks): string {
        // Ambil angka di dalam tanda kurung
        if (preg_match('/\((\d+)\)/', $timeticks, $m)) {
            $ticks = (int)$m[1];
            $seconds = $ticks / 100; // 1 tick = 1/100 detik

            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;

            $days = floor($hours / 24);
            $hours = $hours % 24;

            return sprintf('%d hari %d jam %d menit %.2f detik', $days, $hours, $minutes, $secs);

        }

        return $timeticks; // fallback
    }
    
    public function save($table, $data, $pk = 'id') {
        try {
            if (empty($table) or empty($data)) {
                throw new Exception("Invalid Parameters");
            }
            $g = $this->query("select * from `{$table}` limit 1");
			$r = $this->fetch_fields($g);
            $fields = [];
            for ($i = 0; $i < count($r); $i++) {
                array_push($fields,$r[$i]->name);
			}
            // print_r($fields);
            $QUERY = "INSERT INTO";
            // Cek Primary Key
            if (in_array($pk, $fields)) {
                if (!empty($data[$pk])) {
                    $QUERY = "UPDATE";
                    $WHERE = " WHERE `{$pk}` = '". $this->escape_string($data[$pk]) ."'";
                }
            }
            foreach ($data as $k => $v) {
					if (!in_array($k,$fields)) { unset($data[$k]); }
			}
            $cols = [];
            foreach ($data as $k => $v) {
                $v = $this->escape_string(filter_var($v,FILTER_SANITIZE_SPECIAL_CHARS));
                if ($v == 'NULL' and $k != $pk) {
                    $cols[] = "`{$k}` = NULL";
                } else {
                    $cols[] = "`{$k}` = '{$v}'";
                }
            }

            $QUERY .= " `{$table}` SET ". implode(", ",$cols) . $WHERE;
            if($this->query($QUERY)) {
                return $this->insert_id() ?? $data[$pk] ?? 0;
            } else {
                $this->error = $this->conn->error;
                $this->debug($this->error);
                return false;
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function where($data,$andor = 'and') {
        $f = [];
        foreach($data as $k => $v) {
            $f[]="`$k` = '". $this->escape_string($v) ."'";
        }
        $this->wheres[] = " (".implode(" {$andor} ", $f).") ";
        return $this;
    }

    public function getWhere() {
        return implode(" and ",$this->wheres);
    }

    public function get($params) {
		//$id,$table,$fld='id'
		$params['return'] = isset($params['return']) ? $params['return'] : 'json';
		$params['error_return'] = isset($params['error_return']) ? $params['error_return'] : 'json';
		$params['field'] = isset($params['field']) ? $params['field'] : 'id';
		try {
			if (is_array($params['data'])) {
					$f = array();
					foreach($params['data'] as $k => $v) {
						$f[]="`$k` = '". $this->escape_string($v) ."'";
					}
					if (!$g = $this->query("select * from `{$params['table']}` where ". implode(" and ",$f) ."")) {
						throw new \Exception($this->error, 1);
					}
			} else if ($params['where']) {
                if (!$g = $this->query("select * from `{$params['table']}` where {$params['where']}")) {
                    throw new \Exception($this->error, 1);
                }
            }
            else {
					if ($params['data'] == 'last') {
						$g = $this->query("select * from `{$params['table']}` order by `{$params['field']}` desc limit 1");
					} else {
						if (!isset($params['data']) or !isset($params['field'])) {
							throw new \Exception("Field and Value can not blank ".__CLASS__."::->get(['field' => 'id', 'data' => '1', 'table' => 'table_name'])", 1);
						}
						if (!$g = $this->query("select * from `{$params['table']}` where `{$params['field']}`='". $this->escape_string($params['data']) ."'")) {
							throw new \Exception($this->conn->error, 1);
						}
					}
			}
			return $this->fetch_assoc($g);
		} catch (\Exception $e) {
			$this->error = $e->getMessage();
			// $this->error($params);
            $this->debug($this->error);
            return false;
		}
	}

}
?>