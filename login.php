<?php
session_start();
require 'conexion.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['correo'] ?? '';
    $clave = $_POST['clave'] ?? '';

    // Buscamos la ferretería
    $stmt = $pdo->prepare("SELECT CodRes, Correo FROM ferreterias WHERE Correo = ? AND Clave = ?");
    $stmt->execute([$correo, $clave]);
    $ferreteria = $stmt->fetch();

    if ($ferreteria) {
        // Autenticación exitosa
        $_SESSION['cod_res'] = $ferreteria['CodRes'];
        $_SESSION['correo'] = $ferreteria['Correo'];
        
        // Redirigir a la página principal de suministros
        header('Location: suministros.php');
        exit;
    } else {
        $mensaje = 'Credenciales inválidas. Intente de nuevo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso a la aplicación</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f0f4f8; }
        .login-box { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        input { margin-bottom: 10px; padding: 8px; width: 100%; box-sizing: border-box; }
        button { padding: 10px; background-color: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
<div class="login-box">
    <h2>Acceso a la aplicación</h2>
    <?php if ($mensaje) echo "<p style='color: red;'>$mensaje</p>"; ?>
    <form method="POST">
        <label>Correo</label>
        <input type="text" name="correo" placeholder="Ej: madrid01@acme.com" required>
        <label>Clave</label>
        <input type="password" name="clave" placeholder="1234" required>
        <button type="submit">Entrar</button>
    </form>
</div>
</body>
</html>