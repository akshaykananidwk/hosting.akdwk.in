<?php
// FILE: /app/Core/Database.php
// -------------------------------------------------------------------
// PDO singleton + query runner. બધી queries PREPARED STATEMENTS થી
// ચાલે (SQL injection સામે). table() → fluent QueryBuilder આપે.
// -------------------------------------------------------------------

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    protected array $config;
    protected ?PDO $pdo = null;

    public function __construct(array $config)
    {
        $this->config = array_merge([
            'host' => '127.0.0.1', 'port' => 3306, 'name' => '',
            'user' => '', 'pass' => '', 'charset' => 'utf8mb4',
        ], $config);
    }

    /**
     * Lazily open (and reuse) the PDO connection.
     */
    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        $c = $this->config;
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
        try {
            $this->pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
        return $this->pdo;
    }

    /**
     * Run a prepared statement and return the PDOStatement.
     */
    public function run(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $statement->bindValue($param, $value, $type);
        }
        $statement->execute();
        return $statement;
    }

    /**
     * SELECT returning all rows.
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll();
    }

    /**
     * SELECT returning the first row (or null).
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * SELECT returning a single scalar value.
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        return $this->run($sql, $bindings)->fetchColumn();
    }

    /**
     * INSERT and return the last insert id.
     */
    public function insert(string $sql, array $bindings = []): int
    {
        $this->run($sql, $bindings);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * UPDATE/DELETE and return affected row count.
     */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function statement(string $sql): bool
    {
        return $this->pdo()->exec($sql) !== false;
    }

    /**
     * Start a fluent query on a table.
     */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    // ---------------------------------------------------------------
    // Transactions
    // ---------------------------------------------------------------

    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo()->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }

    /**
     * Run a callback inside a transaction; auto commit/rollback.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Quick connectivity test used by the installer.
     */
    public function ping(): bool
    {
        try {
            $this->pdo()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
