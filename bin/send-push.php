<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/../src/Installation.php';
require __DIR__.'/../src/PushDelivery.php';
// Optional since 0.21.1: sync-inbox.php already delivers what it discovers.
// Kept so an existing cron line keeps working and never sends twice.
Installation::ensureSchema(Installation::checks(false));
echo PushDelivery::deliverPending().PHP_EOL;
