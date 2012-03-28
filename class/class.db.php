<?php
class db extends PDO {
	private $error;
	private $sql;
	private $bind;
	private $errorCallbackFunction;
	private $errorMsgFormat;
  public $pdostmt;

	public function __construct($dsn, $user="", $passwd="") {
		$options = array(
			//PDO::ATTR_PERSISTENT => true,
			1002 => 'SET NAMES utf8',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
			//PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		);

		try {
			parent::__construct($dsn, $user, $passwd, $options);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
		}
	}

	private function debug() {
		if(!empty($this->errorCallbackFunction)) {
			$error = array("Error" => $this->error);
			if(!empty($this->sql))
				$error["SQL Statement"] = $this->sql;
			if(!empty($this->bind))
				$error["Bind Parameters"] = trim(print_r($this->bind, true));

			$backtrace = debug_backtrace();
			if(!empty($backtrace)) {
				foreach($backtrace as $info) {
					if($info["file"] != __FILE__)
						$error["Backtrace"] = $info["file"] . " at line " . $info["line"];	
				}		
			}

			$msg = "";
			if($this->errorMsgFormat == "html") {
				if(!empty($error["Bind Parameters"]))
					$error["Bind Parameters"] = "<pre>" . $error["Bind Parameters"] . "</pre>";
				$css = trim(file_get_contents(dirname(__FILE__) . "/error.css"));
				$msg .= '<style type="text/css">' . "\n" . $css . "\n</style>";
				$msg .= "\n" . '<div class="db-error">' . "\n\t<h3>SQL Error</h3>";
				foreach($error as $key => $val)
					$msg .= "\n\t<label>" . $key . ":</label>" . $val;
				$msg .= "\n\t</div>\n</div>";
			}
			elseif($this->errorMsgFormat == "text") {
				$msg .= "SQL Error\n" . str_repeat("-", 3);
				foreach($error as $key => $val)
					$msg .= "\n\n$key:\n$val";
			}

			$func = $this->errorCallbackFunction;
			$func($msg);
		}
	}

	public function delete($table, $where, $bind="") {
		$sql = "DELETE FROM " . $table . " WHERE " . $where . ";";
		$this->run($sql, $bind)->check();
	}

	private function filter($table, $info) {
		$driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
		if($driver == 'sqlite') {
			$sql = "PRAGMA table_info('" . $table . "');";
			$key = "name";
		}
		elseif($driver == 'mysql') {
			$sql = "DESCRIBE " . $table . ";";
			$key = "Field";
		}
		else {	
			$sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '" . $table . "';";
			$key = "column_name";
		}	

		if(false !== ($list = $this->run($sql)->getAll(PDO::FETCH_OBJ))) {
			$fields = array();
			foreach($list as $record)
				$fields[] = $record->$key;

			return array_values(array_intersect($fields, array_keys($info)));
		}
		return array();
	}

	private function cleanup($bind) {
		if(!is_array($bind)) {
			if(!empty($bind))
				$bind = array($bind);
			else
				$bind = array();
		}
		return $bind;
	}

	public function insert($table, $info) {
      $GLOBALS["lastError"] = "";
		$fields = $this->filter($table, $info);
		$sql = "INSERT INTO " . $table . " (" . implode($fields, ", ") . ") VALUES (:" . implode($fields, ", :") . ");";
		$bind = array();
		foreach($fields as $field)
			$bind[":$field"] = $info[$field];
		return $this->run($sql, $bind)->check();
	}

	public function run($sql, $bind="") {
		$this->sql = trim($sql);
		$this->bind = $this->cleanup($bind);
		$this->error = "";
		$GLOBALS["lastError"] = "";
		

		try {
			$this->pdostmt = $pdostmt = $this->prepare($this->sql);
			if($pdostmt->execute($this->bind) !== false) {
				/*if(preg_match("/^(" . implode("|", array("select", "describe", "pragma")) . ") /i", $this->sql)) {
					switch ($fetchType) {
						case "one":
							return $this->fetchOne($pdostmt);
							
						case "row":
							return $this->fetchRow($pdostmt);
							
						case "col":
							return $this->fetchColumn($pdostmt);
              
            case "result":
              return $pdostmt;
							
						default:
							return $this->fetchAll($pdostmt);
					}
				
				} elseif(preg_match("/^(" . implode("|", array("delete", "insert", "update")) . ") /i", $this->sql)) {
					return $pdostmt->rowCount();
				}*/
			}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();	
			$this->debug();
			//return false;
		}
		
		return $this;
	}

  public function query($sql, $bind="") {
    return $this->run($sql, $bind);
  }

  public function getResult() {
    if (!empty($this->error)) {
      return false;
    }
    
    return $this->pdostmt;
  }
  
  public function rowCount() {
    if (!empty($this->error)) {
    	//var_dump($this->error);exit('w');
      return false;
    }
    
    return $this->pdostmt->rowCount();
  }

  public function check() {
    if (!empty($this->error)) {
      //var_dump($this->error);exit('w');
      return false;
    }
    
    return true;
  }
	
	public function getAll($resultType = PDO::FETCH_ASSOC) {
	  if (!empty($this->error)) {
			return false;
		}
    
		try {
  	  $res = $this->pdostmt->fetchAll($resultType);
   		$this->pdostmt->closeCursor();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();	
			$this->debug();
			return false;
		}
	
		return (!empty($res) ? $res : false);
	}
  
  // make first field in the query to be the key for the result
  public function getAllGroup($resultType = PDO::FETCH_ASSOC) {
    if (!empty($this->error)) {
      return false;
    }
    
    try {
      $res = $this->pdostmt->fetchAll($resultType|PDO::FETCH_GROUP);
      $this->pdostmt->closeCursor();
      foreach ($res as $k => $v) {
        $res[$k] = $res[$k][0];
      }
      
      if ($resultType == PDO::FETCH_OBJ) {
        $res = (object)$res;
      }
    } catch (PDOException $e) {
      $this->error = $e->getMessage();  
      $this->debug();
      return false;
    }
  
    return (!empty($res) ? $res : false);
  }
	
	// make first field in the query to be the key for the result
  public function getIndexedColumn($resultType = PDO::FETCH_ASSOC, $index = 1) {
    if (!empty($this->error)) {
      return false;
    }
    
    try {
      $res = $this->pdostmt->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP);
			print_r($res);exit;
      $this->pdostmt->closeCursor();
      foreach ($res as $k => $v) {
        $res[$k] = $res[$k][$index];
      }
      
      if ($resultType == PDO::FETCH_ASSOC) {
        $res = (object)$res;
      }
    } catch (PDOException $e) {
      $this->error = $e->getMessage();  
      $this->debug();
      return false;
    }
  
    return (!empty($res) ? $res : false);
  }
	
	public function getColumn($resultType = PDO::FETCH_COLUMN, $index = 0) {
	  if (!empty($this->error)) {
      return false;
    }
    
		try {
			$res = $this->pdostmt->fetchAll($resultType, $index);
			$this->pdostmt->closeCursor();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();	
			$this->debug();
			return false;
		}
	
		return (!empty($res) ? $res : false);
	}
	
	public function getRow($resultType = PDO::FETCH_ASSOC) {
		if (!empty($this->error)) {
      return false;
    }
    
		try {
			if(($row = $this->pdostmt->fetch()) && !empty($row)) {
				//$this->pdostmt->closeCursor();
				return $row;
			} else {
				$this->pdostmt->closeCursor();
				return false;
			}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();	
			$this->debug();
			return false;
		}
	
	}
	
	public function getOne($resultType = PDO::FETCH_ASSOC) {
	  if (!empty($this->error)) {
      return false;
    }
    
		try {
			if(($row = $this->pdostmt->fetch()) && !empty($row)) {
				$this->pdostmt->closeCursor();
				$row = (array)$row;
				reset($row);
				return current($row);
			} else {
				$this->pdostmt->closeCursor();
				return false;
			}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();	
			$this->debug();
			return false;
		}
	
		return false;
	}

	public function select($table, $where="", $bind="", $fields="*") {
		$sql = "SELECT " . $fields . " FROM " . $table;
		if(!empty($where))
			$sql .= " WHERE " . $where;
		$sql .= ";";
		return $this->run($sql, $bind);
	}

	public function setErrorCallbackFunction($errorCallbackFunction, $errorMsgFormat="html") {
		//Variable functions for won't work with language constructs such as echo and print, so these are replaced with print_r.
		if(in_array(strtolower($errorCallbackFunction), array("echo", "print")))
			$errorCallbackFunction = "print_r";

		if(function_exists($errorCallbackFunction)) {
			$this->errorCallbackFunction = $errorCallbackFunction;	
			if(!in_array(strtolower($errorMsgFormat), array("html", "text")))
				$errorMsgFormat = "html";
			$this->errorMsgFormat = $errorMsgFormat;	
		}	
	}

	public function update($table, $info, $where, $bind="") {
		$fields = $this->filter($table, $info);
		$fieldSize = sizeof($fields);

		$sql = "UPDATE " . $table . " SET ";
		for($f = 0; $f < $fieldSize; ++$f) {
			if($f > 0)
				$sql .= ", ";
			$sql .= $fields[$f] . " = :update_" . $fields[$f]; 
		}
		$sql .= " WHERE " . $where . ";";

		$bind = $this->cleanup($bind);
		foreach($fields as $field)
			$bind[":update_$field"] = $info[$field];

		return $this->run($sql, $bind)->check();
	}

   public function setResult($result) {
      if (!empty($result))
         $this->pdostmt = $result;
   }
}	
