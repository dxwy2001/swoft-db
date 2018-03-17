<?php

namespace Swoft\Db\Driver\Mysql;

use Swoft\App;
use Swoft\Db\AbstractDbConnection;
use Swoft\Db\Bean\Annotation\Connection;
use Swoft\Db\Driver\DriverType;

/**
 * Mysql sync connection
 *
 * @Connection(type=DriverType::SYNC)
 */
class SyncMysqlConnection extends AbstractDbConnection
{
    /**
     * @var \PDO
     */
    private $connection;

    /**
     * @var \PDOStatement
     */
    private $stmt;

    /**
     * @var string
     */
    private $sql;

    /**
     * Create connection
     */
    public function createConnection()
    {
        $uri                = $this->pool->getConnectionAddress();
        $options            = $this->parseUri($uri);
        $options['timeout'] = $this->pool->getTimeout();

        $user    = $options['user'];
        $passwd  = $options['password'];
        $host    = $options['host'];
        $port    = $options['port'];
        $dbName  = $options['database'];
        $charset = $options['charset'];
        $timeout = $options['timeout'];

        // connect
        $pdoOptions       = [
            \PDO::ATTR_TIMEOUT    => $timeout,
            \PDO::ATTR_PERSISTENT => true,
        ];
        $dsn              = "mysql:host=$host;port=$port;dbname=$dbName;charset=$charset";
        $this->connection = new \PDO($dsn, $user, $passwd, $pdoOptions);
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @param string $sql
     */
    public function prepare(string $sql)
    {
        $this->sql  = $sql . " Params:";
        $this->stmt = $this->connection->prepare($sql);
    }

    /**
     * @param array|null $params
     *
     * @return bool
     */
    public function execute(array $params = null)
    {
        $this->bindParams($params);
        $this->formatSqlByParams($params);
        $result = $this->stmt->execute();

        if ($result !== true) {
            App::error('Sync mysql execute error，sql=' . $this->stmt->debugDumpParams());
        }
        $this->pushSqlToStack($this->sql);

        return $result;
    }

    /**
     * @param array|null $params
     */
    private function bindParams(array $params = null)
    {
        if (empty($params)) {
            return;
        }

        foreach ($params as $key => $value) {
            if (is_int($key)) {
                $key = $key + 1;
            }
            $this->stmt->bindValue($key, $value);
        }
    }

    /**
     * @return void
     */
    public function reconnect()
    {
        $this->createConnection();
    }

    /**
     * @return bool
     */
    public function check(): bool
    {
        try {
            $this->connection->getAttribute(\PDO::ATTR_SERVER_INFO);
        } catch (\Throwable $e) {
            if ($e->getCode() == 'HY000') {
                return false;
            }
        }

        return true;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }

    /**
     * @return mixed
     */
    public function getInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * @return int
     */
    public function getAffectedRows(): int
    {
        return $this->stmt->rowCount();
    }

    /**
     * @return array
     */
    public function fetch()
    {
        return $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        $this->connection->rollBack();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        $this->connection->commit();
    }

    /**
     * Destory sql
     */
    public function destory()
    {
        $this->sql  = '';
        $this->stmt = null;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Format
     *
     * @param array $params
     */
    private function formatSqlByParams(array $params)
    {
        if (empty($params)) {
            return;
        }
        foreach ($params as $key => $value) {
            $this->sql .= " $key=" . $value;
        }
    }
}
