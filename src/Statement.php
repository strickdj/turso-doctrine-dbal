<?php

declare(strict_types=1);

namespace Turso\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use LibSQL;
use LibSQLStatement;

use function is_int;

final class Statement implements StatementInterface
{
    protected array $parameters = [];

    public function __construct(
        private readonly LibSQL $connection,
        private readonly LibSQLStatement $statement,
        private readonly string $sql,
        private readonly bool $isStandAlone
    ) {
    }

    /**
     * @param $param
     * @param $value
     * @param $type
     * @return bool
     */
    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        if (!preg_match('/^[:@]/', (string) $param)) {
            $this->parameters[] = $value;
        } else {
            $this->parameters[$param] = $value;
        }
        return true;
    }

    /**
     * @param $param
     * @param $variable
     * @param $type
     * @param $length
     * @return bool
     * @throws \Exception
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5563',
            '%s is deprecated. Use bindValue() instead.',
            __METHOD__,
        );
        return $this->bindValue($param, $variable, $type);
    }

    /**
     * @param $params
     * @return Result
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function execute($params = null): Result
    {
        if ($params !== null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5556',
                'Passing $params to Statement::execute() is deprecated. Bind parameters using'
                . ' Statement::bindParam() or Statement::bindValue() instead.',
            );

            foreach ($params as $param => $value) {
                if (is_int($param)) {
                    $this->bindValue($param + 1, $value, ParameterType::STRING);
                } else {
                    $this->bindValue($param, $value, ParameterType::STRING);
                }
            }
        }

        $result = $this->connection->query($this->sql, $this->parameters);
        $this->reset();

        return new Result($result, $this->isStandAlone);
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        $this->parameters = [];
    }
}
