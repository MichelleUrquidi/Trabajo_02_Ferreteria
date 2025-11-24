<?php 
$host = '127.0.0.1';
$dbname = 'pedidos';
$user = 'root';
$pass = 'root';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\Throwable $th) {
    //throw $th;
    die(("Error de conexión: " . $th->getMessage()));
}