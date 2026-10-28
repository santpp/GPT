<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
db()->exec(file_get_contents(__DIR__.'/../database/002-integrations.sql'));
echo "Integration schema ready\n";
