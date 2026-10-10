<?php
final class PgsqlResult
{
    private $rows;
    private $position = 0;
    public $num_rows;

    public function __construct(PDOStatement $statement)
    {
        $this->rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc()
    {
        if (!isset($this->rows[$this->position])) {
            return null;
        }
        return $this->rows[$this->position++];
    }

    public function fetch_row()
    {
        $row = $this->fetch_assoc();
        return $row === null ? null : array_values($row);
    }

    public function fetch_all()
    {
        $rows = array_slice($this->rows, $this->position);
        $this->position = count($this->rows);
        return $rows;
    }
}

final class PgsqlStatement
{
    private $connection;
    private $statement;
    private $boundValues = [];
    public $affected_rows = 0;

    private $returnsInsertId;

    public function __construct(PgsqlConnection $connection, PDOStatement $statement, $returnsInsertId)
    {
        $this->connection = $connection;
        $this->statement = $statement;
        $this->returnsInsertId = $returnsInsertId;
    }

    public function bind_param($types, &...$values)
    {
        foreach ($values as $index => &$value) {
            $this->boundValues[$index] = [&$value, $types[$index] ?? 's'];
        }
        unset($value);
        return true;
    }

    public function execute()
    {
        $this->connection->insert_id = 0;
        foreach ($this->boundValues as $index => $bound) {
            $value = $bound[0];
            $type = $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR;
            if ($value !== null && $bound[1] === 'i') {
                $type = PDO::PARAM_INT;
            } elseif ($value !== null && $bound[1] === 'b') {
                $type = PDO::PARAM_LOB;
            }
            $this->statement->bindValue($index + 1, $value, $type);
        }

        $executed = $this->statement->execute();
        $this->affected_rows = $this->statement->rowCount();
        if ($this->returnsInsertId) {
            $this->connection->insert_id = (int) $this->statement->fetchColumn();
        }
        return $executed;
    }

    public function get_result()
    {
        return new PgsqlResult($this->statement);
    }

    public function __get($name)
    {
        if ($name === 'insert_id') {
            return $this->connection->insert_id;
        }
        throw new LogicException('Unsupported PostgreSQL statement property: '.$name);
    }
}

final class PgsqlConnection
{
    private $pdo;
    public $insert_id = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function query($sql)
    {
        $returnsInsertId = self::returnsInsertId($sql);
        $statement = $this->pdo->query(self::translate($sql));
        if ($statement === false) {
            throw new RuntimeException('PostgreSQL query failed.');
        }
        if ($returnsInsertId && $statement->columnCount() > 0) {
            $this->insert_id = (int) $statement->fetchColumn();
        }
        return new PgsqlResult($statement);
    }

    public function prepare($sql)
    {
        $returnsInsertId = self::returnsInsertId($sql);
        $statement = $this->pdo->prepare(self::translate($sql));
        if ($statement === false) {
            throw new RuntimeException('PostgreSQL statement preparation failed.');
        }
        return new PgsqlStatement($this, $statement, $returnsInsertId);
    }

    public function ping()
    {
        return $this->pdo->query('SELECT 1') !== false;
    }

    public function autocommit($enabled)
    {
        if (!$enabled) {
            throw new LogicException('Disabling autocommit is not supported.');
        }
        return true;
    }

    public function begin_transaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->inTransaction() ? $this->pdo->commit() : false;
    }

    public function rollback()
    {
        return $this->pdo->inTransaction() ? $this->pdo->rollBack() : false;
    }

    private static function translate($sql)
    {
        $sql = preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CURRENT_DATE', $sql);
        $sql = preg_replace('/\bNOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $sql);
        $sql = preg_replace(
            "/DATE_SUB\\(\\s*CURRENT_DATE\\s*,\\s*INTERVAL\\s+1\\s+DAY\\s*\\)/i",
            "(CURRENT_DATE - INTERVAL '1 day')",
            $sql
        );
        $sql = preg_replace('/TIMESTAMP\(\s*([a-z_][a-z0-9_.]*)\s*,\s*([a-z_][a-z0-9_.]*)\s*\)/i', '($1 + $2)', $sql);
        $sql = preg_replace('/CONCAT\(\s*"%"\s*,\s*\?\s*,\s*"%"\s*\)/i', "('%' || ? || '%')", $sql);
        $sql = preg_replace(
            "/CONCAT\\(\\s*'([^']*)'\\s*,\\s*([a-z_][a-z0-9_.]*)\\s*\\)/i",
            "('$1' || $2)",
            $sql
        );
        $sql = preg_replace(
            "/ON DUPLICATE KEY UPDATE\\s+setting_value\\s*=\\s*VALUES\\(setting_value\\)/i",
            'ON CONFLICT (setting_key) DO UPDATE SET setting_value=EXCLUDED.setting_value',
            $sql
        );
        $sql = preg_replace(
            "/ON DUPLICATE KEY UPDATE\\s+status\\s*=\\s*'SIGNED'\\s*,\\s*signed_at\\s*=\\s*CURRENT_TIMESTAMP\\s*,\\s*signed_by\\s*=\\s*VALUES\\(signed_by\\)/i",
            "ON CONFLICT (student_id) DO UPDATE SET status='SIGNED',signed_at=CURRENT_TIMESTAMP,signed_by=EXCLUDED.signed_by",
            $sql
        );
        $sql = preg_replace_callback(
            "/FIELD\\(\\s*([a-z_][a-z0-9_.]*)\\s*,\\s*'([^']+)'\\s*,\\s*'([^']+)'\\s*,\\s*'([^']+)'\\s*\\)/i",
            static function ($matches) {
                return "CASE {$matches[1]} WHEN '{$matches[2]}' THEN 1 WHEN '{$matches[3]}' THEN 2 WHEN '{$matches[4]}' THEN 3 ELSE 4 END";
            },
            $sql
        );

        if (preg_match('/^\s*INSERT\s+IGNORE\s+INTO\b/i', $sql)) {
            $sql = preg_replace('/^\s*INSERT\s+IGNORE\s+INTO\b/i', 'INSERT INTO', $sql);
            $sql = rtrim($sql);
            $semicolon = substr($sql, -1) === ';' ? ';' : '';
            if ($semicolon !== '') {
                $sql = substr($sql, 0, -1);
            }
            $sql .= ' ON CONFLICT DO NOTHING'.$semicolon;
        }

        if (preg_match('/^\s*INSERT\s+INTO\s+(admin_users|ssc_users|payments)\b/i', $sql)
            && !preg_match('/\bRETURNING\b/i', $sql)) {
            $sql = rtrim($sql);
            $semicolon = substr($sql, -1) === ';' ? ';' : '';
            if ($semicolon !== '') {
                $sql = substr($sql, 0, -1);
            }
            $sql .= ' RETURNING id'.$semicolon;
        }

        return $sql;
    }

    private static function returnsInsertId($sql)
    {
        return (bool) preg_match('/^\s*INSERT\s+INTO\s+(admin_users|ssc_users|payments)\b/i', $sql);
    }
}
