<?php
// validar_sesion.php - Control de la sesión
session_start();
if (isset($_SESSION['cod-res'])) {
    # si no hay codigo de ferreteria, redirigir al login
    header("Location: login.php");
    exit();
}