<?php
// FILE: /app/Core/Validator.php
// -------------------------------------------------------------------
// Input validation engine. Rules pipe-separated: 'required|email|max:191'.
// DB rules (unique/exists) run through prepared statements.
// -------------------------------------------------------------------

namespace App\Core;

class Validator
{
    protected array $data;
    protected array $rules;
    protected array $customMessages;
    protected array $errors = [];
    protected array $validated = [];

    protected array $defaultMessages = [
        'required'  => ':field is required.',
        'email'     => ':field must be a valid email address.',
        'numeric'   => ':field must be a number.',
        'integer'   => ':field must be an integer.',
        'min'       => ':field must be at least :param.',
        'max'       => ':field may not be greater than :param.',
        'between'   => ':field must be between :param0 and :param1.',
        'in'        => 'The selected :field is invalid.',
        'same'      => ':field and :param do not match.',
        'confirmed' => ':field confirmation does not match.',
        'regex'     => ':field format is invalid.',
        'url'       => ':field must be a valid URL.',
        'boolean'   => ':field must be true or false.',
        'date'      => ':field must be a valid date.',
        'alpha'     => ':field may only contain letters.',
        'alpha_num' => ':field may only contain letters and numbers.',
        'alpha_dash'=> ':field may only contain letters, numbers, dashes and underscores.',
        'unique'    => ':field has already been taken.',
        'exists'    => 'The selected :field does not exist.',
        'mobile'    => ':field must be a valid mobile number.',
    ];

    public function __construct(array $data, array $rules, array $customMessages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
        $this->run();
    }

    public static function make(array $data, array $rules, array $customMessages = []): self
    {
        return new self($data, $rules, $customMessages);
    }

    protected function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            $isNullable = in_array('nullable', $rules, true);
            if ($isNullable && ($value === null || $value === '')) {
                $this->validated[$field] = $value;
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $params = $param !== null ? explode(',', $param) : [];
                if (!$this->applyRule($name, $field, $value, $params)) {
                    // Stop at first failing rule per field.
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    protected function applyRule(string $name, string $field, mixed $value, array $params): bool
    {
        $passes = match ($name) {
            'required'  => $value !== null && $value !== '' && $value !== [],
            'email'     => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'numeric'   => is_numeric($value),
            'integer'   => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'string'    => is_string($value),
            'min'       => $this->checkSize($value) >= (float) ($params[0] ?? 0),
            'max'       => $this->checkSize($value) <= (float) ($params[0] ?? 0),
            'between'   => $this->checkSize($value) >= (float) ($params[0] ?? 0) && $this->checkSize($value) <= (float) ($params[1] ?? 0),
            'in'        => in_array((string) $value, $params, true),
            'not_in'    => !in_array((string) $value, $params, true),
            'same'      => $value === ($this->data[$params[0] ?? ''] ?? null),
            'confirmed' => $value === ($this->data[$field . '_confirmation'] ?? null),
            'regex'     => @preg_match($params[0] ?? '//', (string) $value) === 1,
            'url'       => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'boolean'   => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true),
            'date'      => strtotime((string) $value) !== false,
            'alpha'     => ctype_alpha((string) $value),
            'alpha_num' => ctype_alnum((string) $value),
            'alpha_dash'=> preg_match('/^[a-zA-Z0-9_-]+$/', (string) $value) === 1,
            'mobile'    => preg_match('/^(\+?91)?[6-9]\d{9}$/', preg_replace('/\s+/', '', (string) $value) ?? '') === 1,
            'unique'    => $this->checkUnique($value, $params),
            'exists'    => $this->checkExists($value, $params),
            default     => true,
        };

        if (!$passes) {
            $this->addError($name, $field, $params);
            return false;
        }
        return true;
    }

    protected function checkSize(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_array($value)) {
            return (float) count($value);
        }
        return (float) mb_strlen((string) $value);
    }

    protected function checkUnique(mixed $value, array $params): bool
    {
        // unique:table,column,exceptId,idColumn
        $table = $params[0] ?? '';
        $column = $params[1] ?? '';
        $exceptId = $params[2] ?? null;
        $idColumn = $params[3] ?? 'id';
        if ($table === '' || $column === '') {
            return true;
        }
        $qb = App::instance()->make('db')->table($table)->where($column, $value);
        if ($exceptId !== null && $exceptId !== '') {
            $qb->where($idColumn, '!=', $exceptId);
        }
        return !$qb->exists();
    }

    protected function checkExists(mixed $value, array $params): bool
    {
        $table = $params[0] ?? '';
        $column = $params[1] ?? 'id';
        if ($table === '') {
            return true;
        }
        return App::instance()->make('db')->table($table)->where($column, $value)->exists();
    }

    protected function addError(string $rule, string $field, array $params): void
    {
        $template = $this->customMessages["{$field}.{$rule}"]
            ?? $this->customMessages[$rule]
            ?? $this->defaultMessages[$rule]
            ?? ':field is invalid.';

        $message = str_replace(':field', $this->humanize($field), $template);
        $message = str_replace(':param', $params[0] ?? '', $message);
        foreach ($params as $i => $p) {
            $message = str_replace(':param' . $i, $p, $message);
        }
        $this->errors[$field][] = $message;
    }

    protected function humanize(string $field): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $field));
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    /**
     * Only the fields that had rules and passed.
     */
    public function validated(): array
    {
        return array_diff_key($this->validated, $this->errors);
    }
}
