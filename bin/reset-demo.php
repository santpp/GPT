<?php
declare(strict_types=1);
// Deja la subcuenta de demostración como estaba al empezar. Uso: php bin/reset-demo.php
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/DemoSpace.php';
DemoSpace::ensure();
DemoSpace::reset();
echo "Demostración reiniciada\n";
