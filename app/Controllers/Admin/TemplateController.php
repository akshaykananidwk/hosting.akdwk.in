<?php
// FILE: /app/Controllers/Admin/TemplateController.php
// -------------------------------------------------------------------
// Admin — Notification templates (ટેમ્પ્લેટ). WhatsApp + Email
// template editors with {variable} hints.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class TemplateController extends Controller
{
    /** List WhatsApp + Email templates with inline edit forms. */
    public function index(Request $request, string $id = ''): Response
    {
        $whatsapp = db()->table('whatsapp_templates')->orderBy('slug', 'asc')->get();
        $email = db()->table('email_templates')->orderBy('slug', 'asc')->get();

        return $this->view('admin.templates.index', [
            'whatsapp' => $whatsapp,
            'email'    => $email,
        ]);
    }

    /** Update a template body (+ subject for email) by id. */
    public function update(Request $request, string $id = ''): Response
    {
        $type = (string) $request->query('type', 'whatsapp');
        $now = date('Y-m-d H:i:s');

        if ($type === 'email') {
            $tpl = db()->table('email_templates')->where('id', (int) $id)->first();
            if (!$tpl) {
                return back_with('error', 'ટેમ્પ્લેટ મળ્યું નહીં');
            }
            db()->table('email_templates')->where('id', (int) $id)->update([
                'subject'    => (string) $request->input('subject', $tpl['subject']),
                'body'       => (string) $request->input('body', $tpl['body']),
                'updated_at' => $now,
            ]);
        } else {
            $tpl = db()->table('whatsapp_templates')->where('id', (int) $id)->first();
            if (!$tpl) {
                return back_with('error', 'ટેમ્પ્લેટ મળ્યું નહીં');
            }
            db()->table('whatsapp_templates')->where('id', (int) $id)->update([
                'body'       => (string) $request->input('body', $tpl['body']),
                'updated_at' => $now,
            ]);
        }

        audit('template.update', 'Template', (int) $id, ['type' => $type]);
        return back_with('success', 'ટેમ્પ્લેટ અપડેટ થયું ✅');
    }
}
