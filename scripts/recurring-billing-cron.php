#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

$count = \App\Services\Finance\RecurringBillingService::generateDue();
if ($count > 0) {
    fwrite(STDOUT, "Recurring billing cycles generated: {$count}\n");
} else {
    fwrite(STDOUT, "No recurring billing cycles were due today.\n");
}

exit(0);
