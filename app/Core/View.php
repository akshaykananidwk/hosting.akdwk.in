<?php
// FILE: /app/Core/View.php
// -------------------------------------------------------------------
// Plain-PHP template engine with layout inheritance + sections.
// Templates: /app/Views/*.php  ($this = View, $data extracted).
//   $this->extend('layouts/app');
//   $this->section('content'); ... $this->end();
//   layout માં: $this->yield('content');
// -------------------------------------------------------------------

namespace App\Core;

class View
{
    protected string $viewPath;
    protected array $shared = [];

    // Rendering state.
    protected array $sections = [];
    protected array $sectionStack = [];
    protected ?string $layout = null;

    public function __construct(string $viewPath)
    {
        $this->viewPath = rtrim($viewPath, '/');
    }

    /**
     * Share a variable with every rendered view (e.g. current user).
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Render a template (with optional layout) to a string.
     */
    public function render(string $template, array $data = []): string
    {
        $this->layout = null;
        $content = $this->renderFile($template, $data);

        // If the template called extend(), wrap it in the layout.
        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['content'] = $content;
            $content = $this->renderFile($layout, $data);
        }
        return $content;
    }

    protected function resolve(string $template): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template} ({$file})");
        }
        return $file;
    }

    protected function renderFile(string $template, array $data): string
    {
        $file = $this->resolve($template);
        $vars = array_merge($this->shared, $data);

        ob_start();
        (function () use ($file, $vars): void {
            extract($vars, EXTR_SKIP);
            include $file;
        })();
        return (string) ob_get_clean();
    }

    // ---------------------------------------------------------------
    // Directives used inside templates
    // ---------------------------------------------------------------

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * Inline one-line section: $this->set('title', 'Dashboard').
     */
    public function set(string $name, string $value): void
    {
        $this->sections[$name] = $value;
    }

    public function end(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new \RuntimeException('View::end() called without a matching section().');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Include a partial template inline (shares current data).
     */
    public function include(string $template, array $data = []): void
    {
        echo $this->renderFile($template, $data);
    }

    /**
     * Escape helper usable inside templates: <?= $this->e($x) ?>.
     */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
