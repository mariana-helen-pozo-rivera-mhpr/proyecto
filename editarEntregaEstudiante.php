<?php
session_start();
require_once 'universal.php';
include 'verificar_sesion.php';

/** @var PDO $con */

if (!isset($_GET['idTarea'])) {
    header("Location: dashboard.php");
    exit();
}

$tarea_id = filter_input(INPUT_GET, 'idTarea', FILTER_VALIDATE_INT);
$usuario_id = $_SESSION['usuario_id'];
$clase_id = $_SESSION['clase_id'];

if (!$tarea_id || !$clase_id) {
    header("Location: dashboard.php?error=datos_insuficientes");
    exit();
}

$mensaje = '';

if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar') {
    try {
        $stmt_find = $con->prepare("SELECT archivo FROM Entrega WHERE tarea_idtarea = ? AND cuenta_iduser = ?");
        $stmt_find->execute([$tarea_id, $usuario_id]);
        $entrega_a_eliminar = $stmt_find->fetch();

        if ($entrega_a_eliminar) {
            if (file_exists($entrega_a_eliminar['archivo'])) {
                unlink($entrega_a_eliminar['archivo']);
            }
            $stmt_delete = $con->prepare("DELETE FROM Entrega WHERE tarea_idtarea = ? AND cuenta_iduser = ?");
            $stmt_delete->execute([$tarea_id, $usuario_id]);

            $_SESSION['success_message'] = "🗑️ Entrega eliminada permanentemente.";
            header("Location: claseEstudiante.php?id=$clase_id");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "❌ Error al eliminar la entrega.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?idTarea=$tarea_id");
        exit();
    }
}

try {
    $query = "SELECT e.*, t.titulo FROM Entrega e JOIN Tarea t ON e.tarea_idtarea = t.idtarea WHERE e.tarea_idtarea = ? AND e.cuenta_iduser = ?";
    $stmt = $con->prepare($query);
    $stmt->execute([$tarea_id, $usuario_id]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entrega) {
        header("Location: claseEstudiante.php?id=$clase_id&error=no_entrega");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $f_entrega = date("Y-m-d H:i:s");
        $archivo_nuevo = false;
        $archivo_destino = $entrega['archivo'];

        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivo_nuevo = true;
            $carpeta = 'uploads/entregas/';
            if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

            $nombre_original = basename($_FILES['archivo']['name']);
            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
            $nombre_unico = uniqid('entrega_editada_', true) . '.' . $extension;
            $archivo_destino = $carpeta . $nombre_unico;

            if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $archivo_destino)) {
                throw new Exception("Error al subir el nuevo archivo.");
            }
        }

        $stmt_update = $con->prepare("UPDATE Entrega SET f_entrega=?, archivo=? WHERE tarea_idtarea=? AND cuenta_iduser=?");
        $stmt_update->execute([$f_entrega, $archivo_destino, $tarea_id, $usuario_id]);

        if ($archivo_nuevo && $entrega['archivo'] && file_exists($entrega['archivo'])) {
            unlink($entrega['archivo']);
        }

        $_SESSION['success_message'] = "✅ Entrega modificada correctamente.";
        header("Location: claseEstudiante.php?id=$clase_id");
        exit();
    }
} catch (Exception $e) {
    $mensaje = "<div class='mensaje error'>❌ " . $e->getMessage() . "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Entrega: <?php echo htmlspecialchars($entrega['titulo']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Lora", serif;
            background-color: #570a0a;
            margin: 0;
            padding: 20px;
        }

        .contenedor-principal {
            max-width: 700px;
            margin: 40px auto;
            background-color: #f7ebdd;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-family: "Cinzel", serif;
            color: #a02222;
            font-size: 2.5rem;
            margin: 0 0 10px 0;
        }

        .header p {
            color: #570a0a;
            font-size: 1.2rem;
            margin: 0;
        }

        .btn {
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: bold;
            font-family: 'Lora', serif;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-volver {
            background-color: #a02222;
            color: #f7ebdd;
            margin-bottom: 20px;
        }

        .btn-volver:hover {
            background-color: #570a0a;
        }

        .zona-modificar {
            background-color: #a02222;
            color: #f7ebdd;
            border-radius: 12px;
            padding: 30px;
        }

        .archivo-actual {
            text-align: center;
            margin-bottom: 20px;
        }

        .archivo-actual a {
            color: #f7ebdd;
            font-weight: bold;
        }

        .drop-area {
            position: relative;
            width: 100%;
            box-sizing: border-box;
            border: 2px dashed #f7ebdd;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.1);
            padding: 30px 20px;
            text-align: center;
            margin-bottom: 25px;
            transition: all 0.3s;
        }

        .drop-area.dragover {
            background: rgba(0, 0, 0, 0.2);
        }

        .icon-nube {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .nombre-archivo {
            font-weight: bold;
            margin-top: 15px;
        }

        .btn-examinar {
            background-color: #f7ebdd;
            color: #570a0a;
        }

        .btn-examinar:hover {
            background-color: #e9d9c7;
        }

        #btn-limpiar {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ffc107;
            color: #333;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 20px;
            line-height: 30px;
            cursor: pointer;
            display: none;
        }

        .form-botones {
            display: flex;
            gap: 15px;
            width: 100%;
        }

        .btn-form {
            flex: 1;
        }

        .btn-cancelar {
            background-color: #6c757d;
            color: white;
        }

        .btn-guardar {
            background-color: #28a745;
            color: white;
        }

        .btn-eliminar {
            margin-top: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
            width: 60%;
            max-width: 350px;
            box-sizing: border-box;
            background-color: #dc3545;
            color: white;
            text-align: center;
        }

        .mensaje {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
            color: #721c24;
            background-color: #f8d7da;
        }

        #modalOverlay {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
        }

        #confirmModal {
            background-color: #1c1c1c;
            color: #f7ebdd;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        #confirmModal h3 {
            font-family: 'Cinzel', serif;
            color: #dc3545;
        }

        .modal-acciones {
            margin-top: 25px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .modal-acciones button {
            padding: 10px 25px;
            border-radius: 8px;
            border: none;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        #confirmBtn {
            background-color: #dc3545;
            color: white;
        }

        #cancelBtn {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>

<body>
    <div class="contenedor-principal">
        <a href="javascript:history.back()" class="btn btn-volver">← Volver a la Clase</a>
        <div class="header">
            <h1>Modificar Entrega</h1>
            <p><?php echo htmlspecialchars($entrega['titulo']); ?></p>
        </div>

        <?php if ($mensaje) echo $mensaje; ?>

        <div class="zona-modificar">
            <form method="POST" enctype="multipart/form-data">
                <div class="archivo-actual">
                    Archivo actual:
                    <a href="<?php echo htmlspecialchars($entrega['archivo']); ?>" download>Ver archivo entregado</a>
                </div>
                <div class="drop-area" id="drop-area">
                    <button type="button" id="btn-limpiar" title="Limpiar selección">&times;</button>
                    <div class="icon-nube">📤</div>
                    <p>Arrastra un nuevo archivo para reemplazar</p>
                    <p style="font-style: italic; opacity: 0.8; margin-top: 5px;">o</p>
                    <div style="margin-top: 10px;">
                        <button type="button" id="btn-examinar" class="btn btn-examinar">Examinar</button>
                    </div>
                    <input type="file" id="archivo" name="archivo" style="display:none;">
                    <div class="nombre-archivo" id="nombre-archivo"></div>
                </div>
                <div class="form-botones">
                    <a href="javascript:history.back()" class="btn btn-form btn-cancelar">Cancelar</a>
                    <button type="submit" class="btn btn-form btn-guardar">Guardar Cambios</button>
                </div>
            </form>
            <div style="text-align:center;">
                <a href="?idTarea=<?php echo $tarea_id; ?>&accion=eliminar"
                    class="btn btn-form btn-eliminar"
                    onclick="openConfirmModal(this.href, '¿Estás seguro de eliminar esta entrega? Esta acción es permanente.'); return false;">
                    Eliminar Entrega
                </a>
            </div>
        </div>
    </div>

    <div id="modalOverlay">
        <div id="confirmModal">
            <h3>Confirmar Eliminación</h3>
            <p id="modal-texto"></p>
            <div class="modal-acciones">
                <button id="cancelBtn">Cancelar</button>
                <button id="confirmBtn">Aceptar</button>
            </div>
        </div>
    </div>

    <script>
        const dropArea = document.getElementById('drop-area');
        const fileInput = document.getElementById('archivo');
        const nombreArchivo = document.getElementById('nombre-archivo');
        const btnExaminar = document.getElementById('btn-examinar');
        const btnLimpiar = document.getElementById('btn-limpiar');
        btnExaminar.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', mostrarNombreArchivo);
        btnLimpiar.addEventListener('click', function() {
            fileInput.value = '';
            mostrarNombreArchivo();
        });

        function mostrarNombreArchivo() {
            if (fileInput.files.length > 0) {
                nombreArchivo.textContent = `Nuevo: ${fileInput.files[0].name}`;
                btnLimpiar.style.display = 'block';
            } else {
                nombreArchivo.textContent = '';
                btnLimpiar.style.display = 'none';
            }
        }
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.add('dragover'), false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.remove('dragover'), false);
        });
        dropArea.addEventListener('drop', function(e) {
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                mostrarNombreArchivo();
            }
        });

        const modalOverlay = document.getElementById('modalOverlay');
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const modalTexto = document.getElementById('modal-texto');
        let actionUrl = '';

        function openConfirmModal(url, message) {
            actionUrl = url;
            modalTexto.textContent = message;
            modalOverlay.style.display = 'flex';
        }

        function closeConfirmModal() {
            modalOverlay.style.display = 'none';
        }
        confirmBtn.addEventListener('click', () => {
            window.location.href = actionUrl;
        });
        cancelBtn.addEventListener('click', closeConfirmModal);
        modalOverlay.addEventListener('click', (event) => {
            if (event.target === modalOverlay) closeConfirmModal();
        });
    </script>
</body>

</html>