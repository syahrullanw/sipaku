<?php

declare(strict_types=1);

use Core\Log;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

if (PHP_SAPI === 'cli') {
    $arguments = $argv ?? [];
    $command = $arguments[1] ?? null;

    if ($command === 'finance:generate-tagihan-rutin') {
        $count = \App\Services\Finance\RecurringBillingService::generateDue();
        echo "Recurring billing cycles generated: {$count}\n";

        return;
    }

    if ($command === 'finance:reminder') {
        \App\Services\Finance\RecurringBillingService::generateDue();
        \App\Services\Finance\ReminderService::dispatchBillingReminders();
        \App\Services\Finance\ReminderService::dispatchLoanInstallmentReminders();
        \App\Services\Finance\ReminderService::dispatchHonorReminders();
        echo "Finance reminders dispatched.\n";

        return;
    }

    if ($command === 'whatsapp:dispatch') {
        $limitArgument = $arguments[2] ?? null;
        $limit = is_numeric($limitArgument) ? (int) $limitArgument : null;
        if ($limit !== null && $limit <= 0) {
            $limit = null;
        }

        $result = \App\Services\WhatsappGatewayService::dispatchPending($limit);

        echo sprintf(
            "WhatsApp queue processed: %d total, %d sent, %d requeued, %d failed.\n",
            $result['processed'],
            $result['sent'],
            $result['requeued'],
            $result['failed'],
        );

        return;
    }
}

try {
    $app->run();
} catch (\Throwable $exception) {
    $context = [
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ];

    try {
        Log::channel('app')->error('Unhandled exception during request', $context);
    } catch (\Throwable) {
        // Ignore logging failures to avoid masking the original exception.
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "[ERROR] {$context['type']}: {$context['message']} in {$context['file']}:{$context['line']}\n");
        exit(1);
    }

    http_response_code(500);

    if ((bool) config('app.debug', false)) {
        $details = sprintf(
            "%s: %s in %s on line %d\n\n%s",
            $context['type'],
            $context['message'],
            $context['file'],
            $context['line'],
            $context['trace'],
        );
        echo '<h1>Unhandled Exception</h1>';
        echo '<pre>' . htmlspecialchars($details, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Terjadi kesalahan internal pada server. Silakan coba lagi atau hubungi administrator.';
    }
}
