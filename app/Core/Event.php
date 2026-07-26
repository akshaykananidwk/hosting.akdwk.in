<?php
// FILE: /app/Core/Event.php
// -------------------------------------------------------------------
// Lightweight event/hook system. Modules આનાથી એકબીજા સાથે loosely
// coupled રહે — દા.ત. 'invoice.paid' event પર provisioning trigger.
// -------------------------------------------------------------------

namespace App\Core;

class Event
{
    /** @var array<string,array<int,array{callback:callable,priority:int}>> */
    protected array $listeners = [];

    /**
     * Register a listener. Lower priority number = runs earlier.
     */
    public function listen(string $event, callable $callback, int $priority = 10): void
    {
        $this->listeners[$event][] = ['callback' => $callback, 'priority' => $priority];
        usort($this->listeners[$event], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * Fire an event; collect each listener's return value.
     * If a listener returns false, propagation stops (like a veto hook).
     *
     * @return array<int,mixed>
     */
    public function dispatch(string $event, mixed ...$payload): array
    {
        $results = [];
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $result = ($listener['callback'])(...$payload);
            $results[] = $result;
            if ($result === false) {
                break;
            }
        }
        return $results;
    }

    /**
     * Filter hook: pass a value through all listeners, each may modify it.
     */
    public function filter(string $event, mixed $value, mixed ...$extra): mixed
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $value = ($listener['callback'])($value, ...$extra);
        }
        return $value;
    }

    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }
}
