<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
// Password via stdin, never a command-line argument or a committed seed.
[$script,$email,$role] = $argv + [null,null,null];
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !in_array($role,['administrator','supervisor','seller'],true)) exit("Usage: php bin/create-user.php email role < password-file\n");
$password = trim(stream_get_contents(STDIN));
if (strlen($password)<12) exit("Password must have at least 12 characters\n");
$location=getenv('GHL_LOCATION_ID');
if (!$location) exit("GHL_LOCATION_ID required\n");
query('INSERT INTO tenants(name,location_id) VALUES(?,?) ON DUPLICATE KEY UPDATE location_id=VALUES(location_id)',['Cliente',$location]);
$tenant=query('SELECT id FROM tenants WHERE location_id=?',[$location])->fetchColumn();
query('INSERT INTO users(tenant_id,email,password_hash,role) VALUES(?,?,?,?)',[$tenant,strtolower($email),password_hash($password,PASSWORD_DEFAULT),$role]);
echo "User created\n";
