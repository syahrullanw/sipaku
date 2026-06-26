<?php

declare(strict_types=1);

$app = require __DIR__ . '/app.php';

$command = $argv[1] ?? 'list';

switch ($command) {
    case 'list':
        echo "Available commands:\n";
        echo "  migrate         Run database migrations (coming soon)\n";
        echo "  migrate:rollback Rollback the latest batch of migrations (coming soon)\n";
        echo "  seed            Seed the database with initial data (coming soon)\n";
        break;
    default:
        echo "Command [{$command}] is not available yet.\n";
        break;
}
