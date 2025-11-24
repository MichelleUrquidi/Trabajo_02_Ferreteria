<?php
session_start();
require '../src/config/conexion.php';

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
    <link rel="stylesheet" href="assets/css/style.css"> 
    <style>
        /* CSS para centrar y estilizar la caja de login */
        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
        }
        .login-box { 
            background-color: #fff; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15); 
            width: 300px; /* Ancho ajustado */
        }
        input { 
            margin-bottom: 15px; 
            padding: 10px; 
            width: 100%; 
            box-sizing: border-box; 
            border: 1px solid #ccc;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="login-box">
    <h2>Acceso al Backstore</h2>
    <?php if ($mensaje) echo "<p class='mensaje error'>$mensaje</p>"; ?>
    <form method="POST">
        <label>Correo:</label>
        <input type="email" name="correo" placeholder="Ej: madrid01@acme.com" required>
        <label>Contraseña:</label>
        <input type="password" name="clave" placeholder="Contraseña" required>
        <button type="submit" class="btn-primary" style="width: 100%;">Entrar</button>
    </form>
</div>
</body>
</html>