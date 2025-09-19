<?php
session_start();
include 'universal.php';


// 1. VERIFICACIÓN DE SESIÓN Y ROL
include 'verificar_sesion.php';
$rol_usuario = $_SESSION['rol'];
$usuario_id = $_SESSION['usuario_id'];
$mensaje = '';

// Lógica para calificar (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'calificar') {
    if ($rol_usuario === 'profesor' || $rol_usuario === 'administrador') {
        $tarea_id = filter_input(INPUT_POST, 'tarea_id', FILTER_VALIDATE_INT);
        $estudiante_id = filter_input(INPUT_POST, 'estudiante_id', FILTER_VALIDATE_INT);
        $nota = filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_FLOAT);

        if ($tarea_id && $estudiante_id && is_numeric($nota) && $nota >= 0 && $nota <= 100) {
            try {
                $sql_update = "UPDATE Entrega SET nota = ? WHERE tarea_idtarea = ? AND cuenta_iduser = ?";
                $stmt_update = $con->prepare($sql_update);
                $stmt_update->execute([$nota, $tarea_id, $estudiante_id]);
                $_SESSION['success_message'] = "Calificación guardada correctamente.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error al guardar la calificación.";
            }
        } else {
            $_SESSION['error_message'] = "Por favor, introduce una nota válida (0-100).";
        }
    }
    // Redirigir para limpiar la URL y evitar reenvío del formulario
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
    exit();
}


// Mensajes de sesión
if (isset($_SESSION['success_message'])) {
    $mensaje = '<div class="mensaje exito">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $mensaje = '<div class="mensaje error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}


// OBTENCIÓN DE DATOS PARA LA VISTA
$tareas = [];
$nombre_tarea = "Todas las Tareas";
$clase_id_para_volver = null;

try {
    // Escenario A: Profesor viendo las entregas de UNA tarea específica
    if (($rol_usuario === 'profesor' || $rol_usuario === 'administrador') && isset($_GET['idTarea'])) {
        $id_tarea_seleccionada = filter_input(INPUT_GET, 'idTarea', FILTER_VALIDATE_INT);
        if (!$id_tarea_seleccionada) {
            header('Location: dashboard.php?error=tarea_no_especificada');
            exit();
        }

        $stmt_info = $con->prepare("SELECT titulo, clases_idclases FROM Tarea WHERE idtarea = ?");
        $stmt_info->execute([$id_tarea_seleccionada]);
        $info_tarea = $stmt_info->fetch();
        if ($info_tarea) {
            $nombre_tarea = $info_tarea['titulo'];
            $clase_id_para_volver = $info_tarea['clases_idclases'];
        }

        $sql = "SELECT t.titulo, e.archivo, e.tarea_idtarea, e.nota, e.cuenta_iduser, i.nom, i.paterno
                FROM entrega AS e
                JOIN Tarea AS t ON e.tarea_idtarea = t.idtarea
                JOIN info AS i ON e.cuenta_iduser = i.cuenta_iduser
                WHERE e.tarea_idtarea = ?
                ORDER BY i.paterno, i.nom;";
        $stmt = $con->prepare($sql);
        $stmt->execute([$id_tarea_seleccionada]);
        $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Escenario B: Estudiante viendo SUS entregas
    elseif ($rol_usuario === 'estudiante') {
        $sql = "SELECT t.titulo, t.tema, e.archivo, e.tarea_idtarea, e.nota 
                FROM Entrega e 
                JOIN Tarea t ON e.tarea_idtarea = t.idtarea 
                WHERE e.cuenta_iduser = ?";
        $stmt = $con->prepare($sql);
        $stmt->execute([$usuario_id]);
        $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Si es profesor pero no ha seleccionado una tarea, lo mandamos al dashboard.
        header('Location: dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    die("Error al consultar las tareas: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($rol_usuario == 'profesor') ? 'Entregas de: ' . htmlspecialchars($nombre_tarea) : 'Mis Tareas Subidas'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Lora:display=swap" rel="stylesheet" />
    <style>
        body {
            font-family: 'Lora', serif;
            background-color: #570a0a;
            margin: 0;
            padding: 20px;
        }

        .contenedor-principal {
            max-width: 900px;
            margin: 40px auto;
            background-color: #f7ebdd;
            border-radius: 18px;
            padding: 36px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.18);
        }

        h1 {
            color: #a02222;
            font-family: 'Cinzel', serif;
            font-size: 2.1rem;
            text-align: center;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .subtitulo-tarea {
            text-align: center;
            font-size: 1.2rem;
            color: #570a0a;
            margin-top: 0px;
            margin-bottom: 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px 15px;
            border: 1px solid #a02222;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background-color: #570a0a;
            color: #f7ebdd;
        }

        .btn-volver {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 25px;
            background-color: #a02222;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
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
        }

        .mensaje.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .acciones-cell {
            display: flex;
            gap: 8px;
        }

        .btn-accion {
            padding: 6px 14px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            color: white;
            cursor: pointer;
            border: none;
        }

        .btn-accion.calificar {
            background-color: #28a745;
        }

        .btn-accion.calificar:hover {
            background-color: #1e7e34;
        }

        .btn-accion.modificar {
            background-color: #ffc107;
            /* Color amarillo */
            color: #212529;
            /* Color de texto oscuro para que contraste */
        }

        .btn-accion.modificar:hover {
            background-color: #e0a800;
            /* Amarillo un poco más oscuro */
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 30px;
            border-radius: 15px;
            border-top: 5px solid #a02222;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-family: 'Cinzel', serif;
            color: #a02222;
            margin: 0;
        }

        .modal-header p {
            margin: 5px 0 0 0;
            color: #666;
        }

        .modal-form .form-group {
            margin-bottom: 15px;
        }

        .modal-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .modal-form input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-family: 'Lora', serif;
            font-size: 1rem;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-buttons button {
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-guardar {
            background-color: #28a745;
            color: white;
        }

        .btn-cancelar {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>

<body>
    <div class="contenedor-principal">
        <a href="<?php echo $clase_id_para_volver ? 'clase.php?id=' . $clase_id_para_volver : 'dashboard.php'; ?>" class="btn-volver">← Volver</a>
        <h1><?php echo ($rol_usuario == 'profesor') ? 'Tareas Entregadas' : 'Mis Tareas Subidas'; ?></h1>
        <?php if ($rol_usuario == 'profesor' && isset($nombre_tarea)): ?>
            <p class="subtitulo-tarea">para la tarea: <strong><?php echo htmlspecialchars($nombre_tarea); ?></strong></p>
        <?php endif; ?>
        <?php echo $mensaje; ?>
        <table>
            <thead>
                <tr>
                    <?php if ($rol_usuario == 'profesor'): ?>
                        <th>Estudiante</th>
                        <th>Archivo</th>
                        <th>Nota</th>
                        <th>Acciones</th>
                    <?php else: // Estudiante 
                    ?>
                        <th>Título</th>
                        <th>Tema</th>
                        <th>Archivo</th>
                        <th>Nota</th>
                        <th>Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tareas)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No hay entregas para mostrar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tareas as $tarea): ?>
                        <tr>
                            <?php if ($rol_usuario == 'profesor'): ?>
                                <td><?php echo htmlspecialchars($tarea['nom'] . ' ' . $tarea['paterno']); ?></td>
                            <?php else: ?>
                                <td><?php echo htmlspecialchars($tarea['titulo']); ?></td>
                                <td><?php echo htmlspecialchars($tarea['tema']); ?></td>
                            <?php endif; ?>
                            <td><a href="<?php echo htmlspecialchars($tarea['archivo']); ?>" download>Ver Archivo</a></td>
                            <td><?php echo htmlspecialchars($tarea['nota'] ?? 'Sin calificar'); ?></td>
                            <td>
                                <div class="acciones-cell">
                                    <?php if ($rol_usuario == 'profesor'): ?>
                                        <button class="btn-accion calificar" onclick="openGradeModal(<?php echo $tarea['tarea_idtarea']; ?>, <?php echo $tarea['cuenta_iduser']; ?>, '<?php echo htmlspecialchars($tarea['nom'] . ' ' . $tarea['paterno'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tarea['nota'] ?? ''); ?>')">Calificar</button>
                                    <?php else: ?>
                                        <a href="editarEntregaEstudiante.php?idTarea=<?php echo $tarea['tarea_idtarea']; ?>" class="btn-accion modificar">Modificar</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($rol_usuario == 'profesor'): ?>
        <div id="gradeModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Calificar Tarea</h3>
                    <p id="modalStudentName"></p>
                </div>
                <form id="gradeForm" method="POST" action="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET); ?>" class="modal-form">
                    <input type="hidden" name="accion" value="calificar">
                    <input type="hidden" id="modalTareaId" name="tarea_id">
                    <input type="hidden" id="modalEstudianteId" name="estudiante_id">
                    <div class="form-group">
                        <label for="modalNota">Nota (0-100)</label>
                        <input type="number" id="modalNota" name="nota" min="0" max="100" step="0.1" required>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" id="cancelGradeBtn" class="btn-cancelar">Cancelar</button>
                        <button type="submit" class="btn-guardar">Guardar Nota</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            const gradeModal = document.getElementById('gradeModal');
            const cancelGradeBtn = document.getElementById('cancelGradeBtn');
            const modalTareaId = document.getElementById('modalTareaId');
            const modalEstudianteId = document.getElementById('modalEstudianteId');
            const modalNota = document.getElementById('modalNota');
            const modalStudentName = document.getElementById('modalStudentName');

            function openGradeModal(tareaId, estudianteId, studentName, currentNota) {
                modalTareaId.value = tareaId;
                modalEstudianteId.value = estudianteId;
                modalStudentName.textContent = `Estudiante: ${studentName}`;
                modalNota.value = currentNota;
                gradeModal.style.display = 'flex';
            }

            function closeGradeModal() {
                gradeModal.style.display = 'none';
            }
            cancelGradeBtn.addEventListener('click', closeGradeModal);
            window.addEventListener('click', (event) => {
                if (event.target === gradeModal) {
                    closeGradeModal();
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>