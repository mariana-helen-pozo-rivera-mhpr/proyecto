<?php
session_start();
include 'universal.php';

/** @var PDO $con */

// --- (INICIO) LÓGICA PARA ELIMINAR ESTUDIANTE ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion']) && $_GET['accion'] === 'eliminar_estudiante') {
    if (isset($_SESSION['rol']) && ($_SESSION['rol'] === 'profesor' || $_SESSION['rol'] === 'administrador')) {
        $clase_id_param = filter_input(INPUT_GET, 'clase_id', FILTER_VALIDATE_INT);
        $estudiante_id_param = filter_input(INPUT_GET, 'estudiante_id', FILTER_VALIDATE_INT);
        if ($clase_id_param && $estudiante_id_param) {
            try {
                $stmt_owner = $con->prepare("SELECT cuenta_iduser FROM clases WHERE idclases = ?");
                $stmt_owner->execute([$clase_id_param]);
                $clase_owner_id = $stmt_owner->fetchColumn();
                if ($_SESSION['rol'] === 'administrador' || $clase_owner_id == $_SESSION['usuario_id']) {
                    $stmt_delete = $con->prepare("DELETE FROM cuenta_has_clases WHERE cuenta_iduser = ? AND clases_idclases = ?");
                    $stmt_delete->execute([$estudiante_id_param, $clase_id_param]);
                    header('Location: clase.php?id=' . $clase_id_param . '&success=estudiante_eliminado');
                    exit();
                } else {
                    $_SESSION['error_message'] = "No tienes permiso para realizar esta acción.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error al eliminar al estudiante: " . $e->getMessage();
            }
        }
    }
    header('Location: clase.php?id=' . ($clase_id_param ?? $_GET['id']));
    exit();
}
// --- (FIN) LÓGICA PARA ELIMINAR ESTUDIANTE ---

$_SESSION['clase_id'] = $_GET['id'];

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    header('Location: logueo.php?error=no_autenticado');
    exit();
}
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$clase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($clase_id <= 0) {
    header('Location: dashboard.php?error=clase_invalida');
    exit();
}

$verificar_acceso = "SELECT COUNT(*) as acceso FROM cuenta_has_clases WHERE cuenta_iduser = ? AND clases_idclases = ?";
$stmt = $con->prepare($verificar_acceso);
$stmt->execute([$usuario_id, $clase_id]);
$resultado = $stmt->fetch();

if ($resultado['acceso'] == 0) {
    $verificar_profesor = "SELECT COUNT(*) as es_profesor FROM clases WHERE idclases = ? AND cuenta_iduser = ?";
    $stmt = $con->prepare($verificar_profesor);
    $stmt->execute([$clase_id, $usuario_id]);
    $es_profesor = $stmt->fetch();

    if ($es_profesor['es_profesor'] == 0 && $rol !== 'administrador') {
        header('Location: dashboard.php?error=sin_acceso');
        exit();
    }
}

$query_clase = "SELECT c.*, i.nom, i.paterno, i.materno FROM clases c LEFT JOIN info i ON c.cuenta_iduser = i.cuenta_iduser WHERE c.idclases = ?";
$stmt = $con->prepare($query_clase);
$stmt->execute([$clase_id]);
$clase = $stmt->fetch();

if (!$clase) {
    header('Location: dashboard.php?error=clase_no_encontrada');
    exit();
}

$query_tareas = "SELECT t.*, COUNT(e.cuenta_iduser) as entregas FROM tarea t LEFT JOIN entrega e ON t.idtarea = e.tarea_idtarea WHERE t.clases_idclases = ? GROUP BY t.idtarea ORDER BY t.idtarea DESC";
$stmt = $con->prepare($query_tareas);
$stmt->execute([$clase_id]);
$tareas = $stmt->fetchAll();

$query_estudiantes = "SELECT i.*, c.iduser 
                        FROM cuenta_has_clases chc 
                        INNER JOIN info i ON chc.cuenta_iduser = i.cuenta_iduser 
                        INNER JOIN cuenta c ON chc.cuenta_iduser = c.iduser 
                        WHERE chc.clases_idclases = ? AND c.rol = 'estudiante' 
                        ORDER BY i.paterno, i.materno, i.nom";
$stmt = $con->prepare($query_estudiantes);
$stmt->execute([$clase_id]);
$estudiantes = $stmt->fetchAll();

$mensaje = '';
$error = '';
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'unido') $mensaje = "Te has unido a la clase exitosamente.";
    if ($_GET['success'] === 'estudiante_eliminado') $mensaje = "Estudiante eliminado de la clase correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clase['nom_clase']); ?> - UET</title>
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

        .header-clase {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #a02222;
        }

        .header-clase h1 {
            color: #a02222;
            font-family: "Cinzel", serif;
            font-size: 36px;
            margin-bottom: 10px;
        }

        .info-clase {
            color: #666;
            font-size: 18px;
            font-style: italic;
        }

        .navegacion {
            margin-bottom: 20px;
        }

        .btn-navegacion {
            background-color: #570a0a;
            color: #f7ebdd;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-right: 10px;
            transition: background-color 0.3s;
        }

        .btn-navegacion:hover {
            background-color: #a02222;
        }

        .secciones-clase {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .seccion {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 2px 4px 8px rgba(0, 0, 0, 0.1);
        }

        .seccion h3 {
            color: #a02222;
            font-family: "Cinzel", serif;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #a02222;
        }

        .tarea-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #a02222;
        }

        .tarea-titulo {
            font-weight: bold;
            color: #a02222;
            margin-bottom: 5px;
        }

        .tarea-descripcion {
            color: #666;
            margin-bottom: 10px;
        }

        .tarea-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #888;
        }

        .tarea-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .btn-tarea {
            background-color: #570a0a;
            color: #f7ebdd;
            padding: 6px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
            transition: background-color 0.3s;
            border: none;
            display: inline-block;
        }

        .btn-tarea:hover {
            background-color: #a02222;
            color: #fff;
        }

        .btn-tarea.eliminar {
            background-color: #c82333;
        }

        .btn-tarea.eliminar:hover {
            background-color: #a71d2a;
        }

        .btn-tarea.editar {
            background-color: #007bff;
        }

        .btn-tarea.editar:hover {
            background-color: #0056b3;
        }

        .btn-tarea.calificar {
            background-color: #28a745;
        }

        .btn-tarea.calificar:hover {
            background-color: #1e7e34;
        }

        .estudiante-item {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .estudiante-nombre {
            font-weight: bold;
            color: #333;
        }

        .estudiante-ci {
            color: #666;
            font-size: 14px;
        }

        .estudiante-acciones {
            display: flex;
            gap: 8px;
        }

        .btn-accion-estudiante {
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-accion-estudiante.ver {
            background-color: #007bff;
        }

        .btn-accion-estudiante.ver:hover {
            background-color: #0056b3;
        }

        .btn-accion-estudiante.eliminar {
            background-color: #dc3545;
        }

        .btn-accion-estudiante.eliminar:hover {
            background-color: #c82333;
        }

        .sin-contenido {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }

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

        #modal-texto {
            margin-bottom: 25px;
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

        #studentModalOverlay {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
        }

        #studentModal {
            background-color: #f7ebdd;
            color: #333;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        }

        #studentModal h3 {
            font-family: 'Cinzel', serif;
            color: #570a0a;
            margin-top: 0;
            border-bottom: 2px solid #a02222;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .modal-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-info-item p {
            margin: 0 0 10px 0;
        }

        .modal-info-item strong {
            color: #570a0a;
        }

        #studentModalCloseBtn {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        #studentModalCloseBtn:hover {
            color: #333;
        }

        @media (max-width: 768px) {
            .secciones-clase {
                grid-template-columns: 1fr;
            }

            .modal-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="contenedor-principal">
        <div class="header-clase">
            <h1>📚 <?php echo htmlspecialchars($clase['nom_clase']); ?></h1>
            <div class="info-clase">
                Código: <strong><?php echo htmlspecialchars($clase['codel']); ?></strong> | Profesor: <?php echo htmlspecialchars($clase['nom'] . ' ' . $clase['paterno'] . ' ' . $clase['materno']); ?>
            </div>
        </div>
        <div class="navegacion">
            <a href="dashboard.php" class="btn-navegacion">← Volver al Dashboard</a>
            <a href="publicaciones.php?clase_id=<?php echo $clase_id; ?>" class="btn-navegacion">📋 Ver Tablón</a>
            <?php if ($rol === 'profesor' || $rol === 'administrador'): ?>
                <a href="creartarea.php?clase_id=<?php echo $clase_id; ?>" class="btn-navegacion">➕ Crear Tarea</a>
            <?php endif; ?>
        </div>

        <div class="secciones-clase">
            <div class="seccion">
                <h3>📝 Tareas</h3>
                <?php if (empty($tareas)): ?>
                    <div class="sin-contenido">📭 No hay tareas en esta clase</div>
                <?php else: ?>
                    <?php foreach ($tareas as $tarea): ?>
                        <div class="tarea-item">
                            <div class="tarea-titulo"><?php echo htmlspecialchars($tarea['titulo']); ?></div>
                            <div class="tarea-descripcion"><?php echo htmlspecialchars($tarea['desc']); ?></div>
                            <div class="tarea-info">
                                <span>Tema: <?php echo htmlspecialchars($tarea['tema']); ?></span>
                                <span>Entregas: <?php echo $tarea['entregas']; ?></span>
                            </div>
                            <div class="tarea-actions">
                                <?php if ($rol === 'profesor' || $rol === 'administrador'): ?>
                                    <a href="vertareasentregadas.php?idTarea=<?php echo $tarea['idtarea']; ?>" class="btn-tarea calificar">Calificar</a>
                                    <a href="formularioeditartarea.php?id_tarea=<?php echo $tarea['idtarea'] ?>" class="btn-tarea editar">Editar</a>
                                    <a href="eliminartarea.php?id_tarea=<?php echo $tarea['idtarea'] ?>" class="btn-tarea eliminar">Eliminar</a>
                                <?php else: // Rol Estudiante 
                                ?>
                                    <a href="entregartarea.php?id_tarea=<?php echo $tarea['idtarea'] ?>" class="btn-tarea">Entregar Tarea</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="seccion">
                <h3>👥 Estudiantes (<?php echo count($estudiantes); ?>)</h3>
                <?php if (empty($estudiantes)): ?>
                    <div class="sin-contenido">📭 No hay estudiantes registrados</div>
                <?php else: ?>
                    <?php foreach ($estudiantes as $estudiante): ?>
                        <div class="estudiante-item">
                            <div>
                                <div class="estudiante-nombre"><?php echo htmlspecialchars($estudiante['nom'] . ' ' . $estudiante['paterno'] . ' ' . $estudiante['materno']); ?></div>
                                <div class="estudiante-ci">CI: <?php echo htmlspecialchars($estudiante['ci']); ?></div>
                            </div>
                            <?php if ($rol === 'profesor' || $rol === 'administrador'): ?>
                                <div class="estudiante-acciones">
                                    <a href="#" class="btn-accion-estudiante ver" onclick="openStudentModal(<?php echo $estudiante['iduser']; ?>); return false;">Ver</a>
                                    <a href="clase.php?accion=eliminar_estudiante&clase_id=<?php echo $clase_id; ?>&estudiante_id=<?php echo $estudiante['iduser']; ?>" class="btn-accion-estudiante eliminar" onclick="openConfirmModal(this.href, '¿Estás seguro de que quieres eliminar a este estudiante de la clase?'); return false;">Eliminar</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="modalOverlay">
        <div id="confirmModal">
            <h3>Confirmar Acción</h3>
            <p id="modal-texto">¿Estás seguro?</p>
            <div class="modal-acciones">
                <button id="cancelBtn">Cancelar</button>
                <button id="confirmBtn">Aceptar</button>
            </div>
        </div>
    </div>

    <div id="studentModalOverlay">
        <div id="studentModal">
            <span id="studentModalCloseBtn">&times;</span>
            <h3>Detalles del Estudiante</h3>
            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <p><strong>Nombre:</strong> <span id="modalStudentNombre"></span></p>
                </div>
                <div class="modal-info-item">
                    <p><strong>CI:</strong> <span id="modalStudentCi"></span></p>
                </div>
                <div class="modal-info-item">
                    <p><strong>Teléfono:</strong> <span id="modalStudentTelefono"></span></p>
                </div>
                <div class="modal-info-item">
                    <p><strong>Fecha de Nac.:</strong> <span id="modalStudentFechaN"></span></p>
                </div>
                <div class="modal-info-item">
                    <p><strong>RUDE:</strong> <span id="modalStudentRude"></span></p>
                </div>
                <div class="modal-info-item">
                    <p><strong>Curso:</strong> <span id="modalStudentCurso"></span></p>
                </div>
                <div class="modal-info-item">
                    <p><strong>Dirección:</strong> <span id="modalStudentDirec"></span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const estudiantesData = <?php echo json_encode($estudiantes, JSON_UNESCAPED_UNICODE); ?>;

        const modalOverlay = document.getElementById('modalOverlay');
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const modalTexto = document.getElementById('modal-texto');
        let deleteUrl = '';

        function openConfirmModal(url, message) {
            deleteUrl = url;
            modalTexto.textContent = message;
            modalOverlay.style.display = 'flex';
        }

        function closeConfirmModal() {
            modalOverlay.style.display = 'none';
        }
        confirmBtn.addEventListener('click', function() {
            window.location.href = deleteUrl;
        });
        cancelBtn.addEventListener('click', closeConfirmModal);

        const studentModalOverlay = document.getElementById('studentModalOverlay');
        const studentModalCloseBtn = document.getElementById('studentModalCloseBtn');

        function openStudentModal(studentId) {
            const student = estudiantesData.find(s => s.iduser == studentId);

            if (student) {
                document.getElementById('modalStudentNombre').textContent = `${student.nom || ''} ${student.paterno || ''} ${student.materno || ''}`;
                document.getElementById('modalStudentCi').textContent = student.ci || 'No disponible';
                document.getElementById('modalStudentTelefono').textContent = student.telefono || 'No disponible';
                document.getElementById('modalStudentFechaN').textContent = student.fecha_n || 'No disponible';
                document.getElementById('modalStudentRude').textContent = student.rude || 'No disponible';
                document.getElementById('modalStudentCurso').textContent = student.curso || 'No disponible';
                document.getElementById('modalStudentDirec').textContent = student.direc || 'No disponible';

                studentModalOverlay.style.display = 'flex';
            } else {
                alert('No se pudo encontrar la información del estudiante.');
            }
        }

        function closeStudentModal() {
            studentModalOverlay.style.display = 'none';
        }

        studentModalCloseBtn.addEventListener('click', closeStudentModal);
        studentModalOverlay.addEventListener('click', function(event) {
            if (event.target === studentModalOverlay) {
                closeStudentModal();
            }
        });
    </script>
</body>

</html>