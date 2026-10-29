<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
foreach(['002-integrations.sql','003-api-sync.sql','004-messaging.sql','005-message-cache.sql'] as $migration)db()->exec(file_get_contents(__DIR__.'/../database/'.$migration));
echo "Integration schema ready\n";
