<?php
session_start();

require_once 'universal.php';

// --- (TODA TU LÓGICA PHP SE MANTIENE EXACTAMENTE IGUAL) ---
if (!isset($con) || !$con) {
    die("Error Crítico: La conexión a la base de datos no se pudo establecer. Verifique el archivo 'universal.php'.");
}
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    header('Location: logueo.php?error=no_autenticado');
    exit();
}
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$mensaje = '';
$error = '';
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'clase_creada') $mensaje = "Clase creada exitosamente.";
    if ($_GET['success'] === 'clase_eliminada') $mensaje = "Clase eliminada correctamente.";
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion']) && $_GET['accion'] === 'eliminar_clase') {
    if ($rol === 'profesor' || $rol === 'administrador') {
        $clase_id_a_eliminar = $_GET['id'] ?? 0;
        try {
            $con->beginTransaction();
            $stmt_owner = $con->prepare("SELECT cuenta_iduser FROM clases WHERE idclases = ?");
            $stmt_owner->execute([$clase_id_a_eliminar]);
            $clase_owner = $stmt_owner->fetchColumn();
            if ($rol === 'administrador' || ($rol === 'profesor' && $clase_owner == $usuario_id)) {
                $stmt1 = $con->prepare("DELETE FROM cuenta_has_clases WHERE clases_idclases = ?");
                $stmt1->execute([$clase_id_a_eliminar]);
                $stmt2 = $con->prepare("DELETE FROM publicaciones WHERE clases_idclases = ?");
                $stmt2->execute([$clase_id_a_eliminar]);
                $stmt3 = $con->prepare("DELETE FROM clases WHERE idclases = ?");
                $stmt3->execute([$clase_id_a_eliminar]);
                $con->commit();
                header('Location: dashboard.php?success=clase_eliminada');
                exit();
            } else {
                throw new Exception("No tienes permiso para eliminar esta clase.");
            }
        } catch (Exception $e) {
            if ($con->inTransaction()) $con->rollBack();
            $_SESSION['error_message'] = "Error al eliminar la clase: " . $e->getMessage();
            header('Location: dashboard.php');
            exit();
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear_clase' && ($rol === 'profesor' || $rol === 'administrador')) {
        $nom_clase = trim($_POST['nom_clase'] ?? '');
        if (!empty($nom_clase)) {
            $codel = substr(md5(uniqid()), 0, 8);
            $query = "INSERT INTO clases (nom_clase, codel, cuenta_iduser) VALUES (?, ?, ?)";
            $stmt = $con->prepare($query);
            if ($stmt->execute([$nom_clase, $codel, $usuario_id])) {
                header('Location: dashboard.php?success=clase_creada');
                exit();
            } else {
                $error = "Error al crear la clase";
            }
        } else {
            $error = "El nombre de la clase no puede estar vacío";
        }
    } elseif ($accion === 'unirse_codigo' && $rol === 'estudiante') {
        $codigo = trim($_POST['codigo_clase'] ?? '');
        if (!empty($codigo)) {
            $stmt = $con->prepare("SELECT idclases FROM clases WHERE codel = ?");
            $stmt->execute([$codigo]);
            $fila = $stmt->fetch();
            if ($fila) {
                $cid = $fila['idclases'];
                $stmt_check = $con->prepare("SELECT COUNT(*) FROM cuenta_has_clases WHERE cuenta_iduser = ? AND clases_idclases = ?");
                $stmt_check->execute([$usuario_id, $cid]);
                if ($stmt_check->fetchColumn() == 0) {
                    $stmt_ins = $con->prepare("INSERT INTO cuenta_has_clases (cuenta_iduser, clases_idclases) VALUES (?, ?)");
                    if ($stmt_ins->execute([$usuario_id, $cid])) {
                        $mensaje = 'Te has unido a la clase con éxito';
                    } else {
                        $error = 'Error al unirse a la clase';
                    }
                } else {
                    $error = 'Ya estás inscrito en esta clase';
                }
            } else {
                $error = 'Código de clase inválido';
            }
        } else {
            $error = 'Ingresa el código de la clase';
        }
    }
}
$clases = [];
if ($rol === 'administrador') {
    $query_clases = "SELECT c.*, i.nom, i.paterno, i.materno FROM clases c LEFT JOIN info i ON c.cuenta_iduser = i.cuenta_iduser ORDER BY c.nom_clase";
    $stmt = $con->prepare($query_clases);
} elseif ($rol === 'profesor') {
    $query_clases = "SELECT c.*, i.nom, i.paterno, i.materno FROM clases c LEFT JOIN info i ON c.cuenta_iduser = i.cuenta_iduser WHERE c.cuenta_iduser = ? ORDER BY c.nom_clase";
    $stmt = $con->prepare($query_clases);
    $stmt->execute([$usuario_id]);
} elseif ($rol === 'estudiante') {
    $query_clases = "SELECT c.*, i.nom, i.paterno, i.materno FROM clases c INNER JOIN cuenta_has_clases chc ON c.idclases = chc.clases_idclases LEFT JOIN info i ON c.cuenta_iduser = i.cuenta_iduser WHERE chc.cuenta_iduser = ? ORDER BY c.nom_clase";
    $stmt = $con->prepare($query_clases);
    $stmt->execute([$usuario_id]);
}
if (isset($stmt)) {
    if ($rol === 'administrador') $stmt->execute();
    $clases = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - UET</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Lora", serif;
            background-color: #570a0a;
            color: #000000;
            margin: 0;
            padding: 20px;
        }

        .contenedor-principal {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #f7ebdd;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.3);
        }

        .header-dashboard {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #a02222;
        }

        .header-dashboard h1 {
            color: #a02222;
            font-family: "Cinzel", serif;
            font-size: 36px;
            margin: 0;
        }

        .info-usuario {
            text-align: right;
        }

        .nombre-usuario {
            font-weight: bold;
            color: #a02222;
            font-size: 18px;
        }

        .rol-usuario {
            color: #666;
            font-size: 14px;
            text-transform: capitalize;
        }

        .btn-cerrar-sesion,
        .btn-perfil,
        .btn-gestion-usuarios {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            margin-top: 10px;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn-cerrar-sesion {
            background-color: #dc3545;
            color: white;
        }

        .btn-cerrar-sesion:hover {
            background-color: #c82333;
        }

        .btn-perfil {
            background-color: #a02222;
            color: white;
            margin-right: 8px;
        }

        .btn-perfil:hover {
            background-color: #570a0a;
        }

        .btn-gestion-usuarios {
            background-color: #6c757d;
            color: white;
        }

        .btn-gestion-usuarios:hover {
            background-color: #5a6268;
        }

        .seccion-crear-clase {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 2px 4px 8px rgba(0, 0, 0, 0.1);
        }

        .seccion-crear-clase h3 {
            color: #a02222;
            font-family: "Cinzel", serif;
            margin-bottom: 15px;
        }

        .form-crear-clase {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .form-crear-clase input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: "Lora", serif;
            font-size: 16px;
        }

        .form-crear-clase input:focus {
            outline: none;
            border-color: #a02222;
        }

        .btn-crear {
            background-color: #a02222;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-crear:hover {
            background-color: #570a0a;
        }

        .clases-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .clase-card {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 2px 4px 8px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #a02222;
            transition: transform 0.3s ease;
        }

        .clase-card:hover {
            transform: translateY(-5px);
        }

        .clase-header {
            margin-bottom: 15px;
        }

        .clase-nombre {
            font-size: 20px;
            font-weight: bold;
            color: #a02222;
            margin-bottom: 5px;
        }

        .clase-codigo {
            color: #666;
            font-size: 14px;
            font-family: monospace;
        }

        .clase-profesor {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .clase-acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-accion {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-ver-clase {
            background-color: #007bff;
            color: white;
        }

        .btn-ver-clase:hover {
            background-color: #0056b3;
        }

        .btn-tablon {
            background-color: #28a745;
            color: white;
        }

        .btn-tablon:hover {
            background-color: #1e7e34;
        }

        .btn-editar-clase {
            background-color: #ffc107;
            color: #000;
        }

        .btn-editar-clase:hover {
            background-color: #e0a800;
        }

        .btn-eliminar-clase {
            background-color: #dc3545;
            color: white;
        }

        .btn-eliminar-clase:hover {
            background-color: #c82333;
        }

        .mensaje {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
        }

        .mensaje.exito {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .mensaje.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .sin-clases {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 2px 4px 8px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .header-dashboard {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .form-crear-clase {
                flex-direction: column;
            }

            .clases-container {
                grid-template-columns: 1fr;
            }
        }


        /* Estilos modal */
        #modalOverlay {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
        }

        #confirmModal {
            background-color: #1c1c1c;
            color: #f7ebdd;
            margin: auto;
            padding: 30px;
            border: 1px solid #a02222;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            text-align: center;
            font-family: 'Lora', serif;
        }

        #confirmModal h3 {
            font-family: 'Cinzel', serif;
            color: #dc3545;
            margin-top: 0;
        }

        .modal-acciones {
            margin-top: 25px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .modal-acciones button {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        #confirmBtn {
            background-color: #dc3545;
            color: white;
        }

        #confirmBtn:hover {
            background-color: #c82333;
        }

        #cancelBtn {
            background-color: #6c757d;
            color: white;
        }

        #cancelBtn:hover {
            background-color: #5a6268;
        }
    </style>
</head>

<body>
    <div class="contenedor-principal">
        <div class="header-dashboard">
            <h1>🏫PRINCIPAL - UET</h1>
            <div class="info-usuario">
                <div class="nombre-usuario">👤 <?php echo htmlspecialchars($nombre_usuario); ?></div>
                <div class="rol-usuario">Rol: <?php echo htmlspecialchars($rol); ?></div>
                <a href="perfil.php" class="btn-perfil">👤 Perfil</a>
                <a href="cerrar_sesion.php" class="btn-cerrar-sesion">🚪 Cerrar Sesión</a>
                <?php if ($rol === 'administrador'): ?>
                    <a href="usuarios.php" class="btn-gestion-usuarios">👥 Gestión de Usuarios</a>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($mensaje): ?><div class="mensaje exito"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mensaje error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($rol === 'estudiante'): ?>
            <div class="seccion-crear-clase">
                <h3>🔑 Unirse a una Clase</h3>
                <form class="form-crear-clase" method="POST" action="dashboard.php">
                    <input type="hidden" name="accion" value="unirse_codigo">
                    <input type="text" name="codigo_clase" placeholder="Código de la clase" required>
                    <button type="submit" class="btn-crear">🎓 Unirse</button>
                </form>
            </div>
        <?php endif; ?>
        <?php if ($rol === 'profesor' || $rol === 'administrador'): ?>
            <div class="seccion-crear-clase">
                <h3>➕ Crear Nueva Clase</h3>
                <form class="form-crear-clase" method="POST" action="dashboard.php">
                    <input type="hidden" name="accion" value="crear_clase">
                    <input type="text" name="nom_clase" placeholder="Nombre de la clase" required>
                    <button type="submit" class="btn-crear">📚 Crear Clase</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="clases-container">
            <?php if (empty($clases)): ?>
                <div class="sin-clases">
                    <h3>📭 No hay clases disponibles</h3>
                </div>
            <?php else: ?>
                <?php foreach ($clases as $clase): ?>
                    <div class="clase-card">
                        <div class="clase-header">
                            <div class="clase-nombre">📚 <?php echo htmlspecialchars($clase['nom_clase']); ?></div>
                            <div class="clase-codigo">Código: <?php echo htmlspecialchars($clase['codel']); ?></div>
                            <?php if ($clase['nom']): ?>
                                <div class="clase-profesor">👨‍🏫 Profesor: <?php echo htmlspecialchars($clase['nom'] . ' ' . $clase['paterno'] . ' ' . $clase['materno']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="clase-acciones">
                            <a href="<?php echo ($rol === 'estudiante') ? 'claseEstudiante.php' : 'clase.php'; ?>?id=<?php echo $clase['idclases']; ?>" class="btn-accion btn-ver-clase">👁️ Ver Clase</a>
                            <a href="publicaciones.php?clases_idclases=<?php echo $clase['idclases']; ?>" class="btn-accion btn-tablon">📋 Tablón</a>
                            <?php if (($rol === 'profesor' && $clase['cuenta_iduser'] == $usuario_id) || $rol === 'administrador'): ?>
                                <a href="editar_clase.php?id=<?php echo $clase['idclases']; ?>" class="btn-accion btn-editar-clase">✏️ Editar</a>
                                <a href="dashboard.php?accion=eliminar_clase&id=<?php echo $clase['idclases']; ?>"
                                    class="btn-accion btn-eliminar-clase"
                                    onclick="openConfirmModal(this.href); return false;">🗑️ Eliminar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="modalOverlay">
        <div id="confirmModal">
            <h3>Confirmar Eliminación</h3>
            <p>¿Estás seguro de que quieres eliminar esta clase? Esta acción es irreversible y borrará todas las publicaciones y estudiantes inscritos.</p>
            <div class="modal-acciones">
                <button id="cancelBtn">Cancelar</button>
                <button id="confirmBtn">Aceptar</button>
            </div>
        </div>
    </div>

    <script>
        // Script para ocultar mensajes de éxito/error
        setTimeout(function() {
            const messages = document.querySelectorAll('.mensaje');
            if (messages) {
                messages.forEach(function(message) {
                    message.style.transition = 'opacity 0.5s';
                    message.style.opacity = '0';
                    setTimeout(function() {
                        message.remove();
                    }, 500);
                });
            }
        }, 5000);

        /* =============================================================== */
        /* ==== 4. LÓGICA JAVASCRIPT PARA EL MODAL (añadido al final) ==== */
        /* =============================================================== */
        const modalOverlay = document.getElementById('modalOverlay');
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        let deleteUrl = '';

        // Función para abrir el modal
        function openConfirmModal(url) {
            deleteUrl = url; // Guarda la URL de eliminación
            modalOverlay.style.display = 'flex'; // Muestra el modal
        }

        // Función para cerrar el modal
        function closeConfirmModal() {
            modalOverlay.style.display = 'none'; // Oculta el modal
        }

        // Evento para el botón de Aceptar
        confirmBtn.addEventListener('click', function() {
            window.location.href = deleteUrl; // Redirige a la URL guardada
        });

        // Evento para el botón de Cancelar
        cancelBtn.addEventListener('click', closeConfirmModal);

        // Opcional: Cerrar el modal si se hace clic en el fondo oscuro
        modalOverlay.addEventListener('click', function(event) {
            if (event.target === modalOverlay) {
                closeConfirmModal();
            }
        });
    </script>
</body>

</html>