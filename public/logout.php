<?php
// logout_mensaje.php - Cierra la sesión y muestra el mensaje
session_start();
session_unset(); // Elimina todas las variables de sesión
session_destroy(); // Destruye la sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sesión Cerrada</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
        }
        .logout-box { 
            background-color: #fff; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15); 
            text-align: center;
        }
        .logout-box h2 {
            color: #1e3a8a;
        }
        .logout-box a {
            text-decoration: none;
            display: inline-block;
            padding: 10px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="logout-box">
        <h2>Sesión Cerrada</h2>
        <p>La sesión se cerró correctamente, hasta la próxima.</p>
        <a href="login.php" class="btn-primary">Ir a la página de login</a>
    </div>
</body>
</html>