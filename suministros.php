<?php
require 'validar_sesion.php';
require 'conexion.php';

$cod_res = $_SESSION['cod_res'];
$categoria_seleccionada = $_GET['cat'] ?? null;
$mensaje = '';

// --- LÓGICA DE AÑADIR PRODUCTO AL PEDIDO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'comprar') {
    $cod_prod = (int)($_POST['cod_prod'] ?? 0);
    $unidades = (int)($_POST['unidades'] ?? 1);
    
    if ($cod_prod > 0 && $unidades > 0) {
        
        // 1. Verificar/Crear Pedido Provisional (Enviado=0)
        if (!isset($_SESSION['cod_ped_actual'])) {
            // No hay pedido abierto, creamos uno nuevo
            $stmt_new_ped = $pdo->prepare("INSERT INTO pedidos (Fecha, Enviado, ferreteria) VALUES (NOW(), 0, ?)");
            $stmt_new_ped->execute([$cod_res]);
            $_SESSION['cod_ped_actual'] = $pdo->lastInsertId();
        }
        $cod_ped = $_SESSION['cod_ped_actual'];

        // 2. Insertar/Actualizar PedidosProductos
        
        // Primero, intentamos actualizar si el producto ya existe en este pedido
        $stmt_check = $pdo->prepare("SELECT CodPedProd, Unidades FROM pedidosproductos WHERE Pedido = ? AND Producto = ?");
        $stmt_check->execute([$cod_ped, $cod_prod]);
        $item = $stmt_check->fetch();

        if ($item) {
            // Actualizar: sumar unidades
            $nuevas_unidades = $item['Unidades'] + $unidades;
            $stmt_update = $pdo->prepare("UPDATE pedidosproductos SET Unidades = ? WHERE CodPedProd = ?");
            $stmt_update->execute([$nuevas_unidades, $item['CodPedProd']]);
            $mensaje = "Se añadieron $unidades unidades más al pedido (Total: $nuevas_unidades).";
        } else {
            // Insertar nuevo producto
            $stmt_insert = $pdo->prepare("INSERT INTO pedidosproductos (Pedido, Producto, Unidades) VALUES (?, ?, ?)");
            $stmt_insert->execute([$cod_ped, $cod_prod, $unidades]);
            $mensaje = "Producto añadido al pedido provisional.";
        }
        
    } else {
        $mensaje = "Error: Cantidad o producto no válido.";
    }
}

// --- LECTURA DE CATEGORÍAS Y PRODUCTOS ---

// Leer todas las categorías para el menú lateral/superior
$categorias_stmt = $pdo->query("SELECT CodCat, Nombre FROM categorias");
$categorias = $categorias_stmt->fetchAll();

$productos = [];
if ($categoria_seleccionada) {
    // Leer productos de la categoría seleccionada
    $productos_stmt = $pdo->prepare("SELECT * FROM productos WHERE Categoria = ?");
    $productos_stmt->execute([$categoria_seleccionada]);
    $productos = $productos_stmt->fetchAll();
    
    // Obtener el nombre de la categoría para el título
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
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .contenedor { display: flex; padding: 20px; }
        .categorias-menu { width: 200px; padding-right: 20px; border-right: 1px solid #ccc; }
        .categorias-menu a { display: block; margin-bottom: 5px; text-decoration: none; color: #1e3a8a; }
        .productos-listado { flex-grow: 1; padding-left: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        input[type="number"] { width: 60px; }
        .mensaje { padding: 10px; background-color: #d1e7dd; color: #0f5132; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="contenedor">
        <div class="categorias-menu">
            <h3>Suministros por Categoría</h3>
            <?php foreach ($categorias as $cat): ?>
                <a href="suministros.php?cat=<?php echo $cat['CodCat']; ?>">
                    <?php echo htmlspecialchars($cat['Nombre']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="productos-listado">
            <?php if ($mensaje): ?>
                <div class="mensaje"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <?php if ($categoria_seleccionada): ?>
                <h2><?php echo $nombre_cat; ?></h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Peso</th>
                            <th>Stock</th>
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
                                        <button type="submit">Comprar</button>
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