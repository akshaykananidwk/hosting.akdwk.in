<?php
// FILE: /app/Core/ValidationException.php
// -------------------------------------------------------------------
// Thrown when validation fails. For normal form posts the Router turns
// this into a redirect BACK to the form (with flashed errors + old
// input) instead of a dead-end error page. API/AJAX requests get a
// 422 JSON body with the field errors.
// -------------------------------------------------------------------

namespace App\Core;

class ValidationException extends HttpException
{
    protected array $errors;
    protected ?string $redirectTo;

    public function __construct(array $errors, ?string $redirectTo = null)
    {
        $this->errors = $errors;
        $this->redirectTo = $redirectTo;
        parent::__construct(422, 'Validation failed');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Where to send the user back to (the submitting page).
     */
    public function getRedirectTo(): string
    {
        return $this->redirectTo ?: ($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
