<?php
// FILE: /app/Core/Controller.php
// -------------------------------------------------------------------
// Base controller — બધા controllers આ extend કરે. View render,
// JSON, redirect અને validation ના shortcut આપે.
// -------------------------------------------------------------------

namespace App\Core;

abstract class Controller
{
    /**
     * Render a view into an HTML Response.
     */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::view($template, $data, $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    /**
     * Redirect back to the previous URL (from Referer), default to '/'.
     */
    protected function back(): Response
    {
        $url = $_SERVER['HTTP_REFERER'] ?? '/';
        return Response::redirect($url);
    }

    /**
     * Validate request input; throws HttpException(422) on failure with
     * flashed errors + old input (for classic form flows).
     */
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            Session::flash('errors', $validator->errors());
            Session::flashInput($request->except(['password', 'password_confirmation', '_token']));
            if ($request->wantsJson()) {
                throw new HttpException(422, json_encode(['errors' => $validator->errors()], JSON_UNESCAPED_UNICODE) ?: '');
            }
            throw new HttpException(422, 'Validation failed');
        }
        return $validator->validated();
    }

    /**
     * Abort with a status code unless the condition holds.
     */
    protected function authorize(bool $condition, int $code = 403, string $message = 'પ્રવેશ નથી (Forbidden)'): void
    {
        if (!$condition) {
            throw new HttpException($code, $message);
        }
    }
}
