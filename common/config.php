<?

// contain all db connections - every key is a new db object - $db1, $db2 etc.
$dbSettings = array(
	"db1" => array("host" => "192.168.0.5", "user" => "root1", "password" => "rootpass1", "dbName" => "mydb1"),
  "db2" => array("host" => "192.168.0.6", "user" => "root2", "password" => "rootpass2", "dbName" => "mydb2"),
);


define("basePath", "/var/www/site/");

// object relational mapping class
require_once(basePath."class/class.db.php");
require_once(basePath."class/class.dbWrapper.php");
require_once(basePath."class/class.inputfilter.php");
require_once(basePath."common/db.php");
require_once(basePath."class/validation.php");
require_once(basePath."class/class.baseTable.php");