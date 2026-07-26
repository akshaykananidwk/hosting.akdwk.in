<?php
// FILE: /app/Core/QueryBuilder.php
// -------------------------------------------------------------------
// Fluent query builder. All values are bound as PLACEHOLDERS and
// identifiers (table/column) are sanitised with a strict regex, so
// SQL injection is not possible.
// -------------------------------------------------------------------

namespace App\Core;

class QueryBuilder
{
    protected Database $db;
    protected string $table;

    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $joins = [];
    protected array $orders = [];
    protected array $groups = [];
    protected array $havings = [];
    protected ?int $limit = null;
    protected ?int $offset = null;

    public function __construct(Database $db, string $table)
    {
        $this->db = $db;
        $this->table = $this->wrap($table);
    }

    /**
     * Wrap and validate an identifier. Rejects anything not matching
     * [a-zA-Z0-9_.] — the only safe way to place identifiers in SQL.
     */
    public static function wrap(string $identifier): string
    {
        $identifier = trim($identifier);
        // Allow "table.column" and "col as alias".
        if (stripos($identifier, ' as ') !== false) {
            [$col, $alias] = preg_split('/\s+as\s+/i', $identifier, 2);
            return self::wrap($col) . ' AS ' . self::wrap($alias);
        }
        if ($identifier === '*') {
            return '*';
        }
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid SQL identifier: {$identifier}");
        }
        $parts = explode('.', $identifier);
        return implode('.', array_map(fn($p) => $p === '*' ? '*' : '`' . $p . '`', $parts));
    }

    public function select(string|array $columns): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        // Two-argument form: where('id', 5) means where id = 5.
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $operator = strtoupper((string) $operator);
        $allowed = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];
        if (!in_array($operator, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid operator: {$operator}");
        }
        $this->wheres[] = ['type' => 'basic', 'sql' => self::wrap($column) . ' ' . $operator . ' ?', 'boolean' => $boolean];
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->where($column, $operator, null, 'OR');
        }
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if (empty($values)) {
            $this->wheres[] = ['type' => 'raw', 'sql' => '0 = 1', 'boolean' => $boolean];
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['type' => 'in', 'sql' => self::wrap($column) . " IN ({$placeholders})", 'boolean' => $boolean];
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = ['type' => 'null', 'sql' => self::wrap($column) . ' IS NULL', 'boolean' => $boolean];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = ['type' => 'null', 'sql' => self::wrap($column) . ' IS NOT NULL', 'boolean' => $boolean];
        return $this;
    }

    public function whereLike(string $column, string $value, string $boolean = 'AND'): self
    {
        return $this->where($column, 'LIKE', $value, $boolean);
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $operator = in_array($operator, ['=', '<', '>', '<=', '>=', '!='], true) ? $operator : '=';
        $this->joins[] = strtoupper($type) . ' JOIN ' . self::wrap($table) . ' ON ' . self::wrap($first) . " {$operator} " . self::wrap($second);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = self::wrap($column) . ' ' . $direction;
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = self::wrap($column);
        }
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $allowed = ['=', '!=', '<', '<=', '>', '>='];
        $operator = in_array($operator, $allowed, true) ? $operator : '=';
        $this->havings[] = self::wrap($column) . " {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function forPage(int $page, int $perPage = 20): self
    {
        return $this->limit($perPage)->offset(($page - 1) * $perPage);
    }

    // ---------------------------------------------------------------
    // SQL compilation
    // ---------------------------------------------------------------

    protected function compileWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        $sql = '';
        foreach ($this->wheres as $i => $where) {
            $sql .= ($i === 0 ? '' : ' ' . $where['boolean'] . ' ') . $where['sql'];
        }
        return ' WHERE ' . $sql;
    }

    public function toSql(): string
    {
        $columns = implode(', ', array_map(fn($c) => self::wrap($c), $this->columns));
        $sql = "SELECT {$columns} FROM {$this->table}";
        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        $sql .= $this->compileWheres();
        if ($this->groups) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }
        if ($this->havings) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
        }
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    // ---------------------------------------------------------------
    // Terminal methods
    // ---------------------------------------------------------------

    public function get(): array
    {
        return $this->db->select($this->toSql(), $this->bindings);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function find(int $id, string $key = 'id'): ?array
    {
        return $this->where($key, $id)->first();
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();
        return $row[$column] ?? null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $rows = $this->get();
        $result = [];
        foreach ($rows as $row) {
            if ($key !== null) {
                $result[$row[$key]] = $row[$column] ?? null;
            } else {
                $result[] = $row[$column] ?? null;
            }
        }
        return $result;
    }

    public function count(string $column = '*'): int
    {
        $expr = $column === '*' ? '*' : self::wrap($column);
        $sql = "SELECT COUNT({$expr}) AS aggregate FROM {$this->table}"
            . ($this->joins ? ' ' . implode(' ', $this->joins) : '')
            . $this->compileWheres();
        return (int) ($this->db->selectOne($sql, $this->bindings)['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $sql = "SELECT COALESCE(SUM(" . self::wrap($column) . "),0) AS aggregate FROM {$this->table}"
            . $this->compileWheres();
        return (float) ($this->db->selectOne($sql, $this->bindings)['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $wrapped = implode(', ', array_map(fn($c) => self::wrap($c), $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO {$this->table} ({$wrapped}) VALUES ({$placeholders})";
        return $this->db->insert($sql, array_values($data));
    }

    public function update(array $data): int
    {
        $sets = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $sets[] = self::wrap($column) . ' = ?';
            $bindings[] = $value;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $this->compileWheres();
        return $this->db->affectingStatement($sql, array_merge($bindings, $this->bindings));
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}" . $this->compileWheres();
        return $this->db->affectingStatement($sql, $this->bindings);
    }

    /**
     * Simple paginator: returns data + meta.
     */
    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $total = (clone $this)->count();
        $data = $this->forPage($page, $perPage)->get();
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / max(1, $perPage)),
        ];
    }
}
