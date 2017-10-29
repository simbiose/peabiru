<?php namespace libs;

/**
 * database abstraction layer
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

use PDO, NotORM, NotORM_Result, NotORM_Structure_Convention;

/**
 * structure convention class with primary/foreign key resolution
 */

class Schema extends NotORM_Structure_Convention {

  protected $con, $struct = [];

  function __construct ($con) {
    $this->con = $con;
    parent::__construct('id', 'fk_%s', '%s', '');
    if (!empty($this->struct = apcu_fetch('norm-structure')))
      return;

    foreach ($this->con->query(
<<<'SQL'
  SELECT
    tc.table_name AS tb, kc.column_name AS col, cc.table_name AS ref, constraint_type AS c_type
  FROM information_schema.table_constraints AS tc
  JOIN information_schema.key_column_usage AS kc
    ON (tc.constraint_name = kc.constraint_name)
  JOIN information_schema.constraint_column_usage AS cc
    ON (cc.constraint_name = tc.constraint_name)
SQL
      ) as $row)
      $this->struct[$row['tb']] = array_merge_recursive(
        $this->struct[$row['tb']] ?: [],
        ($row['c_type'][0] == 'F' ? [$row['ref'] => [$row['col']]] : ['pk' => $row['col']])
      );

    apcu_store('norm-structure', $this->struct, 60*60*24);
  }

  function getSequence ($table) {
    debug(' [sequence] ', sprintf('%s_%s_seq', $table, $this->struct[$table]['pk']));
    return sprintf('%s_%s_seq', $table, $this->struct[$table]['pk']);
  }

  function getRelation ($parent, $table) {
    debug(" [relations] parent: $parent -> $table");
    $results    = [];
    $ref_parent = $this->struct[$parent][$table] ?: [$this->struct[$parent]['pk']];
    $ref_table  = $this->struct[$table][$parent] ?: [$this->struct[$table]['pk']];
    if ($ref_parent[0] == $ref_table[0]) $ref_table[0] = [$this->struct[$table]['pk']];
    if (count($ref_parent) > count($ref_table)) {
      for ($i=0; $i<count($ref_parent); ++$i) $results[] = [$ref_parent[$i], $ref_table[0]];
    } elseif (count($ref_parent) < count($ref_table)) {
      for ($i=0; $i<count($ref_table); ++$i) $results[] = [$ref_parent[0], $ref_table[$i]];
    } else {
      $results[] = array_merge($ref_parent, $ref_table);
    }
    debug(' [relations] ', $results, $this->struct[$parent]);
    return $results;
  }
}

/**
 * patch NotORM Result
 */

class Result extends NotORM_Result {

  protected $customizedJoins = [];

  function __construct(...$args) {
    parent::__construct(...$args);
  }

  protected function createJoins ($val) {
    $results = [];
    preg_match_all('~\\b([a-z_][a-z0-9_.:]*[.:])[a-z_*]~i', $val, $matches);

    foreach ($matches[1] as $names) {
      if ($names == ($parent = $this->table).'.') continue;

      preg_match_all('~\\b([a-z_][a-z0-9_]*)([.:])~i', $names, $matches, PREG_SET_ORDER);

      foreach ($matches as $match) {
        list($no, $name, $delimiter) = $match;

        $table          = $this->notORM->structure->getReferencedTable($name, $parent);
        $relations      = $this->notORM->structure->getRelation($parent, $table);
        $results[$name] = ' LEFT JOIN '. $table .($table != $name ? ' AS '. $name : '') .
          ' ON ('. join(' OR ', array_map(function ($item) use ($parent, $name) {
            return sprintf('%s.%s = %s.%s', $parent, $item[0], $name, $item[1]);
          }, $relations)) .')';

        $parent = $name;
      }
    }

    if (count($this->customizedJoins) > 0)
      foreach ($this->customizedJoins as $name => $query)
        $results[$name] = ' '.$query;

    return $results;
  }

  function join($tableName, $joinQuery) {
    $this->customizedJoins[$tableName] = $joinQuery;
    return $this;
  }

 /** Execute the built query
  * @return null
  */

  protected function execute() {
    if (isset($this->rows)) return;

    $result     = false;
    $exception  = null;
    $parameters = [];

    foreach (array_merge(
        $this->select, [$this, $this->group, $this->having], $this->order, $this->unionOrder
      ) as $val
    ) if (($val instanceof NotORM_Literal || $val instanceof self) && $val->parameters)
        $parameters = array_merge($parameters, $val->parameters);

    try {
      $result = $this->query($this->__toString(), $parameters);
    } catch (PDOException $exception) { /* later */  }

    if (!$result)
      if ((!$this->select && $this->accessed) && ($this->accessed = '') && ($this->access = []))
        $result = $this->query($this->__toString(), $parameters);
      elseif ($exception)
        throw $exception;

    $this->rows = [];

    if ($result) {
      $cast_all   = null;
      $fn_body    = '';
      $i          = -1;
      $cast_index = ['numeric'=>'float', 'boolean'=>'bool'];

      $result->setFetchMode(PDO::FETCH_ASSOC);

      foreach ($result as $key => $row) {
        if (!$cast_all) {
          foreach ($row as $k => $v)
            if (
              ($type = $result->getColumnMeta(++$i)['native_type']) && isset($cast_index[$type])
              && is_string($v) && ($type = $cast_index[$type])
            ) $fn_body .= '  $row["'. $k .'"] = ('. $type .') $row["'. $k .'"];'. PHP_EOL;
          $cast_all = create_function('&$row', $fn_body);
        }

        $cast_all($row);

        if (isset($row[$this->primary])) {
          $key = $row[$this->primary];
          if (!is_string($this->access))
            $this->access[$this->primary] = true;
        }

        $this->rows[$key] = new $this->notORM->rowClass($row, $this);
      }
    }

    $this->data = $this->rows;
  }
}

/**
 *
 *
 */

class Norm extends NotORM {

  private static $norm;
  private static $colorful;

  private static function connect () {
    // load schema
    $con   = new PDO(
      sprintf('pgsql:host=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_NAME')),
      getenv('DB_USER'), null, [
        PDO::ATTR_PERSISTENT        => true,
        PDO::ATTR_EMULATE_PREPARES  => false,
        PDO::ATTR_STRINGIFY_FETCHES => false
      ]
    );
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    $con->setAttribute(PDO::ATTR_CASE,    PDO::CASE_LOWER);

    self::$norm = new self($con, new Schema($con));

    if (DEV) {
      self::$colorful = new Colorful();
      self::$norm->debug = [&self::$colorful, 'colorize'];
    }
  }

  static function to_a ($results=null) {
    if (!$results) return [];
    return array_values(array_map('iterator_to_array', iterator_to_array($results)));
  }

  //function literal ($v) { return new NotORM_Literal($v); }
  // literal,   many-to-many: ([attach, detach], sync) ? pagination
  static function __callStatic ($method, $args) {
    if (!isset(self::$norm)) self::connect();
    return self::$norm->{$method};
  }

  /**
   * patch NotORM
   */

  function __get ($table) {
    return new Result($this->structure->getReferencingTable($table, ''), $this, true);
  }

  function __call ($table, array $where) {
    $result = new Result($this->structure->getReferencingTable($table, ''), $this);
    if ($where) [&$result, 'where'](...$where);
    return $result;
  }
}


class Colorful {
  private $sql, $keywords, $themes, $theme, $available_themes;
  private $commentOpen = false, $stringOpen = false, $quoteOpen = false;

  function __construct () {
    $this->sql = (object) [
      'regex'        => '/([`\'"\/]?\*?)([\w\d]*)(\*?[\/`\'"]?)/i',
      'quote'        => '\'',
      'string'       => '`',
      'commentOpen'  => '/*',
      'commentClose' => '*/'
    ];
    $this->keywords  = array_flip([
      'ADD', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'AS', 'ASC', 'ASENSITIVE', 'BEFORE', 'INDEX', 'BLOB',
      'BOTH', 'BY', 'CALL', 'CASCADE', 'CASE', 'CHANGE', 'CHAR', 'CHARACTER', 'CHECK', 'CONDITION',
      'CONSTRAINT', 'CONTINUE', 'CONVERT', 'CREATE', 'CROSS', 'CURRENT_DATE', 'CURRENT_TIME',
      'CURRENT_TIMESTAMP', 'CURRENT_USER', 'CURSOR', 'DATABASEDATABASES', 'DAY_HOUR', 'ORDER', 'OR',
      'DAY_MICROSECOND', 'DAY_MINUTE', 'DAY_SECOND', 'DEC', 'DECIMAL', 'DECLARE', 'DEFAULT',
      'DELAYED', 'DELETE', 'DESC', 'DESCRIBE', 'DETERMINISTIC', 'DISTINCT', 'DISTINCTROW', 'DIV',
      'DOUBLE', 'DROP', 'DUAL', 'EACH', 'ELSE', 'ELSEIF', 'ENCLOSED', 'ESCAPED', 'EXISTS', 'EXIT',
      'EXPLAIN', 'FALSE', 'FETCH', 'FLOAT', 'SET', 'FLOAT4', 'FLOAT8', 'FOR', 'FORCE', 'FOREIGN',
      'FROM', 'FULLTEXT', 'GRANT', 'GROUP', 'HAVING', 'OPTIMIZE', 'HIGH_PRIORITY',
      'HOUR_MICROSECOND', 'HOUR_MINUTE', 'HOUR_SECOND', 'IF', 'IGNORE', 'IN', 'BETWEEN', 'INFILE',
      'INNER', 'INOUT', 'INSENSITIVE', 'INSERT', 'INT', 'INT1', 'INT2', 'INT3', 'INT4', 'INT8',
      'INTEGER', 'INTERVAL', 'INTO', 'IS', 'ITERATE', 'JOIN', 'KEY', 'KEYS', 'KILL', 'LEADING',
      'LEAVE', 'LEFT', 'LIKE', 'LIMIT', 'LINES', 'LOAD', 'LOCALTIME', 'LOCALTIMESTAMP', 'LOCK',
      'LONG', 'LONGBLOB', 'LONGTEXT', 'LOOP', 'ON', 'OUT', 'LOW_PRIORITY', 'MATCH', 'MEDIUMBLOB',
      'MEDIUMINT', 'MEDIUMTEXT', 'MIDDLEINT', 'MOD', 'MODIFIES', 'MINUTE_MICROSECOND',
      'MINUTE_SECOND', 'NATURAL', 'NOT', 'OPTION', 'PURGE', 'OPTIONALLY', 'NO_WRITE_TO_BINLOGNULL',
      'NUMERIC', 'OUTER', 'OUTFILE', 'PRECISION', 'PRIMARY', 'PROCEDURE', 'READ', 'READS', 'REAL',
      'REFERENCES', 'REGEXP', 'RELEASE', 'RENAME', 'REPEAT', 'REPLACE', 'REQUIRE', 'RESTRICT',
      'RETURN', 'REVOKE', 'RIGHT', 'RLIKE', 'SCHEMA', 'SCHEMAS', 'SQLSTATE', 'SECOND_MICROSECOND',
      'SELECT', 'SENSITIVE', 'SSL', 'SEPARATOR', 'SHOW', 'SMALLINT', 'SONAME', 'SPATIAL', 'SPECIFIC',
      'SQL', 'SQLEXCEPTION', 'SQLWARNING', 'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS',
      'SQL_SMALL_RESULT', 'STARTING', 'STRAIGHT_JOIN', 'TABLE', 'TERMINATED', 'THEN', 'TINYBLOB',
      'TINYINT', 'TINYTEXT', 'TO', 'TRAILING', 'TRIGGER', 'TRUE', 'UNDO', 'UNION', 'UNIQUE',
      'UNLOCK', 'UNSIGNED', 'UPDATE', 'USAGE', 'USE', 'USING', 'WHEN', 'UTC_DATE', 'UTC_TIME',
      'UTC_TIMESTAMP', 'VALUES', 'VARBINARY', 'VARCHAR', 'VARCHARACTER', 'XOR', 'VARYING', 'WHERE',
      'WHILE', 'WITH', 'WRITE', 'YEAR_MONTH', 'ZEROFILL', 'BIGINT', 'BINARY', 'COLLATE', 'COLUMN'
    ]);
    $this->themes = (object) [
      'twilight' => [
        'comment'=>"\x1b[38;5;59m", 'foreground'=>"\x1b[38;5;231m", 'quote'=>"\x1b[38;5;107m",
        'string'=>"\x1b[38;5;107m", 'number'=>"\x1b[38;5;167m", 'keyword'=>"\x1b[38;5;179m"
      ], 'monokai' => [
        'comment'=>"\x1b[38;5;242m", 'foreground'=>"\x1b[38;5;255m", 'quote'=>"\x1b[38;5;186m",
        'string'=>"\x1b[38;5;186m", 'number'=>"\x1b[38;5;141m", 'keyword'=>"\x1b[38;5;197m"
      ], 'pastel' => [
        'comment'=>"\x1b[38;5;240m", 'foreground'=>"\x1b[38;5;253m", 'quote'=>"\x1b[38;5;137m",
        'string'=>"\x1b[38;5;137m", 'number'=>"\x1b[38;5;252m", 'keyword'=>"\x1b[38;5;147m"
      ], 'sunburst' => [
        'comment'=>"\x1b[38;5;145m", 'foreground'=>"\x1b[38;5;231m", 'quote'=>"\x1b[38;5;71m",
        'string'=>"\x1b[38;5;71m",  'number'=>"\x1b[38;5;231m", 'keyword'=>"\x1b[38;5;180m"
      ], 'solarized' => [
        'comment'=>"\x1b[38;5;242m", 'foreground'=>"\x1b[38;5;246m", 'quote'=>"\x1b[38;5;71m",
        'string'=>"\x1b[38;5;36m", 'number'=>"\x1b[38;5;168m", 'keyword'=>"\x1b[38;5;100m"
      ]];
  }

  function colorize (...$args) {
    if (!empty($args[1])) {
      for ($i = 0; $i < count($args[1]); ++$i)
        $args[1][$i] = is_numeric($args[1][$i]) ? $args[1][$i] : '\''. $args[1][$i] .'\'';
      $args[0] = vsprintf(str_replace('?', '%s', $args[0]), $args[1]);
    }
    if (!$this->available_themes)
      $this->available_themes = array_keys((array) $this->themes);
    $this->theme = (object)
      $this->themes->{$this->available_themes[rand(0, count($this->available_themes)-1)]};
    debug(
      $this->theme->foreground . preg_replace_callback(
        $this->sql->regex, [&$this, 'parser'], $args[0]
      ) ."\x1b[0m"
    );
  }

  function parser ($matches) {
    list($a, $b, $c, $d) = $matches;
    // comment
    if (
      $this->commentOpen && ($this->sql->commentClose == $d || $this->sql->commentClose == $b)
    ) {
      $this->commentOpen = false;
      return $c  .$this->sql->commentClose .$this->theme->foreground;
    } elseif ($b == $this->sql->commentOpen && $d == $this->sql->commentClose) {
      return $this->theme->comment .$b. $c .$d. $this->theme->foreground;
    } elseif ($b == $this->sql->commentOpen) {
      $this->commentOpen = true;
      return $this->theme->comment .$b. $c;
    } elseif ($this->commentOpen) {
      return $a;
    }
    // string
    if ($this->stringOpen && ($this->sql->string == $d || $this->sql->string == $b)) {
      $this->stringOpen = false;
      return $c .$this->sql->string .$this->theme->foreground;
    } elseif ($b == $this->sql->string && $d == $this->sql->string) {
      return $this->theme->string .$b. $c .$d. $this->theme->foreground;
    } elseif ($b == $this->sql->string) {
      $this->stringOpen = true;
      return $this->theme->string .$b. $c;
    } elseif ($this->stringOpen) {
      return $a;
    }
    // quotes affairs
    if ($this->quoteOpen && ($this->sql->quote == $d || $this->sql->quote == $b)) {
      $this->quoteOpen = false;
      return $c .$this->sql->quote. $this->theme->foreground;
    } elseif ($b == $this->sql->quote && $d == $this->sql->quote) {
      return $this->theme->quote .$this->sql->quote .$c.
        $this->sql->quote .$this->theme->foreground;
    } elseif ($b == $this->sql->quote) {
      $this->quoteOpen = true;
      return $this->theme->quote .$b. $c;
    } elseif ($this->quoteOpen) {
      return $a;
    }
    // keyword or number?
    if (isset($this->keywords[$c]))
      return $this->theme->keyword .$c. $this->theme->foreground;
    if ($b == '' && $d == '' && is_numeric($c))
      return $this->theme->number .$c. $this->theme->foreground;

    return $a;
  }
}

?>
