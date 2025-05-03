<?php

declare(strict_types=1);

namespace Turso\Doctrine\DBAL;

use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use LibSQL;
use LibSQLTransaction;
use Doctrine\DBAL\ParameterType;

final class Connection implements  ServerInfoAwareConnection
{
    private bool $isTransaction = false;
    private LibSQLTransaction $transaction;

    public function __construct(
        private LibSQL $connection,
        private readonly bool $isStandAlone
    ) {
    }

    /**
     * @param $value
     * @return string
     */
    public static function escapeString($value): string
    {
        // DISCUSSION: Open PR if you have best approach
        $escaped_value = str_replace(
            ["\\", "\x00", "\n", "\r", "\x1a", "'", '"'],
            ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'", '\\"'],
            $value
        );

        return $escaped_value;
    }

    /**
     * @param string $sql
     * @return Statement
     * @throws Exception
     */
    public function prepare(string $sql): Statement
    {
        try {
            $statement = $this->connection->prepare($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        \assert($statement !== false);

        return new Statement($this->connection, $statement, $sql, $this->isStandAlone);
    }

    /**
     * @param $sql
     * @return Result
     * @throws Exception
     */
    public function query(string $sql): Result
    {
        try {
            // echo "Query\n";
            // echo $sql . PHP_EOL;
            if (stripos(trim($sql), 'SELECT') !== 0) {
                // echo "Write";
                $exec = $this->connection->execute($sql);
                // dump($exec);
            }
            $result = $this->connection->query($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        assert($result !== false);

        return new Result($result, $this->isStandAlone);
    }

    /**
     * @param string $value
     * @param int $type
     * @return mixed
     */
    public function quote($value, $type = ParameterType::STRING): string
    {
        return self::escapeString($value);
    }

    /**
     * @param string $sql
     * @return int
     * @throws Exception
     */
    public function exec(string $sql): int
    {
        $changes = 0;

        try {
            $changes = $this->isTransaction ? $this->transaction->execute($sql) : $this->connection->execute($sql);
            // echo "Exec, in transaction (". ($this->isTransaction ? 'YES' : 'NO') .")\n";
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        return $changes;
    }

    /**
     * @param string|null $name
     * @return int
     */
    public function lastInsertId($name = null): int
    {
        // echo "Last insert ID, in transaction (". ($this->isTransaction ? 'YES' : 'NO') .")\n";
        return $this->isTransaction ? $this->transaction->changes() : $this->connection->changes();
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): bool
    {
        try {
            $this->isTransaction = true;
            $this->transaction = $this->connection->transaction();
            // echo "Transaction begin, in transaction (". ($this->isTransaction ? 'YES' : 'NO') .")\n";
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
        return is_object($this->transaction);
    }

    public function commit(): bool
    {
        try {
            if ($this->isTransaction) {
                $this->transaction->commit();
                $this->isTransaction = false;
                return true;
            }
            // echo "Committed\n";
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
        return false;
    }

    public function rollBack(): bool
    {
        try {
            if ($this->isTransaction) {
                $this->transaction->rollBack();
                $this->isTransaction = false;
                return true;
            }
            // echo "Rollback\n";
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
        return false;
    }

    public function getNativeConnection(): LibSQL
    {
        return $this->connection;
    }

    public function getServerVersion(): string
    {
        return LibSQL::version();
    }
}
