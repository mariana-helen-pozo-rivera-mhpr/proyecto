<?php
session_start();
require_once 'universal.php';
include 'verificar_sesion.php';

if ($_SESSION['rol'] !== 'estudiante' || !isset($_GET['idTarea'])) {
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

$tarea_id = filter_input(INPUT_GET, 'idTarea', FILTER_VALIDATE_INT);
$usuario_id = $_SESSION['usuario_id'];
$clase_id = $_SESSION['clase_id'];

if (!$tarea_id || !$clase_id) {
    header("Location: dashboard.php?error=parametros_invalidos");
    exit();
}

try {
    // Se obtienen el título y las instrucciones de la tarea
    $stmt_tarea = $con->prepare("SELECT titulo, `desc` FROM tarea WHERE idtarea = ?");
    $stmt_tarea->execute([$tarea_id]);
    $tarea = $stmt_tarea->fetch();

    if (!$tarea) {
        header("Location: claseEstudiante.php?id=$clase_id&error=tarea_no_encontrada");
        exit();
    }

    // Si ya existe una entrega, se redirige a la página de edición
    $stmt_verificar = $con->prepare("SELECT tarea_idtarea FROM Entrega WHERE tarea_idtarea = ? AND cuenta_iduser = ?");
    $stmt_verificar->execute([$tarea_id, $usuario_id]);
    if ($stmt_verificar->fetchColumn()) {
        header("Location: editarEntregaEstudiante.php?idTarea=$tarea_id");
        exit();
    }

    // Procesar la subida del archivo
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $f_entrega = date("Y-m-d H:i:s");
            $archivo_nombre = $_FILES['archivo']['name'];
            $archivo_tmp = $_FILES['archivo']['tmp_name'];

            $carpeta_destino = 'uploads/entregas/';
            if (!is_dir($carpeta_destino)) mkdir($carpeta_destino, 0777, true);

            $extension = pathinfo($archivo_nombre, PATHINFO_EXTENSION);
            $nuevo_nombre_archivo = uniqid('entrega_', true) . '.' . $extension;
            $ruta_completa = $carpeta_destino . $nuevo_nombre_archivo;

            if (move_uploaded_file($archivo_tmp, $ruta_completa)) {
                $stmt_insert = $con->prepare("INSERT INTO Entrega (tarea_idtarea, cuenta_iduser, f_entrega, archivo) VALUES (?, ?, ?, ?)");
                $stmt_insert->execute([$tarea_id, $usuario_id, $f_entrega, $ruta_completa]);

                $_SESSION['success_message'] = "✅ Tarea entregada correctamente.";
                header("Location: claseEstudiante.php?id=$clase_id");
                exit();
            } else {
                throw new Exception("Error al mover el archivo subido.");
            }
        } else {
            throw new Exception("Debes seleccionar un archivo o hubo un error en la subida.");
        }
    }
} catch (Exception $e) {
    $mensaje_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregar: <?php echo htmlspecialchars($tarea['titulo']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Lora", serif;
            background-color: #570a0a;
            margin: 0;
            padding: 20px;
        }

        .contenedor-principal {
            max-width: 800px;
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
            font-size: 1.1rem;
            margin: 0;
            line-height: 1.6;
        }

        .btn {
            padding: 12px 28px;
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

        .zona-entrega {
            background-color: #a02222;
            color: #f7ebdd;
            border-radius: 12px;
            padding: 30px;
        }

        .zona-entrega h3 {
            font-family: 'Cinzel', serif;
            text-align: center;
            margin-top: 0;
            font-size: 1.5rem;
        }

        .form-subida {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .drop-area {
            width: 100%;
            box-sizing: border-box;
            border: 2px dashed #f7ebdd;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.1);
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s;
            margin-bottom: 25px;
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

        .acciones-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            width: 100%;
            margin-top: 10px;
        }

        .btn-cancelar {
            background-color: #6c757d;
            color: white;
        }

        .btn-cancelar:hover {
            background-color: #5a6268;
        }

        .btn-entregar {
            background-color: #28a745;
            color: white;
        }

        .btn-entregar:hover {
            background-color: #1e7e34;
        }

        .mensaje.error {
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body>
    <div class="contenedor-principal">
        <a href="claseEstudiante.php?id=<?php echo $clase_id; ?>" class="btn btn-volver" style="background-color: #a02222; color: #f7ebdd; margin-bottom: 20px;">← Volver a la Clase</a>
        <div class="header">
            <h1><?php echo htmlspecialchars($tarea['titulo']); ?></h1>
            <p><?php echo nl2br(htmlspecialchars($tarea['desc'])); ?></p>
        </div>

        <?php if (isset($mensaje_error)): ?>
            <div class="mensaje error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <div class="zona-entrega">
            <h3>Sube tu Archivo</h3>
            <form class="form-subida" method="POST" enctype="multipart/form-data">
                <div class="drop-area" id="drop-area">
                    <div class="icon-nube">📤</div>
                    <p>Arrastra y suelta tu archivo aquí</p>
                    <p style="font-style: italic; opacity: 0.8; margin-top: 5px;">o</p>
                    <div style="margin-top: 10px;">
                        <button type="button" id="btn-examinar" class="btn btn-examinar">Seleccionar Archivo</button>
                    </div>
                    <input type="file" id="archivo" name="archivo" style="display:none;" required>
                    <div class="nombre-archivo" id="nombre-archivo"></div>
                </div>

                <div class="acciones-footer">
                    <a href="claseEstudiante.php?id=<?php echo $clase_id; ?>" class="btn btn-cancelar">Cancelar</a>
                    <button type="submit" class="btn btn-entregar">Entregar Tarea</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        const dropArea = document.getElementById('drop-area');
        const fileInput = document.getElementById('archivo');
        const nombreArchivo = document.getElementById('nombre-archivo');
        const btnExaminar = document.getElementById('btn-examinar');

        btnExaminar.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', mostrarNombreArchivo);

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

        function mostrarNombreArchivo() {
            if (fileInput.files.length > 0) {
                nombreArchivo.textContent = `Archivo seleccionado: ${fileInput.files[0].name}`;
            } else {
                nombreArchivo.textContent = '';
            }
        }
    </script>
</body>

</html>