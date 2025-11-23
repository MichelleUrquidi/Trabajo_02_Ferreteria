<?php
session_start();
session_unset(); // Elimina todas las variables de sesión
session_destroy(); // Destruye la sesión

// Redirigir a la página de login con un mensaje de cierre
header('Location: login.php');
exit;
?>