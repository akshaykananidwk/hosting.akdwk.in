<?php
// FILE: /app/Core/Model.php
// -------------------------------------------------------------------
// Base model — QueryBuilder ઉપર thin layer. Multi-tenant scope નું
// support built-in છે: $tenantScoped = true હોય તો દરેક query પર
// આપોઆપ tenant_id filter લાગે (Module 4 currentTenantId set કરે).
// -------------------------------------------------------------------

namespace App\Core;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $tenantScoped = false;

    /** Current tenant id — set by Module 4 (TenantMiddleware). */
    protected static ?int $currentTenantId = null;

    public static function setCurrentTenant(?int $tenantId): void
    {
        static::$currentTenantId = $tenantId;
    }

    public static function getCurrentTenant(): ?int
    {
        return static::$currentTenantId;
    }

    /**
     * A fresh, tenant-scoped query builder for this model's table.
     */
    public static function query(): QueryBuilder
    {
        $qb = App::instance()->make('db')->table(static::$table);
        if (static::$tenantScoped && static::$currentTenantId !== null) {
            $qb->where('tenant_id', static::$currentTenantId);
        }
        return $qb;
    }

    /**
     * Raw (un-scoped) builder — for Super Admin / cross-tenant reads.
     */
    public static function unscoped(): QueryBuilder
    {
        return App::instance()->make('db')->table(static::$table);
    }

    public static function find(int $id): ?array
    {
        return static::query()->where(static::$primaryKey, $id)->first();
    }

    public static function findOrFail(int $id): array
    {
        $row = static::find($id);
        if ($row === null) {
            throw new HttpException(404, static::class . " #{$id} મળ્યું નહીં.");
        }
        return $row;
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function where(string $column, mixed $operator = null, mixed $value = null): QueryBuilder
    {
        return func_num_args() === 2
            ? static::query()->where($column, $operator)
            : static::query()->where($column, $operator, $value);
    }

    /**
     * Create a row (auto-injects tenant_id + timestamps) and return its id.
     */
    public static function create(array $data): int
    {
        if (static::$tenantScoped && static::$currentTenantId !== null && !isset($data['tenant_id'])) {
            $data['tenant_id'] = static::$currentTenantId;
        }
        $now = date('Y-m-d H:i:s');
        $data += ['created_at' => $now, 'updated_at' => $now];
        return static::unscoped()->insert($data);
    }

    /**
     * Update a row by primary key (respecting tenant scope).
     */
    public static function updateById(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return static::query()->where(static::$primaryKey, $id)->update($data);
    }

    public static function deleteById(int $id): int
    {
        return static::query()->where(static::$primaryKey, $id)->delete();
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    public static function table(): string
    {
        return static::$table;
    }
}
