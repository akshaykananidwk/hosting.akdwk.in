<?php
// FILE: /app/Controllers/Api/WhatsAppController.php
// -------------------------------------------------------------------
// 🟢 MODULE 20 — REST API: WhatsApp send. Queues a message via
// WhatsAppService (delivered by the cron worker). JSON only.
// -------------------------------------------------------------------

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\WhatsAppService;

class WhatsAppController extends Controller
{
    public function send(Request $request, string $id = ''): Response
    {
        $number  = trim((string) $request->input('number', ''));
        $message = (string) $request->input('message', '');

        // Both fields are required.
        $errors = [];
        if ($number === '') {
            $errors['number'] = 'The number field is required.';
        }
        if (trim($message) === '') {
            $errors['message'] = 'The message field is required.';
        }
        if ($errors) {
            return $this->json(['error' => 'Validation failed', 'errors' => $errors], 422);
        }

        $queueId = (new WhatsAppService())->queue($number, $message);

        return $this->json([
            'data' => [
                'id'     => $queueId,
                'status' => 'queued',
                'number' => $number,
            ],
        ], 201);
    }
}
