<?php 
//validar_seseion.php - Control de la sesión
session_start();
if (!isset($_SESSION['cod_res'])) {
    # csi no hay código de ferreteria, redirigir al login
    header("Location: login.php");
    exit();
}