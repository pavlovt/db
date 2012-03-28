<?
// 20110818 -- pavlovt@wph.bg -- created

class baseTable {
  /* define class properties starts */
  public $primaryKey = "";
  public $tableName = ""; 
 
  public $selectFields = "";
  public $select = "";
  
  public $dataType = array();
  public $validationRules = array();

  public $db;
  public $result;
  public $p;

  public $totalRecords;
  public $error;

  /* define class properties ends */

  function __construct($return_object = false) {
    $this->reset();
    $this->return_object = $return_object;

  } // constructor

  private function reset() {
    $this->result = NULL;
    $this->p = array();

    $this->totalRecords = 0;

  } // reset

  public function next() {
    if (!empty($this->result) && ($this->p = $this->result->fetch())) {
      $this->p = $this->parseRecord($this->p);
      return ($this->return_object ? (object)$this->p : $this->p);
    }

    return false;
  } // next

  public function loadList($skip = null, $limit = null, $orderBy = null, $filter = '', $filterParams = array()) {
    // reset
    $this->reset();
    $db = dbWrapper::getDb('db');

    // update select string and format the additional FROM and where extentions
    $additionalOrderBy = $orderBy ? " ORDER BY ".trim($orderBy)." " : " ORDER BY {$this->tableName}.{$this->primaryKey}";

    if ((int)$limit) {
      $additionalLimit = " LIMIT ".(int)$skip.", ".(int)$limit." ";
    } else {
      $additionalLimit = "";
    }

    if (empty($filter))
      $filter = '';

    $q = 'SELECT '.$this->selectFields." ".$this->select." ".$filter." ".$additionalOrderBy." ".$additionalLimit;
    //echo $q."<br>"; //exit;
    
    if (!$this->result = $db->run($q, $filterParams)->getResult()) {
      return false;
    }

    if (!$this->totalRecords = $db->run("SELECT COUNT({$this->tableName}.{$this->primaryKey}) ".$this->select.$filter, $filterParams)->getOne()) {
      return false;
    }

    return true;

  } // loadList

  public function loadById($id) {
    $db = dbWrapper::getDb('db');
    $this->reset();

    $q = 'SELECT '.$this->selectFields." ".$this->select." AND {$this->tableName}.{$this->primaryKey} = :id";
		//exit($q);
    if (!$this->result = $db->run($q, array('id' => (int)$id))->getResult()) {
      return false;
    }

    return $this->next();

  } // loadById

  public function find($id) {
    return $this->loadById($id);
  }

  private function reload() {
    return $this->loadById($this->p[$this->primaryKey]);

  } // reload
  
  // define data and type validation
  public function isValid($data) {
    $this->errors = array();
    $this->lastError = '';
    
    // make shure this is not an object
    $data = (array)$data;
    
    // uses functions.validate.php
    $this->errors = validateFields($data, $this->validationRules);

    if(!empty($this->errors)) {
    	//var_dump($this->errors, $data);exit;
    	$this->lastError = implode("<br>", $this->errors);
      return false;
      
    } elseif(!$this->isValidCustom($data)) {
      return true;
      
    }

    return true;
    
  }
  
  // class custom validation
  private function isValidCustom($data) {
    return true;
    
  }

  public function createNew($data) {
    $db = dbWrapper::getDb('db');

    if (!$this->isValid($data)) {
      return false;
    }

    if (!$db->insert( $this->tableName, $data )) {
      $this->lastError = 'Error creating new record '.$GLOBALS['lastError'];
      return false;
    }

    if (!$this->loadById($db->lastInsertId())) {
      $this->lastError = 'Unable to load newly created record';
      return false;
    }

    return true;

  } // createNew

  public function isLoaded() {
    // make sure anything focused
    $p = (array)$this->p;
    if (!(int)$p[$this->primaryKey]) {
      $this->lastError = 'No record is loaded';
      return false;
    }

    // ok to update
    return true;

  } // isLoaded

  public function update($data) {
    $db = dbWrapper::getDb('db');
    $this->lastError = '';
      //echo "<pre>"; print_r($data); exit;
    if (!$this->isLoaded()) {
      return false;
    }

    if (!$this->isValid($data)) {
      return false;
    }

    if (!$db->update( $this->tableName, $data, "{$this->primaryKey} = ".(int)$this->p[$this->primaryKey] )) {
      $this->lastError = 'Db error: '.$GLOBALS['lastError'];
      return false;
    }

    if (!$this->reload()) {
      return false;
    }

    return true;

  } // update

  public function delete() {
    $db = dbWrapper::getDb('db');
    $p = (array)$this->p;

    if (!$this->isLoaded()) {
      return false;
    }

    $q = "DELETE FROM {$this->tableName} WHERE {$this->tableName}.{$this->primaryKey} = ".(int)$p[$this->primaryKey];

    if (!$db->run($q)) {
      $this->lastError = 'Error deleting the record '.$GLOBALS['lastError'];
      return false;
    }

    return true;

  } // delete

  public function parseRecord($r) {
    // p is object - make it array to be able to do the following operations
    $r = (array)$r;
    if (!empty($r) && stristr(implode(",", array_keys($r)),"json")) {
      foreach ($r as $k => $v) {
        if (stristr($k,'json')) {
          $r[$k] = @json_decode($r[$k], true);
        }
      }
    }

    //$r = (object)$r;
    
    return ($this->return_object ? (object)$r : $r);

  } // parseRecord

  public function getAll() {
    if (empty($this->result))
      return false;

    $return = PDO::FETCH_ASSOC;
    if($this->return_object)
      $return = PDO::FETCH_OBJ;

    $db = dbWrapper::getDb('db');
    $db->setResult($this->result);

    $all = $db->getAll($return);
    foreach ($all as $k => $v) {
      $all[$k] = $this->parseRecord($v);
    }

    return $all;

  }

  public function getIndexedColumn($column) {
    if (empty($this->result) || empty($column))
      return false;

    $db = dbWrapper::getDb('db');
    $db->setResult($this->result);

    $all = $db->getAllGroup($resultType = PDO::FETCH_ASSOC);
    foreach ($all as $k => $v) {
      $all[$k] = $v[$column];
    }

    return $all;

  }

  public function getColumn($column) {
    if (empty($this->result) || empty($column))
      return false;

    $db = dbWrapper::getDb('db');
    $db->setResult($this->result);

    $all = $db->getAll($resultType = PDO::FETCH_ASSOC);
    $result = array();
    foreach ($all as $v) {
      $result[] = $v[$column];
    }

    return $result;

  }

  public function emptyResult() {
    $this->totalRecords = 0;
    return array();

  }
} // baseTable