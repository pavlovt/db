<?php

/**
 * A simplistic wrapper for PDO
 *
 * dbWrapper is a simplistic wrapper to use singleton pattern for db class.
 * As base is used https://github.com/digg/pdb
 *
 */
class dbWrapper {
    /**
     * Singleton connections
     *
     * @see PDB::singleton()
     * @var array $singletons
     */
    static protected $dbList = array();

    /**
     * Connect to a database
     *
	 * @param string $connectionName db alias - to be able to get it back with getDb method
     * @param string $dsn      PDO DSN (e.g. mysql:host=127.0.0.1:dbname=foo)
     * @param string $username The DB username 
     * @param string $password The DB password
     * @param array  $options  PDO options - not used
     *
     * @access public
     * @throws {@link PDB_Exception} when unable to connect
     * @link http://us.php.net/manual/en/pdo.constants.php
     * @link http://us.php.net/manual/en/pdo.construct.php
     * @return object Instance of PDB driver
     */
	public static function connect(
								$connectionName,
								$dsn, 
                                $username = null,
                                $password = null,
                                array $options = array()) 
    {
        return self::$dbList[$connectionName] = new db($dsn, $username, $password);
		
    }

    /**
     * Create a singleton DB connection
     *
     * @access public
     * @return object Instance of PDB driver
     * @throws {@link PDB_Exception} when unable to connect
     * @link http://us.php.net/manual/en/pdo.construct.php
     */
    static public function getDb($connectionName) {
	
        if (!isset(self::$dbList[$connectionName])) {
            throw new Exception("The database {$connectionName} is not defined");
        }

        return self::$dbList[$connectionName];
    }
	
} // class dbWrapper
