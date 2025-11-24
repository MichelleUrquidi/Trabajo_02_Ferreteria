<?php
require '../src/includes/validar_sesion.php';
require '../src/config/conexion.php';

$cod_res = $_SESSION['cod_res'];
$categoria_seleccionada = $_GET['cat'] ?? null;
$mensaje = '';
$tipo_mensaje = 'info';

// --- LÓGICA DE AÑADIR PRODUCTO AL PEDIDO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'comprar') {
    $cod_prod = (int)($_POST['cod_prod'] ?? 0);
    $unidades_a_añadir = (int)($_POST['unidades'] ?? 1);
    
    if ($cod_prod > 0 && $unidades_a_añadir > 0) {
        
        try {
            $pdo->beginTransaction(); // Iniciamos la transacción

            $cod_ped = $_SESSION['cod_ped_actual'] ?? null;
            $nuevo_pedido_creado = false;

            // 1. Obtener/Crear Pedido Provisional (Solución al error 1452)
            if (!$cod_ped) {
                $stmt_new_ped = $pdo->prepare("INSERT INTO pedidos (Fecha, Enviado, ferreteria) VALUES (NOW(), 0, ?)");
                
                if ($stmt_new_ped->execute([$cod_res])) {
                    $cod_ped = $pdo->lastInsertId();
                    $_SESSION['cod_ped_actual'] = $cod_ped;
                    $nuevo_pedido_creado = true;
                } else {
                    // Si el INSERT falla, lanzamos una excepción para que el catch haga rollback
                    throw new Exception("ERROR CRÍTICO: No se pudo crear la cabecera del pedido. Revise la clave foránea ferreteria.");
                }
            }

            // 2. Control de Stock
            // Bloqueamos la fila de productos para asegurar la lectura y actualización atómicas (FOR UPDATE)
            $stmt_stock = $pdo->prepare("
                SELECT p.Nombre, p.Stock, pp.Unidades 
                FROM productos p 
                LEFT JOIN pedidosproductos pp ON p.CodProd = pp.Producto AND pp.Pedido = ?
                WHERE p.CodProd = ? FOR UPDATE
            ");
            $stmt_stock->execute([$cod_ped, $cod_prod]);
            $info = $stmt_stock->fetch();

            if (!$info) {
                // Si la ferretería o producto no existen, la transacción fallará
                throw new Exception("Error: Producto no encontrado o problema con el pedido.");
            }

            $stock_actual_bd = $info['Stock'];
            $unidades_actuales_en_pedido = $info['Unidades'] ?? 0;
            
            // Verificación: Se añaden unidades al stock ya en pedido. El stock se actualiza en base a las unidades A AÑADIR.
            if ($unidades_a_añadir > $stock_actual_bd) {
                $exceso = $unidades_a_añadir - $stock_actual_bd;
                // Si la adición solicitada supera el stock actual disponible, lanzamos error
                throw new Exception("¡STOCK INSUFICIENTE! El producto **" . htmlspecialchars($info['Nombre']) . "** tiene **$stock_actual_bd** unidades disponibles. No se puede añadir $unidades_a_añadir, se excede en **$exceso** unidades.");
            }

            // 3. Insertar/Actualizar PedidosProductos
            $unidades_totales_solicitadas = $unidades_actuales_en_pedido + $unidades_a_añadir;
            
            if ($unidades_actuales_en_pedido > 0) {
                // Actualizar: sumar unidades
                $stmt_update = $pdo->prepare("UPDATE pedidosproductos SET Unidades = ? WHERE Pedido = ? AND Producto = ?");
                $stmt_update->execute([$unidades_totales_solicitadas, $cod_ped, $cod_prod]);
                $mensaje = "Se añadieron $unidades_a_añadir unidades al pedido (Total: $unidades_totales_solicitadas).";
            } else {
                // Insertar nuevo producto
                $stmt_insert = $pdo->prepare("INSERT INTO pedidosproductos (Pedido, Producto, Unidades) VALUES (?, ?, ?)");
                $stmt_insert->execute([$cod_ped, $cod_prod, $unidades_a_añadir]);
                $mensaje = "Producto añadido al pedido provisional.";
            }
            
            // 4. Descontar Stock de Productos 
            $stmt_stock_upd = $pdo->prepare("UPDATE productos SET Stock = Stock - ? WHERE CodProd = ?");
            $stmt_stock_upd->execute([$unidades_a_añadir, $cod_prod]);
            
            $pdo->commit(); // Si todo fue bien, aplicamos todos los cambios.
            $tipo_mensaje = 'success';

        } catch (Exception $e) {
            $pdo->rollBack(); // Si algo falló (incluyendo el control de stock o la clave foránea)
            
            // Si creamos un pedido y luego falló, limpiamos la sesión para no dejar un ID colgado
            if (isset($nuevo_pedido_creado) && $nuevo_pedido_creado) {
                 unset($_SESSION['cod_ped_actual']);
            }
            
            $mensaje = "ERROR DE TRANSACCIÓN: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
        
    } else {
        $mensaje = "Error: Cantidad o producto no válido.";
        $tipo_mensaje = 'error';
    }
}

// --- LECTURA DE CATEGORÍAS Y PRODUCTOS ---

// Leer todas las categorías para el menú lateral/superior
$categorias_stmt = $pdo->query("SELECT CodCat, Nombre FROM categorias");
$categorias = $categorias_stmt->fetchAll();

// Consulta de productos: es importante mostrar el stock, ya actualizado
$productos = [];
if ($categoria_seleccionada) {
    $productos_stmt = $pdo->prepare("SELECT CodProd, Nombre, Descripcion, Peso, Stock FROM productos WHERE Categoria = ?");
    $productos_stmt->execute([$categoria_seleccionada]);
    $productos = $productos_stmt->fetchAll();
    
    $cat_name_stmt = $pdo->prepare("SELECT Nombre FROM categorias WHERE CodCat = ?");
    $cat_name_stmt->execute([$categoria_seleccionada]);
    $nombre_cat = $cat_name_stmt->fetchColumn() ?: 'Productos';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Suministros</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .contenedor-main { display: flex; padding: 20px; }
        .categorias-menu { width: 200px; padding-right: 20px; border-right: 1px solid #ccc; }
        .categorias-menu a { display: block; margin-bottom: 5px; text-decoration: none; color: #1e3a8a; }
        input[type="number"] { width: 60px; text-align: center; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="contenedor-main contenedor">
        <div class="categorias-menu">
            <h3>Suministros por Categoría</h3>
            <?php foreach ($categorias as $cat): ?>
                <a href="suministros.php?cat=<?php echo $cat['CodCat']; ?>">
                    <?php echo htmlspecialchars($cat['Nombre']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="productos-listado" style="flex-grow: 1; padding-left: 20px;">
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <?php if ($categoria_seleccionada): ?>
                <h2><?php echo $nombre_cat; ?></h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Peso</th>
                            <th>Stock Actual</th>
                            <th>Comprar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $prod): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($prod['Nombre']); ?></td>
                                <td><?php echo htmlspecialchars($prod['Descripcion']); ?></td>
                                <td><?php echo $prod['Peso']; ?></td>
                                <td><?php echo $prod['Stock']; ?></td>
                                <td>
                                    <form method="POST" action="suministros.php?cat=<?php echo $categoria_seleccionada; ?>">
                                        <input type="hidden" name="accion" value="comprar">
                                        <input type="hidden" name="cod_prod" value="<?php echo $prod['CodProd']; ?>">
                                        <input type="number" name="unidades" value="1" min="1" required>
                                        <button type="submit" class="btn-primary">Comprar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <h3>Seleccione una categoría a la izquierda para ver los productos.</h3>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>