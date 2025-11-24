<?php
require '../src/includes/validar_sesion.php';
require '../src/config/conexion.php';

$cod_ped_actual = $_SESSION['cod_ped_actual'] ?? null; // ID del pedido provisional
$cod_res = $_SESSION['cod_res'];
$mensaje = '';
$tipo_mensaje = 'info';

// --- LÓGICA DE ACTUALIZAR UNIDADES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar' && $cod_ped_actual) {
    $cod_prod = (int)($_POST['cod_prod'] ?? 0);
    $nuevas_unidades = (int)($_POST['unidades'] ?? 1);

    if ($cod_prod > 0 && $nuevas_unidades >= 0) {
        
        try {
            $pdo->beginTransaction();

            // 1. Obtener Stock, Nombre y Unidades Anteriores
            $stmt_info = $pdo->prepare("
                SELECT p.Nombre, p.Stock, pp.Unidades AS UnidadesAnteriores
                FROM productos p 
                LEFT JOIN pedidosproductos pp ON p.CodProd = pp.Producto AND pp.Pedido = ?
                WHERE p.CodProd = ? FOR UPDATE
            ");
            $stmt_info->execute([$cod_ped_actual, $cod_prod]);
            $info = $stmt_info->fetch();

            if (!$info) {
                throw new Exception("Error: Producto no encontrado en el sistema o en el pedido.");
            }
            
            $stock_actual_bd = $info['Stock'];
            $unidades_anteriores = $info['UnidadesAnteriores'] ?? 0;
            $nombre_prod = htmlspecialchars($info['Nombre']);
            
            // 2. Calcular la Diferencia
            $diferencia_unidades = $nuevas_unidades - $unidades_anteriores; 
            // Positivo: se añade más, Negativo: se elimina stock del pedido (se devuelve a la BD)
            
            // 3. Control de Stock (solo si la diferencia es positiva, es decir, queremos AÑADIR más)
            if ($diferencia_unidades > 0) {
                if ($diferencia_unidades > $stock_actual_bd) {
                    $exceso = $diferencia_unidades - $stock_actual_bd;
                    throw new Exception("¡STOCK INSUFICIENTE! El producto **$nombre_prod** solo tiene **$stock_actual_bd** unidades disponibles. No se puede añadir $diferencia_unidades más, se excede en **$exceso** unidades.");
                }
            }
            
            // 4. Ejecutar Actualización (o Eliminación si es 0)
            if ($nuevas_unidades == 0) {
                 // Si las unidades son 0, se elimina el producto del pedido
                $stmt_del = $pdo->prepare("DELETE FROM pedidosproductos WHERE Pedido = ? AND Producto = ?");
                $stmt_del->execute([$cod_ped_actual, $cod_prod]);
                $mensaje = "Producto **$nombre_prod** eliminado del pedido provisional.";
            } else {
                // Actualiza las unidades
                $stmt_update = $pdo->prepare("UPDATE pedidosproductos SET Unidades = ? WHERE Pedido = ? AND Producto = ?");
                $stmt_update->execute([$nuevas_unidades, $cod_ped_actual, $cod_prod]);
                $mensaje = "Unidades del producto **$nombre_prod** actualizadas a $nuevas_unidades.";
            }
            
            // 5. Actualizar Stock en tabla productos
            // Si diferencia_unidades es positivo (adición), se resta del Stock. 
            // Si diferencia_unidades es negativo (eliminación), se suma al Stock.
            $stmt_stock_upd = $pdo->prepare("UPDATE productos SET Stock = Stock - ? WHERE CodProd = ?");
            $stmt_stock_upd->execute([$diferencia_unidades, $cod_prod]);
            
            $pdo->commit();
            $tipo_mensaje = 'success';

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "ERROR DE TRANSACCIÓN: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }

    } else {
        $mensaje = "Error: Cantidad o producto no válido.";
        $tipo_mensaje = 'error';
    }
}


// --- LÓGICA DE ELIMINAR O FINALIZAR (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion']) && $cod_ped_actual) {
    
    if ($_GET['accion'] === 'eliminar_producto' && isset($_GET['prod'])) {
        // ELIMINAR PRODUCTO del pedido provisional (Con devolución de stock)
        $cod_prod = (int)$_GET['prod'];
        
        try {
            $pdo->beginTransaction();

            // 1. Obtener unidades a devolver
            $stmt_unidades = $pdo->prepare("SELECT Unidades FROM pedidosproductos WHERE Pedido = ? AND Producto = ? FOR UPDATE");
            $stmt_unidades->execute([$cod_ped_actual, $cod_prod]);
            $unidades_a_devolver = $stmt_unidades->fetchColumn();

            if ($unidades_a_devolver > 0) {
                // 2. Eliminar el producto del pedido
                $stmt_del = $pdo->prepare("DELETE FROM pedidosproductos WHERE Pedido = ? AND Producto = ?");
                $stmt_del->execute([$cod_ped_actual, $cod_prod]);
                
                // 3. Devolver Stock a la tabla productos
                $stmt_stock_upd = $pdo->prepare("UPDATE productos SET Stock = Stock + ? WHERE CodProd = ?");
                $stmt_stock_upd->execute([$unidades_a_devolver, $cod_prod]);

                $pdo->commit();
                $mensaje = "Producto eliminado y **$unidades_a_devolver** unidades devueltas al stock.";
                $tipo_mensaje = 'info';

            } else {
                throw new Exception("El producto ya no está en el pedido o no tiene unidades válidas.");
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "ERROR al eliminar el producto: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
        
        // Redirigir para limpiar la URL
        header('Location: pedido.php?msg=' . urlencode($mensaje) . '&tipo_msg=' . $tipo_mensaje);
        exit;
        
    } elseif ($_GET['accion'] === 'finalizar') {
        // FINALIZAR PEDIDO (Se mantiene el stock descontado previamente)
        
        try {
            $pdo->beginTransaction();

            // 1. Marcar el pedido como ENVIADO (1)
            $stmt_fin = $pdo->prepare("UPDATE pedidos SET Enviado = 1 WHERE CodPed = ? AND ferreteria = ?");
            if (!$stmt_fin->execute([$cod_ped_actual, $cod_res])) {
                 throw new Exception("Error al marcar el pedido como finalizado.");
            }

            $pdo->commit();
            
            $pedido_finalizado_id = $cod_ped_actual;
            
            // 2. Limpiar la SESIÓN solo al finalizar correctamente
            unset($_SESSION['cod_ped_actual']);
            
            $mensaje = "Pedido n° $pedido_finalizado_id realizado con éxito.";
            $tipo_mensaje = 'success';
            header('Location: pedido.php?msg=' . urlencode($mensaje) . '&ver_finalizado=' . $pedido_finalizado_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "ERROR al finalizar el pedido: " . $e->getMessage();
            $tipo_mensaje = 'error';
            header('Location: pedido.php?msg=' . urlencode($mensaje) . '&tipo_msg=' . $tipo_mensaje);
            exit;
        }
    }
}

// Mensajes de redirección
if (isset($_GET['msg'])) {
    $mensaje = htmlspecialchars($_GET['msg']);
}

// --- CONFIGURACIÓN DE VISTA ---
$cod_ped_a_mostrar = $cod_ped_actual; // Por defecto, mostramos el provisional actual.
$es_pedido_finalizado = false;

if (isset($_GET['ver_finalizado'])) {
    // Si se acaba de finalizar, mostramos el detalle de ese pedido.
    $cod_ped_a_mostrar = (int)$_GET['ver_finalizado'];
    $es_pedido_finalizado = true;
}

$pedido_productos = [];
$estado_pedido = 'No hay productos seleccionados aún';
$total_productos = 0;

// TITULOS DINÁMICOS
$titulo_h2 = 'Pedido de compra (Provisional)';
$subtitulo_h3_1 = '';
$subtitulo_h3_2 = '';

if ($cod_ped_a_mostrar) {
    
    if ($es_pedido_finalizado) {
        // VISTA PEDIDO FINALIZADO
        $titulo_h2 = 'Pedido realizado con éxito';
        $subtitulo_h3_1 = "Pedido n° $cod_ped_a_mostrar";
        $subtitulo_h3_2 = "Detalle del pedido";
        
    } elseif ($cod_ped_a_mostrar === $cod_ped_actual) {
        // VISTA PEDIDO PROVISIONAL
        $subtitulo_h3_1 = "Pedido provisional n° $cod_ped_a_mostrar";
        $subtitulo_h3_2 = "Modifique o añada unidades y finalice la compra.";
    }


    // Consulta para obtener los productos del pedido
    $stmt_productos = $pdo->prepare("
        SELECT pp.Producto AS CodProd, p.Nombre, p.Descripcion, p.Peso, pp.Unidades
        FROM pedidosproductos pp
        JOIN productos p ON pp.Producto = p.CodProd
        WHERE pp.Pedido = ?
    ");
    $stmt_productos->execute([$cod_ped_a_mostrar]);
    $pedido_productos = $stmt_productos->fetchAll();
    $total_productos = count($pedido_productos);

    if ($total_productos == 0 && !$es_pedido_finalizado) {
        $estado_pedido = 'No hay productos seleccionados aún';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos</title>
    <link rel="stylesheet" href="style.css">
    <style>
        input[type="number"] { width: 60px; text-align: center; }
        .flex-row { display: flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="contenedor">
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <h2><?php echo $titulo_h2; ?></h2>
        <h3><?php echo $subtitulo_h3_1; ?></h3>
        <h3><?php echo $subtitulo_h3_2; ?></h3>

        <?php if ($total_productos > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Peso</th>
                        <th>Unidades</th>
                        <?php if (!$es_pedido_finalizado): ?>
                            <th>Acción</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedido_productos as $producto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($producto['Nombre']); ?></td>
                            <td><?php echo htmlspecialchars($producto['Descripcion']); ?></td>
                            <td><?php echo $producto['Peso']; ?></td>
                            <td>
                                <?php if (!$es_pedido_finalizado): ?>
                                    <form method="POST" class="flex-row">
                                        <input type="hidden" name="accion" value="actualizar">
                                        <input type="hidden" name="cod_prod" value="<?php echo $producto['CodProd']; ?>">
                                        <input type="number" name="unidades" value="<?php echo $producto['Unidades']; ?>" min="0" required>
                                        <button type="submit" class="btn-primary">Actualizar</button>
                                    </form>
                                <?php else: ?>
                                    <?php echo $producto['Unidades']; ?>
                                <?php endif; ?>
                            </td>
                            
                            <?php if (!$es_pedido_finalizado): ?>
                                <td>
                                    <a href="pedido.php?accion=eliminar_producto&prod=<?php echo $producto['CodProd']; ?>" 
                                       onclick="return confirm('¿Eliminar producto?');">
                                        <button type="button" class="btn-danger">Eliminar</button>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!$es_pedido_finalizado): ?>
                <a href="pedido.php?accion=finalizar" onclick="return confirm('¿Desea enviar el pedido y finalizar la compra?');">
                    <button class="btn-success">Finalizar Pedido</button>
                </a>
            <?php endif; ?>
            
        <?php else: ?>
            <p><?php echo $estado_pedido; ?></p>
            <p>Diríjase a <strong>**Suministros**</strong> para comenzar su compra.</p>
        <?php endif; ?>
    </div>
</body>
</html>