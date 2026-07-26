-- FILE: /database/migrations/2026_07_26_120000_english_templates.sql
-- ---------------------------------------------------------------------
-- Convert the seeded WhatsApp/email templates and the default UI
-- language to English on an ALREADY-INSTALLED database.
-- Idempotent: safe to run more than once.
-- ---------------------------------------------------------------------

UPDATE `settings` SET `value` = 'en'
  WHERE `group` = 'general' AND `key` = 'default_language';

UPDATE `users` SET `language` = 'en' WHERE `language` = 'gu';

-- ---- WhatsApp templates ----------------------------------------------
UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'Hello {client_name} 👋\nWelcome to {company_name}! Your account is ready.\nPanel: {panel_url}'
WHERE `slug` = 'welcome';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'Your verification code is: *{otp}*\nValid for 10 minutes. Do not share it with anyone.'
WHERE `slug` = 'otp';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'{client_name}, we have received your order ✅\nInvoice: {invoice_no} | Amount: ₹{amount}\nYour hosting will be set up automatically once payment is confirmed.'
WHERE `slug` = 'order_placed';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'Hi {client_name}, a new invoice has been generated.\nInvoice: {invoice_no} | Amount: ₹{amount}\nDue date: {due_date}\nPay here: {panel_url}'
WHERE `slug` = 'invoice_generated';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'Thank you {client_name} 🙏\nWe have received your payment of ₹{amount}. Invoice {invoice_no} is now paid.'
WHERE `slug` = 'payment_received';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'Hi {client_name}, a friendly reminder that invoice {invoice_no} (₹{amount}) is due on {due_date}. Please pay on time to avoid interruption.'
WHERE `slug` = 'reminder';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'⚠️ {client_name}, invoice {invoice_no} (₹{amount}) is overdue. Please pay soon to avoid suspension of your service.'
WHERE `slug` = 'overdue';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'🔴 {client_name}, your service ({domain}) has been suspended. Please complete payment to restore it: {panel_url}'
WHERE `slug` = 'suspended';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'🟢 {client_name}, your service ({domain}) is active again. Thank you!'
WHERE `slug` = 'unsuspended';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'🎉 {client_name}, your hosting is ready!\n\n🌐 Domain: {domain}\n👤 Username: {username}\n🔑 Password: {password}\n\n📂 FTP Host: {ftp_host}\nFTP User: {ftp_user}\nFTP Password: {ftp_pass}\n\n🗄️ DB Name: {db_name}\nDB User: {db_user}\nDB Password: {db_pass}\n\nControl panel: {panel_url}'
WHERE `slug` = 'hosting_ready';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'✅ {client_name}, {domain} has been renewed successfully. New expiry date: {expiry_date}.'
WHERE `slug` = 'renewal_success';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'⏰ {client_name}, {domain} expires on {expiry_date}. Renew here: {panel_url}'
WHERE `slug` = 'expiring';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'⚠️ {client_name}, disk usage for {domain} has reached {disk_used} of {disk_limit}. Please free up some space.'
WHERE `slug` = 'disk_warning';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'⚠️ {client_name}, bandwidth usage for {domain} is close to your monthly limit.'
WHERE `slug` = 'bandwidth_warning';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'{client_name}, your support ticket #{ticket_id} has been created. Our team will respond shortly.'
WHERE `slug` = 'ticket_opened';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'{client_name}, there is a new reply on your ticket #{ticket_id}. View it here: {panel_url}'
WHERE `slug` = 'ticket_replied';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'{client_name}, your ticket #{ticket_id} has been closed. Thank you!'
WHERE `slug` = 'ticket_closed';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'🔒 {client_name}, an SSL certificate has been installed successfully for {domain}.'
WHERE `slug` = 'ssl_installed';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'🚨 ALERT: A server appears to be down. Please check immediately.'
WHERE `slug` = 'server_down';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'🚨 ALERT: Provisioning failed for {domain}. Manual retry required.'
WHERE `slug` = 'provisioning_failed';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'🛒 New order: {client_name} — {plan_name} (₹{amount}).'
WHERE `slug` = 'new_order_admin';

UPDATE `whatsapp_templates` SET `language` = 'en', `body` =
'📊 Daily summary:\nNew orders: {amount}\nSee the dashboard for full collection figures.'
WHERE `slug` = 'daily_summary';

-- ---- Email templates --------------------------------------------------
UPDATE `email_templates` SET `language` = 'en',
  `subject` = 'Welcome to {company_name}',
  `body` = '<p>Hello {client_name},</p><p>Welcome to {company_name}. Your account is ready to use.</p><p>Control panel: <a href="{panel_url}">{panel_url}</a></p>'
WHERE `slug` = 'welcome';

UPDATE `email_templates` SET `language` = 'en',
  `subject` = 'Invoice {invoice_no} — {company_name}',
  `body` = '<p>Hello {client_name},</p><p>Your invoice {invoice_no} for ₹{amount} has been generated. Due date: {due_date}.</p>'
WHERE `slug` = 'invoice';

UPDATE `email_templates` SET `language` = 'en',
  `subject` = 'Your hosting is ready — {domain}',
  `body` = '<p>Hello {client_name},</p><p>Your hosting for {domain} is ready. Login details have been sent to you on WhatsApp.</p>'
WHERE `slug` = 'hosting_ready';

UPDATE `email_templates` SET `language` = 'en',
  `subject` = 'Password Reset — {company_name}',
  `body` = '<p>Hello {client_name},</p><p>Click the link below to reset your password: <a href="{panel_url}">Reset password</a></p>'
WHERE `slug` = 'password_reset';
