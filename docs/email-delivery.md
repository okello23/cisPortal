# Email delivery setup

The application defaults to `MAIL_MAILER=log` for local development. That mode does **not** deliver messages to inboxes. Configure an SMTP account authorised to send from your organisation's domain before creating users or assigning Nutrition tasks.

## Application settings

For a Gmail sender account, turn on Google 2-Step Verification and create an App Password in that sender account. Use the App Password in `MAIL_PASSWORD`, not the regular Gmail password. Google may not offer App Passwords for some managed or Advanced Protection accounts; in that case, use an approved Workspace SMTP relay or another transactional mail provider.

For a Gmail sender, the corresponding settings are:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-sender@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_FROM_ADDRESS=your-sender@gmail.com
MAIL_FROM_NAME="CPHL Support Portal"
```

Use one dedicated sender account rather than each recipient's Gmail account. Do not put the App Password in source control or paste it into chat.

Set these values in `.env` using details from your mail provider (do not commit credentials):

```dotenv
APP_URL=https://your-public-portal.example
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.your-provider.example
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=notifications@your-domain.example
MAIL_FROM_NAME="CPHL Support Portal"
```

Use the host, port, encryption scheme and credentials supplied by the provider. The From address must be approved by that provider. After editing `.env`, run `php artisan config:clear`. Do not use the `failover` mailer to silently fall back to `log`; that would make failed deliveries appear successful.

For a deployed server, run Laravel's scheduler continuously (for example, `php artisan schedule:work` during development or `php artisan schedule:run` every minute via cron). The `nutrition-tasks:send-reminders` command runs daily at 08:00 Africa/Kampala and sends to assigned Nutrition team users whose tasks are incomplete and due within one day or overdue. It records a reminder timestamp only after the mail transport accepts the message.

## Sender-domain setup

Ask the domain/mail administrator to publish the provider's SPF and DKIM DNS records, then configure DMARC for the sending domain. Verify that the domain in `MAIL_FROM_ADDRESS` aligns with the authenticated sender. Check provider delivery logs and a test message's `Authentication-Results` headers for SPF, DKIM and DMARC pass. Inbox placement cannot be guaranteed by the application: receiving services also consider reputation, spam complaints and user preferences.

## Verification

1. Send a test by creating a disposable Nutrition user. Verify the recipient receives the account email; remove or deactivate the test user afterwards.
   First, you can run `php artisan mail:send-test your-address@gmail.com` to test SMTP without creating any user.
2. Assign a task to that user. Verify the assignment message arrives.
3. Set the task due tomorrow and run `php artisan nutrition-tasks:send-reminders`. Verify one reminder arrives and a second run on the same day sends none.
4. If a message does not arrive, check the application log and SMTP provider's delivery log. A successful SMTP send confirms the provider accepted the message, not that the recipient placed it in Primary rather than Spam or Promotions.

Existing accounts created while email was in `log` mode will not automatically receive a new password. Use the password-reset flow after SMTP is working, or have an administrator set a new password in the user-management page.
