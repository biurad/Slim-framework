<?php

namespace Rlis\RadeBase;

use DateTime;
use mysqli_result;
use Radion\Config;
use Radion\Debugger;

/**
 * Class RadeBaseManager
 * This class wrappes MySQLi but it could be used for another framework/library.
 *
 * @version 3.24 20190206
 *
 * @author BiuStudios
 * @copyright (c) Jorge Castro C. MIT License
 *
 * @see https://github.com/biustudios/RadeBaseManager
 */
class RadeBase
{
    const NULL = PHP_INT_MAX;
    /** @var string|null Static date (when the date is empty) */
    public static $dateEpoch = '2000-01-01 00:00:00.00000';
    //<editor-fold desc="server fields">
    public $nodeId = 1;
    public $tableSequence = 'snowflake';
    /** @var string server ip. Ex. 127.0.0.1 */
    public $server;
    public $user;
    public $pwd;
    public $db;
    public $charset = '';
    /** @var bool It is true if the database is connected otherwise,it's false */
    public $isOpen = false;
    /** @var bool If true (default), then it throws an error if happens an error. If false, then the execution continues */
    public $throwOnError = true;

    /** @var \mysqli */
    public $conn1;
    //</editor-fold>
    //<editor-fold desc="encryption fields">
    /** @var bool Encryption enabled */
    public $encEnabled = false;
    /** @var string Encryption password */
    public $encPassword = '';
    /** @var string Encryption salt */
    public $encSalt = '';
    /** @var string Encryption method, See http://php.net/manual/en/function.openssl-get-cipher-methods.php */
    public $encMethod = '';
    //</editor-fold>
    /** @var bool */
    public $transactionOpen;
    /** @var bool if the database is in READ ONLY mode or not. If true then we must avoid to write in the database. */
    public $readonly = false;
    /** @var string full filename of the log file. If it's empty then it doesn't store a log file. The log file is limited to 1mb */
    public $logFile = '';
    /** @var int 0=no log (but error), 1=normal,2=verbose */
    public $logLevel = 2;

    /** @var string last query executed */
    public $lastQuery;
    public $lastParam = [];
    /**
     * Text date format.
     *
     * @var string
     *
     * @see https://secure.php.net/manual/en/function.date.php
     */
    public static $dateFormat = 'Y-m-d';
    /**
     * Text datetime format.
     *
     * @var string
     *
     * @see https://secure.php.net/manual/en/function.date.php
     */
    public static $dateTimeFormat = 'Y-m-d\TH:i:s\Z';
    /**
     * Text datetime format with microseconds.
     *
     * @var string
     *
     * @see https://secure.php.net/manual/en/function.date.php
     */
    public static $dateTimeMicroFormat = 'Y-m-d\TH:i:s.u\Z';

    private $genSqlFields = true;
    public $lastSqlFields = '';

    //<editor-fold desc="query builder fields">
    private $select = '';
    private $from = '';
    /** @var array */
    private $where = [];
    /** @var array */
    private $whereParamType = [];
    private $whereCounter = 0;
    /** @var array */
    private $whereParamValue = [];
    /** @var array */
    private $set = [];
    private $group = '';
    /** @var array */
    private $having = [];
    private $limit = '';

    private $distinct = '';
    private $order = '';
    //</editor-fold>

    /**
     * RadeBaseManager constructor.  It doesn't connect to the database.
     *
     * @param string $server  server ip. Ex. 127.0.0.1
     * @param string $user    Ex. root
     * @param string $pwd     Ex. 12345
     * @param string $db      Ex. mybase
     * @param string $logFile Optional  log file. Example c:\\temp\log.log
     * @param string $charset Example utf8mb4
     * @param int    $nodeId  It is the id of the node (server). It is used for sequence. Up to 4096
     *
     * @see RadeBaseManager::connect()
     */
    public function __construct($server = '', $user = '', $pwd = '', $db = '', $logFile = '', $charset = '', $nodeId = 1)
    {
        $radeconfig = [
            'server'        => Config::get('database', 'host'),
            'user'          => Config::get('database', 'username'),
            'pwd'           => Config::get('database', 'password'),
            'db'            => Config::get('database', 'database'),
            'charset'       => Config::get('database', 'charset'),

        ];

        $this->server = $radeconfig['server'];
        $this->user = $radeconfig['user'];
        $this->pwd = $radeconfig['pwd'];
        $this->db = $radeconfig['db'];
        $this->logFile = BR_PATH.'Resources/'.Config::get('theme', 'storage_path').'/logs/temp';
        $this->charset = $radeconfig['charset'];
        $this->nodeId = $nodeId;
    }

    /**
     * It changes default database.
     *
     * @param $dbName
     * @test void this('travisdb')
     */
    public function db($dbName)
    {
        if (!$this->isOpen) {
            return;
        }
        $this->db = $dbName;
        $this->conn1->select_db($dbName);
    }

    /**
     * It sets the charset of the database.
     *
     * @param string $charset Example 'utf8'
     * @test void this('utf8')
     */
    public function setCharset($charset)
    {
        if (!$this->isOpen) {
            return;
        }
        $this->charset = $charset;
        $this->conn1->set_charset($this->charset);
    }

    /**
     * returns if the database is in read-only mode or not.
     *
     * @return bool
     * @test equals false,this(),'the database is read only'
     */
    public function readonly()
    {
        return $this->readonly;
    }

    /**
     * Connects to the database.
     *
     * @param bool $failIfConnected true=it throw an error if it's connected, otherwise it does nothing
     *
     * @throws Debugger
     * @test Debugger this(false)
     */
    public function connect($failIfConnected = true)
    {
        if (Config::get('database', 'enabled') === true) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            if ($this->isOpen) {
                if (!$failIfConnected) {
                    return;
                } // it's already connected.
                $this->throwError('Already connected');
            }

            try {
                if ($this->logLevel >= 2) {
                    $this->storeInfo("connecting to {$this->server} {$this->user}/*** {$this->db}");
                }
                $this->conn1 = new \mysqli($this->server, $this->user, $this->pwd, $this->db);
                if ($this->charset != '') {
                    $this->setCharset($this->charset);
                }
                $this->isOpen = true;
            } catch (Debugger $ex) {
                $this->isOpen = false;
                $this->throwError("Failed to connect to MySQL:\t".$ex->getMessage());
            }
            // Check connection
            if (mysqli_connect_errno()) {
                $this->isOpen = false;
                $this->throwError("Failed to connect to MySQL:\t".mysqli_connect_error());
            }
        } else {
            header('HTTP/1.1 503 Service Unavailable.', true, 503);
            Debugger::display('wrong', 'Hmmmmm!', 'Database Connection is Missing, Please Fix');
            exit(1);
        }
    }

    /**
     * Alias of RadeBaseManager::connect().
     *
     * @param bool $failIfConnected
     *
     * @see RadeBaseManager::connect()
     *
     * @throws Debugger
     * @test Debugger this(false)
     */
    public function open($failIfConnected = true)
    {
        $this->connect($failIfConnected);
    }

    /**
     * It closes the connection.
     *
     * @test void this()
     */
    public function close()
    {
        $this->isOpen = false;
        if ($this->conn1 === null) {
            return;
        } // its already close
        @$this->conn1->close();
        @$this->conn1 = null;
    }

    /**
     * Injects a Message Container.
     *
     * @return MessageList|null
     * @test equals null,this(),'this is not a message container'
     */
    public function getMessages()
    {
        if (function_exists('messages')) {
            return messages();
        }
    }

    /**
     * Run many  unprepared query separated by ;.
     *
     * @param $listSql
     * @param bool $continueOnError
     *
     * @throws Debugger
     *
     * @return bool
     */
    public function runMultipleRawQuery($listSql, $continueOnError = false)
    {
        if (!$this->isOpen) {
            $this->throwError("It's not connected to the database");

            return false;
        }
        $arr = explode(';', $listSql);
        $ok = true;
        foreach ($arr as $rawSql) {
            if (trim($rawSql) != '') {
                if ($this->readonly) {
                    if (stripos($rawSql, 'insert ') === 0 || stripos($rawSql, 'update ') === 0 || stripos($rawSql, 'delete ') === 0) {
                        // we aren't checking SQL-DCL queries. Also, "insert into" is stopped but "  insert into" not.
                        $ok = false;
                        if (!$continueOnError) {
                            $this->throwError('Database is in READ ONLY MODE');
                        }
                    }
                }
                if ($this->logLevel >= 2) {
                    $this->storeInfo($rawSql);
                }
                $r = $this->conn1->query($rawSql);
                if ($r === false) {
                    $ok = false;
                    if (!$continueOnError) {
                        $this->throwError("Unable to run raw query\t".$this->lastQuery);
                    }
                }
            }
        }

        return $ok;
    }

    /**
     * It returns the next sequence.
     *
     * @param bool $asFloat
     *
     * @throws Debugger
     *
     * @return string . Example string(19) "3639032938181434317"
     */
    public function getSequence($asFloat = false)
    {
        $sql = "select next_{$this->tableSequence}({$this->nodeId}) id";
        $r = $this->runRawQuery($sql, null, true);
        if ($asFloat) {
            return floatval($r[0]['id']);
        } else {
            return $r[0]['id'];
        }
    }

    /**
     * Create a table for a sequence.
     *
     * @throws Debugger
     */
    public function createSequence()
    {
        $sql = "CREATE TABLE `{$this->tableSequence}` (
		  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  `stub` char(1) NOT NULL DEFAULT '',
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `stub` (`stub`)
		) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
		-- insert the firsrt value
		INSERT INTO `{$this->tableSequence}` (`stub`) VALUES ('a');
		SET GLOBAL log_bin_trust_function_creators = 1;";
        $this->runMultipleRawQuery($sql);
        $sql = "CREATE FUNCTION `next_{$this->tableSequence}`(node integer) RETURNS BIGINT(20)
			BEGIN
			    DECLARE epoch BIGINT(20);
			    DECLARE current_ms BIGINT(20);
			    DECLARE incr BIGINT(20);
			    SET current_ms = round(UNIX_TIMESTAMP(CURTIME(4)) * 1000);
			    SET epoch = 1459440000000; 
			    REPLACE INTO {$this->tableSequence} (stub) VALUES ('a');
			    SELECT LAST_INSERT_ID() INTO incr;    
			RETURN (current_ms - epoch) << 22 | (node << 12) | (incr % 4096);
			END;";
        $this->runRawQuery($sql);
    }

    //<editor-fold desc="transaction functions">

    /**
     * @param int $flag MYSQLI_TRANS_START_READ_ONLY,MYSQLI_TRANS_START_READ_WRITE,MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT
     *
     * @return bool
     * @test equals true,this()
     * @posttest execution $this->RadeBaseManager->commit();
     *
     * @example examples/testdb.php 92,4
     */
    public function startTransaction($flag = MYSQLI_TRANS_START_READ_WRITE)
    {
        if ($this->transactionOpen || !$this->isOpen) {
            return false;
        }
        $this->transactionOpen = true;
        $this->conn1->begin_transaction($flag);

        return true;
    }

    /**
     * Commit and close a transaction.
     *
     * @param bool $throw
     *
     * @throws Debugger
     *
     * @return bool
     * @test equals false,(false),'transaction is not open'
     */
    public function commit($throw = true)
    {
        if (!$this->transactionOpen && $throw) {
            $this->throwError('Transaction not open to commit()');

            return false;
        }
        if (!$this->isOpen) {
            $this->throwError("It's not connected to the database");

            return false;
        }
        $this->transactionOpen = false;

        return @$this->conn1->commit();
    }

    /**
     * Rollback and close a transaction.
     *
     * @param bool $throw
     *
     * @throws Debugger
     *
     * @return bool
     * @test equals false,(false),'transaction is not open'
     */
    public function rollback($throw = true)
    {
        if (!$this->transactionOpen && $throw) {
            $this->throwError('Transaction not open  to rollback()');
        }
        if (!$this->isOpen) {
            $this->throwError("It's not connected to the database");

            return false;
        }
        $this->transactionOpen = false;

        return @$this->conn1->rollback();
    }

    //</editor-fold>

    //<editor-fold desc="Date functions" defaultstate="collapsed" >

    /**
     * Conver date from php -> mysql
     * It always returns a time (00:00:00 if time is empty). it could returns microseconds 2010-01-01 00:00:00.00000.
     *
     * @param DateTime $date
     *
     * @return string
     */
    public static function dateTimePHP2Sql($date)
    {
        // 31/01/2016 20:20:00 --> 2016-01-31 00:00
        if ($date == null) {
            return self::$dateEpoch;
        }
        if ($date->format('u') != '000000') {
            return $date->format('Y-m-d H:i:s.u');
        } else {
            return $date->format('Y-m-d H:i:s');
        }
    }

    /**
     * Convert date from unix -> mysql.
     *
     * @param int $dateNum
     *
     * @return string
     */
    public static function unixtime2Sql($dateNum)
    {
        // 31/01/2016 20:20:00 --> 2016-01-31 00:00
        if ($dateNum == null) {
            return self::$dateEpoch;
        }

        try {
            $date2 = new DateTime(date('Y-m-d H:i:s.u', $dateNum));
        } catch (Debugger $e) {
            return self::$dateEpoch;
        }

        return $date2->format('Y-m-d H:i:s.u');
    }

    /**
     * Convert date, from mysql -> php.
     *
     * @param $sqlField
     * @param bool $hasTime
     *
     * @return bool|DateTime|null
     */
    public static function dateTimeSql2PHP($sqlField, &$hasTime = false)
    {
        // 3  2016-01-31 00:00:00 -> 01/01/2016 00:00:00
        // mysql always returns the date/datetime/timestmamp in ansi format.
        if ($sqlField === '' || $sqlField === null) {
            if (self::$dateEpoch === null) {
                return;
            }

            return DateTime::createFromFormat('Y-m-d H:i:s.u', self::$dateEpoch);
        }
        if (strpos($sqlField, '.')) {
            // with date with time and microseconds
            $hasTime = true;

            return DateTime::createFromFormat('Y-m-d H:i:s.u', $sqlField);
        } else {
            if (strpos($sqlField, ':')) {
                // date with time
                $hasTime = true;

                return DateTime::createFromFormat('Y-m-d H:i:s', $sqlField);
            } else {
                // only date
                $hasTime = false;

                return DateTime::createFromFormat('Y-m-d', $sqlField);
            }
        }
    }

    /**
     * Convert date, from mysql -> text (using a format pre-established).
     *
     * @param $sqlField
     *
     * @return string
     */
    public static function dateSql2Text($sqlField)
    {
        $hasTime = false;
        $tmpDate = self::dateTimeSql2PHP($sqlField, $hasTime);
        if ($tmpDate === null) {
            return;
        }
        if ($hasTime) {
            return $tmpDate->format((strpos($sqlField, '.') !== false) ? self::$dateTimeMicroFormat : self::$dateTimeFormat);
        } else {
            return $tmpDate->format(self::$dateFormat);
        }
    }

    /**
     * Convert date, from mysql -> text (using a format pre-established).
     *
     * @param $textDate
     * @param bool $hasTime
     *
     * @return string
     */
    public static function dateText2Sql($textDate, $hasTime = true)
    {
        $tmpFormat = (($hasTime)
                ? (strpos($textDate, '.') === false
                    ? self::$dateTimeFormat
                    : self::$dateTimeMicroFormat)
                : self::$dateFormat);
        $tmpDate = DateTime::createFromFormat($tmpFormat, $textDate);
        if (!$hasTime && $tmpDate) {
            $tmpDate->setTime(0, 0, 0);
        }

        return self::dateTimePHP2Sql($tmpDate); // it always returns a date with time. Mysql Ignores it.
    }

    /**
     * Returns the current date(and time) in Text format.
     *
     * @param bool $hasTime
     * @param bool $hasMicroseconds
     *
     * @throws Debugger
     *
     * @return string
     *
     * @see RadeBaseManager::$dateTimeFormat
     */
    public static function dateTextNow($hasTime = true, $hasMicroseconds = false)
    {
        $tmpDate = new DateTime();
        if ($hasTime) {
            return $tmpDate->format(($hasMicroseconds !== false) ? self::$dateTimeMicroFormat : self::$dateTimeFormat);
        } else {
            return $tmpDate->format(self::$dateFormat);
        }
    }

    //</editor-fold>

    //<editor-fold desc="Query Builder functions" defaultstate="collapsed" >

    /**
     * @param string|array $sql
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('select 1 from DUAL')
     */
    public function select($sql)
    {
        if (is_array($sql)) {
            $this->select .= implode(', ', $sql);
        } else {
            $this->select .= $sql;
        }

        return $this;
    }

    /**
     * It generates an inner join.
     *
     * @param string $sql Example "tablejoin on table1.field=tablejoin.field"
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('tablejoin on t1.field=t2.field')
     */
    public function join($sql)
    {
        if ($this->from == '') {
            return $this->from($sql);
        }
        $this->from .= ($sql) ? " inner join $sql " : '';

        return $this;
    }

    /**
     * Macro of join.
     *
     * @param $sql
     *
     * @return RadeBaseManager
     */
    public function innerjoin($sql)
    {
        return $this->join($sql);
    }

    /**
     * @param $sql
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('table t1')
     */
    public function from($sql)
    {
        $this->from = ($sql) ? $sql : '';

        return $this;
    }

    /**
     * @param $sql
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('table2 on table1.t1=table2.t2')
     */
    public function left($sql)
    {
        if ($this->from == '') {
            return $this->from($sql);
        }
        $this->from .= ($sql) ? " left join $sql" : '';

        return $this;
    }

    /**
     * @param $sql
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('table2 on table1.t1=table2.t2')
     */
    public function right($sql)
    {
        if ($this->from == '') {
            return $this->from($sql);
        }
        $this->from .= ($sql) ? " right join $sql" : '';

        return $this;
    }

    /**
     * Example:<br>
     * where(['field'=>20]) // associative array with automatic type<br>
     * where(['field'=>['i',20]]) // associative array with type defined<br>
     * where(['field',20]) // array automatic type<br>
     * where(['field',['i',20]]) // array type defined<br>
     * where('field=20') // literal value<br>
     * where('field=?',[20]) // automatic type<br>
     * where('field=?',['i',20]) // type(i,d,s,b) defined<br>
     * where('field=?,field2=?',['i',20,'s','hello'])<br>.
     *
     * @param string|array $sql
     * @param array|mixed  $param
     *
     * @return RadeBaseManager
     *
     * @see http://php.net/manual/en/mysqli-stmt.bind-param.php for types
     * @test InstanceOf RadeBaseManager::class,this('field1=?,field2=?',['i',20,'s','hello'])
     */
    public function where($sql, $param = self::NULL)
    {
        if (is_string($sql)) {
            $this->where[] = $sql;
            if ($param === self::NULL) {
                return $this;
            }
            switch (true) {
                case !is_array($param):
                    $this->whereParamType[] = $this->getType($param);
                    $this->whereParamValue['i_'.$this->whereCounter] = $param;
                    $this->whereCounter++;
                    break;
                case count($param) == 1:
                    $this->whereParamType[] = $this->getType($param[0]);
                    $this->whereParamValue['i_'.$this->whereCounter] = $param[0];
                    $this->whereCounter++;
                    break;
                default:
                    for ($i = 0; $i < count($param); $i += 2) {
                        $this->whereParamType[] = $param[$i];
                        $this->whereParamValue['i_'.$this->whereCounter] = $param[$i + 1];
                        $this->whereCounter++;
                    }
            }
        } else {
            $col = [];
            $colT = [];
            $p = [];
            $this->constructParam($sql, $param, $col, $colT, $p);

            foreach ($col as $k=> $c) {
                $this->where[] = "`$c`=?";
                $this->whereParamType[] = $p[$k * 2];
                $this->whereParamValue['i_'.$this->whereCounter] = $p[$k * 2 + 1];
                $this->whereCounter++;
            }
        }

        return $this;
    }

    /**
     * @param string|array $sqlOrArray
     * @param array|mixed  $param
     *
     * @throws Debugger
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('field1=?,field2=?',['i',20,'s','hello'])
     */
    public function set($sqlOrArray, $param = self::NULL)
    {
        if (count($this->where)) {
            $this->throwError("you can't execute set() after a where()");
        }
        if (is_string($sqlOrArray)) {
            $this->set[] = $sqlOrArray;
            // self::NULL  is used when no value is set. We can't use null because it is a valid option.
            if ($param === self::NULL) {
                return $this;
            }
            if (is_array($param)) {
                for ($i = 0; $i < count($param); $i += 2) {
                    $this->whereParamType[] = $param[$i];
                    $this->whereParamValue['i_'.$this->whereCounter] = $param[$i + 1];
                    $this->whereCounter++;
                }
            } else {
                $this->whereParamType[] = 's';
                $this->whereParamValue['i_'.$this->whereCounter] = $param;
                $this->whereCounter++;
            }
        } else {
            $col = [];
            $colT = [];
            $p = [];
            $this->constructParam($sqlOrArray, $param, $col, $colT, $p);
            foreach ($col as $k=> $c) {
                $this->set[] = "`$c`=?";
                $this->whereParamType[] = $p[$k * 2];
                $this->whereParamValue['i_'.$this->whereCounter] = $p[$k * 2 + 1];
                $this->whereCounter++;
            }
        }

        return $this;
    }

    /**
     * @param $sql
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('fieldgroup')
     */
    public function group($sql)
    {
        $this->group = ($sql) ? ' group by '.$sql : '';

        return $this;
    }

    /**
     * @param $sql
     * @param array|mixed $param
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('field1=?,field2=?',['i',20,'s','hello'])
     * * @test InstanceOf RadeBaseManager::class,this('field1=?','hello')
     */
    public function having($sql, $param = self::NULL)
    {
        $this->having[] = $sql;
        if ($param === self::NULL) {
            return $this;
        }
        if (is_array($param)) {
            for ($i = 0; $i < count($param); $i += 2) {
                $this->whereParamType[] = $param[$i];
                $this->whereParamValue['i_'.$this->whereCounter] = $param[$i + 1];
                $this->whereCounter++;
            }
        } else {
            $this->whereParamType[] = 's';
            $this->whereParamValue['i_'.$this->whereCounter] = $param;
            $this->whereCounter++;
        }

        return $this;
    }

    /**
     * @param $sql
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('name desc')
     */
    public function order($sql)
    {
        $this->order = ($sql) ? ' order by '.$sql : '';

        return $this;
    }

    /**
     * @param $sql
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this('1,10')
     */
    public function limit($sql)
    {
        $this->limit = ($sql) ? ' limit '.$sql : '';

        return $this;
    }

    /**
     * @param $sql
     *
     * @return RadeBaseManager
     * @test InstanceOf RadeBaseManager::class,this()
     */
    public function distinct($sql = 'distinct')
    {
        $this->distinct = ($sql) ? $sql.' ' : '';

        return $this;
    }

    /**
     * It returns an array of rows.
     *
     * @throws Debugger
     *
     * @return array|bool
     */
    public function toList()
    {
        return $this->runGen(true);
    }

    /**
     * Run builder query and returns a mysqli_result.
     *
     * @param bool $returnArray true=return an array. False return a mysqli_result
     *
     * @throws Debugger
     *
     * @return bool|\mysqli_result
     */
    public function runGen($returnArray = true)
    {
        $sql = $this->sqlGen();
        /** @var \mysqli_stmt $stmt */
        $stmt = $this->prepare($sql);
        if ($stmt === null) {
            return false;
        }
        $parType = implode('', $this->whereParamType);
        $values = array_values($this->whereParamValue);
        if ($parType) {
            $reval = $stmt->bind_param($parType, ...$values);
            if (!$reval) {
                $this->throwError('Error in bind');

                return false;
            }
        }
        $this->runQuery($stmt);
        $rows = $stmt->get_result();
        if ($this->genSqlFields) {
            $this->lastSqlFields = $this->obtainSqlFields($rows);
        }
        $stmt->close();
        $this->builderReset();
        if ($returnArray) {
            $r = $rows->fetch_all(MYSQLI_ASSOC);
            $rows->free_result();

            return $r;
        } else {
            return $rows;
        }
    }

    /**
     * @param bool $genSqlFields
     *
     * @return $this
     * @test InstanceOf RadeBaseManager::class,this(true)
     */
    public function generateSqlFields($genSqlFields = true)
    {
        $this->genSqlFields = $genSqlFields;

        return $this;
    }

    /**
     * @param mysqli_result $rows
     *
     * @return string
     */
    private function obtainSqlFields($rows)
    {
        if (!$rows) {
            return '';
        }
        $fields = $rows->fetch_fields();
        $r = '';
        foreach ($fields as $f) {
            if ($f->orgname != $f->name) {
                $r .= "`{$f->table}`.`$f->orgname` `$f->name`, ";
            } else {
                $r .= "`{$f->table}`.`$f->orgname`, ";
            }
        }

        return trim($r, " \t\n\r\0\x0B,");
    }

    /**
     * Generates the sql to run.
     *
     * @return string
     */
    public function sqlGen()
    {
        if (count($this->where)) {
            $where = ' where '.implode(' and ', $this->where);
        } else {
            $where = '';
        }
        if (count($this->having)) {
            $having = ' having '.implode(' and ', $this->having);
        } else {
            $having = '';
        }
        $sql = 'select '.$this->distinct.$this->select.' from '.$this->from.$where.$this->group.$having.$this->order.$this->limit;

        return $sql;
    }

    /**
     * @param $query string
     *
     * @throws Debugger
     *
     * @return \mysqli_stmt returns the statement if correct otherwise null
     */
    public function prepare($query)
    {
        if (!$this->isOpen) {
            $this->throwError("It's not connected to the database");

            return;
        }
        $this->lastParam = [];
        $this->lastQuery = $query;
        if ($this->readonly) {
            if (stripos($query, 'insert ') === 0 || stripos($query, 'update ') === 0 || stripos($query, 'delete ') === 0) {
                // we aren't checking SQL-DCL queries.
                $this->throwError('Database is in READ ONLY MODE');
            }
        }
        if ($this->logLevel >= 2) {
            $this->storeInfo($query);
        }

        try {
            $stmt = $this->conn1->prepare($query);
        } catch (Debugger $ex) {
            $stmt = false;
            $this->throwError('Failed to prepare:'.$ex->getMessage());
        }
        if ($stmt === false) {
            $this->throwError('Unable to prepare query'.$this->lastQuery);
        }

        return $stmt;
    }

    /**
     * Run a prepared statement.
     *
     * @param $stmt \mysqli_stmt
     *
     * @throws Debugger
     *
     * @return bool returns true if the operation is correct, otherwise false
     * @test equals true,$this->RadeBaseManager->runQuery($this->RadeBaseManager->prepare('select 1 from dual'))
     * @test equals [1=>1],$this->RadeBaseManager->select('1')->from('dual')->first(),'it must runs'
     */
    public function runQuery($stmt)
    {
        if (!$this->isOpen) {
            $this->throwError("It's not connected to the database");

            return;
        }

        try {
            $r = $stmt->execute();
        } catch (Debugger $ex) {
            $r = false;
            $this->throwError("Failed to run query\t".$this->lastQuery.' Cause:'.$ex->getMessage());
        }
        if ($r === false) {
            $this->throwError("Debugger query\t".$this->lastQuery);
        }

        return true;
    }

    /**
     * It reset the parameters used to Build Query.
     */
    private function builderReset()
    {
        $this->select = '';
        $this->from = '';
        $this->where = [];
        $this->whereParamType = [];
        $this->whereCounter = 0;
        $this->whereParamValue = [];
        $this->set = [];
        $this->group = '';
        $this->having = [];
        $this->limit = '';
        $this->distinct = '';
        $this->order = '';
    }

    /**
     * It returns a mysqli_result.
     *
     * @throws Debugger
     *
     * @return \mysqli_result
     */
    public function toResult()
    {
        return $this->runGen(false);
    }

    /**
     * It returns the first row.  If there is not row then it returns empty.
     *
     * @throws Debugger
     *
     * @return array|null
     */
    public function first()
    {
        /** @var \mysqli_result $rows */
        $rows = $this->runGen(false);
        if ($rows === false) {
            return;
        }
        while ($row = $rows->fetch_assoc()) {
            $rows->free_result();

            return $row;
        }
    }

    /**
     * Executes the query, and returns the first column of the first row in the result set returned by the query. Additional columns or rows are ignored.
     *
     * @throws Debugger
     *
     * @return mixed|null
     */
    public function firstScalar()
    {
        /** @var \mysqli_result $rows */
        $rows = $this->runGen(false);
        if ($rows === false) {
            return;
        }
        while ($row = $rows->fetch_assoc()) {
            $rows->free_result();

            return reset($row);
        }
    }

    /**
     * Returns the last row. It's not recommended. Use instead first() and change the order.
     *
     * @throws Debugger
     *
     * @return array|null
     */
    public function last()
    {
        /** @var \mysqli_result $rows */
        $rows = $this->runGen(false);
        if ($rows === false) {
            return;
        }
        $row = null;
        while ($row = $rows->fetch_assoc()) {
            $rows->free_result();
        }

        return $row;
    }

    /**
     * Run an unprepared query.
     *
     * @param string     $rawSql
     * @param array|null $param
     * @param bool       $returnArray
     *
     * @throws Debugger
     *
     * @return bool|\mysqli_result|array an array of associative or a mysqli_result
     * @test equals [0=>[1=>1]],this('select 1',null,true)
     */
    public function runRawQuery($rawSql, $param = null, $returnArray = true)
    {
        if (!$this->isOpen) {
            $this->throwError("It's not connected to the database");

            return false;
        }
        if ($this->readonly) {
            if (stripos($rawSql, 'insert ') === 0 || stripos($rawSql, 'update ') === 0 || stripos($rawSql, 'delete ') === 0) {
                // we aren't checking SQL-DCL queries. Also, "insert into" is stopped but "  insert into" not.
                $this->throwError('Database is in READ ONLY MODE');
            }
        }
        $this->lastParam = $param;
        $this->lastQuery = $rawSql;
        if ($this->logLevel >= 2) {
            $this->storeInfo($rawSql);
        }
        if ($param === null) {
            // the "where" chain doesn't have parameters.
            try {
                $rows = $this->conn1->query($rawSql);
            } catch (Debugger $ex) {
                $rows = false;
                $this->throwError("Debugger raw\t".$rawSql);
            }
            if ($rows === false) {
                $this->throwError("Unable to run raw query\t".$rawSql);
            }
            if ($returnArray && $rows instanceof \mysqli_result) {
                return $rows->fetch_all(MYSQLI_ASSOC);
            } else {
                return $rows;
            }
        }
        // the "where" has parameters.
        $stmt = $this->prepare($rawSql);
        $parType = '';
        $values = [];
        for ($i = 0; $i < count($param); $i += 2) {
            $parType .= $param[$i];
            $values[] = $param[$i + 1];
        }
        $stmt->bind_param($parType, ...$values);
        $this->runQuery($stmt);
        $rows = $stmt->get_result();
        if ($this->genSqlFields) {
            $this->lastSqlFields = $this->obtainSqlFields($rows);
        }
        $stmt->close();
        if ($returnArray && $rows instanceof \mysqli_result) {
            return $rows->fetch_all(MYSQLI_ASSOC);
        } else {
            return $rows;
        }
    }

    /**
     * Returns the last inserted identity.
     *
     * @return mixed
     */
    public function insert_id()
    {
        if (!$this->isOpen) {
            return -1;
        }

        return $this->conn1->insert_id;
    }

    /**
     * Returns the number of affected rows.
     *
     * @return mixed
     */
    public function affected_rows()
    {
        if (!$this->isOpen) {
            return -1;
        }

        return $this->conn1->affected_rows;
    }

    /**
     * Generate and run an update in the database.
     * <code>
     * update('table',['col1','i',10,'col2','s','hello world'],['where','i',10]);
     * // or
     * update('table',['col1','i','col2','s'],[10,'hello world'],['where','i'],[10]);
     * </code>.
     *
     * @param string       $table
     * @param string[]     $tableDef
     * @param string[]|int $value
     * @param string[]     $tableDefWhere
     * @param string[]|int $valueWhere
     *
     * @throws Debugger
     *
     * @return mixed
     */
    public function update($table = null, $tableDef = null, $value = self::NULL, $tableDefWhere = null, $valueWhere = self::NULL)
    {
        if ($table === null) {
            // using builder. from()->set()->where()->update()
            $errorCause = '';
            if ($this->from == '') {
                $errorCause = "you can't execute an empty update() without a from()";
            }
            if (count($this->set) === 0) {
                $errorCause = "you can't execute an empty update() without a set()";
            }
            if (count($this->where) === 0) {
                $errorCause = "you can't execute an empty update() without a where()";
            }
            if ($errorCause) {
                $this->throwError($errorCause);

                return false;
            }
            $sql = 'update `'.$this->from.'` '.$this->constructSet().' '.$this->constructWhere();
            $param = [];
            for ($i = 0; $i < count($this->whereParamType); $i++) {
                $param[] = $this->whereParamType[$i];
                $param[] = $this->whereParamValue['i_'.$i];
            }
            $this->builderReset();
            $this->runRawQuery($sql, $param, true);

            return $this->affected_rows();
        } else {
            $col = [];
            $colT = null;
            $colWhere = [];
            $param = [];
            if ($tableDefWhere === null) {
                $this->constructParam($tableDef, self::NULL, $col, $colT, $param);
                $this->constructParam($value, self::NULL, $colWhere, $colT, $param);
            } else {
                $this->constructParam($tableDef, $value, $col, $colT, $param);
                $this->constructParam($tableDefWhere, $valueWhere, $colWhere, $colT, $param);
            }
            $sql = "update `$table` set ".implode(',', $col).' where '.implode(' and ', $colWhere);
            $this->builderReset();
            $this->runRawQuery($sql, $param);

            return $this->insert_id();
        }
    }

    /**
     * Generates and execute an insert command. Example:
     * <code>
     * insert('table',['col1','i',10,'col2','s','hello world']);
     * // or
     * insert('table',['col1','i','col2','s'],[10,'hello world']);
     * // or
     * insert('table',['col1'=>'i','col2'=>'s'],['col1'=>10,'col2'=>'hello world']);
     * // or
     * ->set(..)->from('table')->insert();
     * </code>.
     *
     * @param string       $table
     * @param string[]     $tableDef
     * @param string[]|int $value
     *
     * @throws Debugger
     *
     * @return mixed
     */
    public function insert($table = null, $tableDef = null, $value = self::NULL)
    {
        if ($table === null) {
            // using builder. from()->set()->insert()
            $errorCause = '';
            if ($this->from == '') {
                $errorCause = "you can't execute an empty insert() without a from()";
            }
            if (count($this->set) === 0) {
                $errorCause = "you can't execute an empty insert() without a set()";
            }
            if ($errorCause) {
                $this->throwError($errorCause);

                return false;
            }
            $sql = /* @lang text */'insert into `'.$this->from.'` '.$this->constructInsert();
            $param = [];

            for ($i = 0; $i < count($this->whereParamType); $i++) {
                $param[] = $this->whereParamType[$i];
                $param[] = $this->whereParamValue['i_'.$i];
            }
            $this->builderReset();
            $this->runRawQuery($sql, $param, true);

            return $this->insert_id();
        } else {
            $col = [];
            $colT = [];
            $param = [];
            $this->constructParam($tableDef, $value, $col, $colT, $param);
            $sql = "insert into `$table` (".implode(',', $col).') values('.implode(',', $colT).')';
            $this->builderReset();
            $this->runRawQuery($sql, $param);

            return $this->insert_id();
        }
    }

    /**
     * <code>
     * delete('table',['col1','i',10,'col2','s','hello world']);
     * // or
     * delete('table',['col1','i','col2','s'],[10,'hello world']);
     * // or
     * delete() if run on a chain $db->from('table')->where('..')->delete()
     * </code>.
     *
     * @param string       $table
     * @param string[]     $tableDefWhere
     * @param string[]|int $valueWhere
     *
     * @throws Debugger
     *
     * @return mixed
     */
    public function delete($table = null, $tableDefWhere = null, $valueWhere = self::NULL)
    {
        if ($table === null) {
            // using builder. from()->where()->delete()
            $errorCause = '';
            if ($this->from == '') {
                $errorCause = "you can't execute an empty delete() without a from()";
            }
            if (count($this->where) === 0) {
                $errorCause = "you can't execute an empty delete() without a where()";
            }
            if ($errorCause) {
                $this->throwError($errorCause);

                return false;
            }
            $sql = 'delete from `'.$this->from.'` '.$this->constructWhere();
            $param = [];
            for ($i = 0; $i < count($this->whereParamType); $i++) {
                $param[] = $this->whereParamType[$i];
                $param[] = $this->whereParamValue['i_'.$i];
            }
            $this->builderReset();
            $this->runRawQuery($sql, $param, true);

            return $this->affected_rows();
        } else {
            // using table/tabldefwhere/valuewhere
            $colWhere = [];
            $colT = null;
            $param = [];
            $this->constructParam($tableDefWhere, $valueWhere, $colWhere, $colT, $param);
            $sql = "delete from `$table` where ".implode(' and ', $colWhere);
            $this->builderReset();
            $this->runRawQuery($sql, $param, true);

            return $this->affected_rows();
        }
    }

    /**
     * @return string
     */
    private function constructWhere()
    {
        if (count($this->where)) {
            $where = ' where '.implode(' and ', $this->where);
        } else {
            $where = '';
        }

        return $where;
    }

    /**
     * @return string
     */
    private function constructSet()
    {
        if (count($this->set)) {
            $where = ' set '.implode(',', $this->set);
        } else {
            $where = '';
        }

        return $where;
    }

    /**
     * @return string
     */
    private function constructInsert()
    {
        if (count($this->set)) {
            $arr = [];
            $val = [];
            $first = $this->set[0];
            if (strpos($first, '=') !== false) {
                // set([])
                foreach ($this->set as $v) {
                    $tmp = explode('=', $v);
                    $arr[] = $tmp[0];
                    $val[] = $tmp[1];
                }
                $where = '('.implode(',', $arr).') values ('.implode(',', $val).')';
            } else {
                // set('(a,b,c) values(?,?,?)',[])
                $where = $first;
            }
        } else {
            $where = '';
        }

        return $where;
    }

    /**
     * @param array     $array1
     * @param array|int $array2 if value is self::NULL then it's calculated without this value
     * @param array     $col
     * @param array     $colT
     * @param array     $param
     */
    private function constructParam($array1, $array2, &$col, &$colT, &$param)
    {
        if ($this->isAssoc($array1)) {
            if ($array2 === self::NULL) {
                // the type is calculated automatically. It could fails and it doesn't work with blob
                foreach ($array1 as $k => $v) {
                    if ($colT === null) {
                        $col[] = "`$k`=?";
                    } else {
                        $col[] = $k;
                        $colT[] = '?';
                    }
                    $vt = $this->getType($v);
                    $param[] = $vt;
                    $param[] = $v;
                }
            } else {
                // it uses two associative array, one for the type and another for the value
                foreach ($array1 as $k => $v) {
                    if ($colT === null) {
                        $col[] = "`$k`=?";
                    } else {
                        $col[] = $k;
                        $colT[] = '?';
                    }

                    $param[] = $v;
                    $param[] = @$array2[$k];
                }
            }
        } else {
            if ($array2 === self::NULL) {
                // it uses a single list, the first value is the column, the second value
                // is the type and the third is the value
                for ($i = 0; $i < count($array1); $i += 3) {
                    if ($colT === null) {
                        $col[] = '`'.$array1[$i].'`=?';
                    } else {
                        $col[] = $array1[$i];
                        $colT[] = '?';
                    }
                    $param[] = $array1[$i + 1];
                    $param[] = $array1[$i + 2];
                }
            } else {
                // it uses two list, the first value of the first list is the column, the second value is the type
                // , the second list only contains values.
                for ($i = 0; $i < count($array1); $i += 2) {
                    if ($colT === null) {
                        $col[] = '`'.$array1[$i].'`=?';
                    } else {
                        $col[] = $array1[$i];
                        $colT[] = '?';
                    }
                    $param[] = $array1[$i + 1];
                    $param[] = $array2[$i / 2];
                }
            }
        }
    }

    /**
     * @param mixed $v Variable
     *
     * @return string
     * @test equals 'd',(20.3)
     * @test equals 'ds',('hello')
     */
    private function getType(&$v)
    {
        switch (1) {
            case $v === null:
                $vt = 's';
                break;
            case is_float($v):
                $vt = 'd';
                break;
            case is_numeric($v):
                $vt = 'i';
                break;
            case is_bool($v):
                $vt = 'i';
                $v = ($v) ? 1 : 0;
                break;
            case is_object($v) && get_class($v) == 'DateTime':
                $vt = 's';
                $v = self::dateTimePHP2Sql($v);
                break;
            default:
                $vt = 's';
        }

        return $vt;
    }

    private function isAssoc($array)
    {
        return array_values($array) !== $array;
    }

    //</editor-fold>

    //<editor-fold desc="Encryption functions" defaultstate="collapsed" >

    /**
     * @param string $password
     * @param string $salt
     * @param string $encMethod . Example : AES-128-CTR See http://php.net/manual/en/function.openssl-get-cipher-methods.php
     *
     * @throws Debugger
     * @test void this('123','somesalt','AES-128-CTR')
     */
    public function setEncryption($password, $salt, $encMethod)
    {
        if (!extension_loaded('openssl')) {
            $this->encEnabled = false;
            $this->throwError('OpenSSL not loaded, encryption disabled');
        } else {
            $this->encEnabled = true;
            $this->encPassword = $password;
            $this->encSalt = $salt;
            $this->encMethod = $encMethod;
        }
    }

    /**
     * It is a two way encryption.
     *
     * @param $data
     *
     * @return string
     */
    public function encrypt($data)
    {
        if (!$this->encEnabled) {
            return $data;
        } // no encryption
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->encMethod));
        $encrypted_string = bin2hex($iv).openssl_encrypt($this->encSalt.$data, $this->encMethod, $this->encPassword, 0, $iv);

        return urlencode($encrypted_string);
    }

    /**
     * It is a two way decryption.
     *
     * @param $data
     *
     * @return bool|string
     */
    public function decrypt($data)
    {
        if (!$this->encEnabled) {
            return $data;
        } // no encryption
        $iv_strlen = 2 * openssl_cipher_iv_length($this->encMethod);
        if (preg_match('/^(.{'.$iv_strlen.'})(.+)$/', $data, $regs)) {
            list(, $iv, $crypted_string) = $regs;
            $decrypted_string = openssl_decrypt($crypted_string, $this->encMethod, $this->encPassword, 0, hex2bin($iv));

            return urldecode(substr($decrypted_string, strlen($this->encSalt)));
        } else {
            return false;
        }
    }

    //</editor-fold>

    //<editor-fold desc="Log functions" defaultstate="collapsed" >

    /**
     * Returns the last error.
     *
     * @return string
     */
    public function lastError()
    {
        if (!$this->isOpen) {
            return "It's not connected to the database";
        }

        return $this->conn1->error;
    }

    /**
     * Write a log line for debug, clean the command chain then throw an error (if throwOnError==true).
     *
     * @param $txt
     *
     * @throws Debugger
     */
    public function throwError($txt)
    {
        $this->builderReset(); // it resets the chain if any.
        if ($this->getMessages() === null) {
            $this->debugFile($txt, 'ERROR');
        } else {
            $this->getMessages()->addItem($this->db, $txt);
            $this->debugFile($txt, 'ERROR');
        }

        if ($this->throwOnError) {
            throw new Debugger($txt);
        }
    }

    /**
     * Write a log line for debug, clean the command chain then throw an error (if throwOnError==true).
     *
     * @param $txt
     *
     * @throws Debugger
     */
    public function storeInfo($txt)
    {
        if ($this->getMessages() === null) {
            $this->debugFile($txt, 'INFO');
        } else {
            $this->getMessages()->addItem($this->db, $txt, 'info');
            $this->debugFile($txt, 'INFO');
        }
    }

    public function debugFile($txt, $level = 'INFO')
    {
        if ($this->logFile == '') {
            return; // debug file is disabled.
        }
        $fz = @filesize($this->logFile);

        if (is_object($txt) || is_array($txt)) {
            $txtW = print_r($txt, true);
        } else {
            $txtW = $txt;
        }
        if ($fz > 10000000) {
            // mas de 10mb = reducirlo a cero.
            $fp = @fopen($this->logFile, 'w');
        } else {
            $fp = @fopen($this->logFile, 'a');
        }
        if ($this->logLevel == 2) {
            $txtW .= ' param:'.json_encode($this->lastParam);
        }

        $txtW = str_replace("\r\n", ' ', $txtW);
        $txtW = str_replace("\n", ' ', $txtW);
        $now = new DateTime();
        @fwrite($fp, $now->format('c')."\t".$level."\t".$txtW."\n");
        @fclose($fp);
    }

    //</editor-fold>
}
