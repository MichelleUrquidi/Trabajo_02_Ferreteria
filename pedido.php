<?php
require 'validar_sesion.php';
require 'conexion.php';

$cod_ped = $_SESSION['cod_ped_actual'] ?? null;
$cod_res = $_SESSION['cod_res'];
$mensaje = '';

// --- LÓGICA DE ELIMINAR O FINALIZAR ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion'])) {
    
    if ($_GET['accion'] === 'eliminar' && isset($_GET['item'])) {
        // ELIMINAR PRODUCTO del pedido provisional
        $cod_ped_prod = (int)$_GET['item'];
        
        // Ejecutamos el DELETE asegurándonos que el item pertenece al pedido actual
        $stmt_del = $pdo->prepare("DELETE pp FROM pedidosproductos pp INNER JOIN pedidos p ON pp.Pedido = p.CodPed WHERE pp.CodPedProd = ? AND p.CodPed = ?");
        $stmt_del->execute([$cod_ped_prod, $cod_ped]);
        
        $mensaje = "Producto eliminado del pedido.";
        header('Location: pedido.php?msg=' . urlencode($mensaje));
        exit;
        
    } elseif ($_GET['accion'] === 'finalizar' && $cod_ped) {
        // FINALIZAR PEDIDO (Validar)
        
        // 1. Marcar el pedido como ENVIADO (1)
        $stmt_fin = $pdo->prepare("UPDATE pedidos SET Enviado = 1 WHERE CodPed = ? AND ferreteria = ?");
        $stmt_fin->execute([$cod_ped, $cod_res]);
        
        // 2. Limpiar la sesión para que se cree un nuevo pedido la próxima vez
        unset($_SESSION['cod_ped_actual']);
        
        $mensaje = "Pedido realizado con éxito. Pedido n° $cod_ped. Detalle del pedido mostrado abajo.";
        // Redirigimos sin el cod_ped_actual para mostrar el detalle del pedido finalizado
        // En este punto, $cod_ped mantiene el ID del pedido recién finalizado
        header('Location: pedido.php?msg=' . urlencode($mensaje) . '&ver_finalizado=' . $cod_ped);
        exit;
    }
}

// Mensajes de redirección
if (isset($_GET['msg'])) {
    $mensaje = htmlspecialchars($_GET['msg']);
}
if (isset($_GET['ver_finalizado'])) {
    // Si se acaba de finalizar, mostramos el detalle de ese pedido
    $cod_ped_a_mostrar = (int)$_GET['ver_finalizado'];
} else {
    // Si no, mostramos el pedido provisional actual
    $cod_ped_a_mostrar = $cod_ped;
}


$pedido_items = [];
$estado_pedido = 'No hay productos seleccionados aún'; // Estado por defecto
$total_productos = 0;

if ($cod_ped_a_mostrar) {
    // Consulta para obtener los productos del pedido
    $stmt_items = $pdo->prepare("
        SELECT pp.CodPedProd, p.Nombre, p.Descripcion, p.Peso, pp.Unidades
        FROM pedidosproductos pp
        JOIN productos p ON pp.Producto = p.CodProd
        WHERE pp.Pedido = ?
    ");
    $stmt_items->execute([$cod_ped_a_mostrar]);
    $pedido_items = $stmt_items->fetchAll();
    $total_productos = count($pedido_items);

    if ($total_productos > 0) {
        $estado_pedido = 'Pedido de compra (Provisional)';
    } else {
        // Si hay un pedido ID pero está vacío
        $estado_pedido = 'No hay productos seleccionados aún';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido Provisional</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .contenedor { padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        .mensaje { padding: 10px; background-color: #d1e7dd; color: #0f5132; border-radius: 5px; margin-bottom: 15px; }
        .btn-finalizar { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 20px; }
        .btn-eliminar { background-color: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="contenedor">
        <?php if ($mensaje): ?>
            <div class="mensaje"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <h2><?php echo $estado_pedido; ?></h2>

        <?php if ($total_productos > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Peso</th>
                        <th>Unidades</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedido_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['Nombre']); ?></td>
                            <td><?php echo htmlspecialchars($item['Descripcion']); ?></td>
                            <td><?php echo $item['Peso']; ?></td>
                            <td><?php echo $item['Unidades']; ?></td>
                            <td>
                                <?php if ($cod_ped_a_mostrar === $cod_ped): ?>
                                    <a href="pedido.php?accion=eliminar&item=<?php echo $item['CodPedProd']; ?>">
                                        <button type="button" class="btn-eliminar">Eliminar</button>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($cod_ped_a_mostrar === $cod_ped): ?>
                <a href="pedido.php?accion=finalizar" onclick="return confirm('¿Desea enviar el pedido y finalizar la compra?');">
                    <button class="btn-finalizar">Finalizar Pedido</button>
                </a>
            <?php endif; ?>
            
        <?php else: ?>
            <p>Por favor, diríjase a **Suministros** para añadir productos a su pedido.</p>
        <?php endif; ?>
    </div>
</body>
</html>