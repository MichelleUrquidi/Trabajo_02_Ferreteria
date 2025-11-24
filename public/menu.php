<?php
// menu.php - Se incluye en todas las páginas tras validar la sesión.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu</title>
    <link rel="stylesheet" href="assets/css/style.css"> 
<style>
    .menu-bar { 
        background-color: #1e3a8a; color: white; padding: 10px 20px; 
        display: flex; justify-content: space-between; align-items: center; 
    }
    .menu-bar a { color: white; text-decoration: none; margin: 0 15px; }
    .menu-bar a:hover { text-decoration: underline; }
    .user-info { font-weight: bold; }
</style>
</head>
<body>
    <div class="menu-bar">
    <div>
        <a href="mantenimiento.php">Mantenimiento</a>
        <a href="suministros.php">Suministros</a>
        <a href="pedido.php">Pedidos</a>
    </div>
    <div>
        <span class="user-info"><?php echo htmlspecialchars($_SESSION['correo']); ?></span>
        <a href="cerrar_sesion.php">Cerrar sesión</a>
    </div>
</div>
</body>
</html>