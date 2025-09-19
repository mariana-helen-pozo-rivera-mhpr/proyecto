<?php
session_start();
include 'universal.php';
include 'verificar_sesion.php';

/** @var PDO $con */

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

// ==============================================================================
//Ahora revisa ambos posibles nombres para el ID de la clase
// ==============================================================================
if (isset($_GET['clases_idclases'])) {
    $clase_id = (int)$_GET['clases_idclases'];
} elseif (isset($_GET['clase_id'])) {
    $clase_id = (int)$_GET['clase_id'];
} else {
    $clase_id = 0;
}

if ($clase_id <= 0) {
    die("Error: ID de clase no válido.");
}


// Eliminar publicación
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar_pub' && isset($_GET['pub_id'])) {
    $pub_id = (int)$_GET['pub_id'];
    // Solo profesor o admin pueden eliminar
    if ($rol === 'profesor' || $rol === 'administrador') {
        // Obtener archivo para eliminarlo físicamente si existe
        $stmt = $con->prepare("SELECT archivo FROM publicaciones WHERE idpublicaciones = ? AND clases_idclases = ?");
        $stmt->execute([$pub_id, $clase_id]);
        $pub = $stmt->fetch();
        if ($pub) {
            if (!empty($pub['archivo']) && file_exists($pub['archivo'])) {
                unlink($pub['archivo']);
            }
            $stmt_del = $con->prepare("DELETE FROM publicaciones WHERE idpublicaciones = ? AND clases_idclases = ?");
            if ($stmt_del->execute([$pub_id, $clase_id])) {
                $_SESSION['success_message'] = "Publicación eliminada correctamente.";
            } else {
                $_SESSION['error_message'] = "Error al eliminar la publicación.";
            }
        } else {
            $_SESSION['error_message'] = "Publicación no encontrada.";
        }
    } else {
        $_SESSION['error_message'] = "No tienes permisos para eliminar publicaciones.";
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?clases_idclases=' . $clase_id);
    exit();
}

// Crear publicación (con o sin archivo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_publicacion_archivo') {
    $contenido = trim($_POST['contenido'] ?? '');
    $archivo_path = null;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $carpeta_destino = 'uploads/publicaciones/';
        if (!is_dir($carpeta_destino)) mkdir($carpeta_destino, 0777, true);
        $archivo_nombre_original = basename($_FILES['archivo']['name']);
        $extension = pathinfo($archivo_nombre_original, PATHINFO_EXTENSION);
        $nombre_unico = uniqid('pub_' . $clase_id . '_', true) . '.' . $extension;
        $archivo_path = $carpeta_destino . $nombre_unico;
        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $archivo_path)) {
            $_SESSION['error_message'] = "Error al mover el archivo subido.";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?clases_idclases=' . $clase_id);
            exit();
        }
    }
    $query = "INSERT INTO publicaciones (contenido, archivo, f_crea, autor_id, clases_idclases) VALUES (?, ?, NOW(), ?, ?)";
    $stmt = $con->prepare($query);
    if ($stmt->execute([$contenido, $archivo_path, $usuario_id, $clase_id])) {
        $_SESSION['success_message'] = "Publicación creada correctamente.";
    } else {
        $_SESSION['error_message'] = "Error al guardar la publicación en la base de datos.";
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?clases_idclases=' . $clase_id);
    exit();
}


$mensaje = '';
if (isset($_SESSION['success_message'])) {
    $mensaje = '<div class="mensaje exito">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $mensaje = '<div class="mensaje error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}

$query_publicaciones = "SELECT p.*, i.nom, i.paterno FROM publicaciones p LEFT JOIN info i ON p.autor_id = i.cuenta_iduser WHERE p.clases_idclases = ? AND p.activo = 1 ORDER BY p.f_crea DESC";
$stmt_pub = $con->prepare($query_publicaciones);
$stmt_pub->execute([$clase_id]);
$publicaciones = $stmt_pub->fetchAll();

// Lógica para editar publicación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_publicacion') {
    $pub_id = (int)($_POST['pub_id'] ?? 0);
    $nuevo_contenido = trim($_POST['contenido'] ?? '');
    $archivo_path = null;
    // Verificar autoría
    $stmt = $con->prepare("SELECT * FROM publicaciones WHERE idpublicaciones = ? AND clases_idclases = ?");
    $stmt->execute([$pub_id, $clase_id]);
    $pub = $stmt->fetch();
    if ($pub && $pub['autor_id'] == $usuario_id) {
        // Si sube nuevo archivo, reemplazar
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $carpeta_destino = 'uploads/publicaciones/';
            if (!is_dir($carpeta_destino)) mkdir($carpeta_destino, 0777, true);
            $archivo_nombre_original = basename($_FILES['archivo']['name']);
            $extension = pathinfo($archivo_nombre_original, PATHINFO_EXTENSION);
            $nombre_unico = uniqid('pub_' . $clase_id . '_', true) . '.' . $extension;
            $archivo_path = $carpeta_destino . $nombre_unico;
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $archivo_path)) {
                // Eliminar archivo anterior si existe
                if (!empty($pub['archivo']) && file_exists($pub['archivo'])) {
                    unlink($pub['archivo']);
                }
            } else {
                $_SESSION['error_message'] = "Error al subir el nuevo archivo.";
                header('Location: ' . $_SERVER['PHP_SELF'] . '?clases_idclases=' . $clase_id);
                exit();
            }
        } else {
            $archivo_path = $pub['archivo'];
        }
        $stmt_upd = $con->prepare("UPDATE publicaciones SET contenido = ?, archivo = ?, f_edit = NOW() WHERE idpublicaciones = ?");
        if ($stmt_upd->execute([$nuevo_contenido, $archivo_path, $pub_id])) {
            $_SESSION['success_message'] = "Publicación editada correctamente.";
        } else {
            $_SESSION['error_message'] = "Error al editar la publicación.";
        }
    } else {
        $_SESSION['error_message'] = "No tienes permisos para editar esta publicación.";
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?clases_idclases=' . $clase_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablón de Archivos</title>
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
            margin: 0 auto;
            background-color: #f7ebdd;
            border-radius: 15px;
            padding: 30px;
        }

        .header-tablon {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #a02222;
        }

        h1 {
            color: #a02222;
            font-family: "Cinzel", serif;
        }

        .navegacion {
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-right: 10px;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-navegacion {
            background-color: #570a0a;
            color: #f7ebdd;
        }

        .btn-navegacion:hover {
            background-color: #a02222;
        }

        .formulario-publicacion {
            background-color: #a02222;
            color: #f7ebdd;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .formulario-publicacion h3 {
            font-family: "Cinzel", serif;
            margin-top: 0;
        }

        textarea {
            width: 100%;
            min-height: 60px;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: "Lora", serif;
            font-size: 1rem;
            resize: vertical;
            box-sizing: border-box;
        }

        .zona-archivo {
            background-color: #f7ebdd;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
        }

        .drop-area {
            width: 100%;
            box-sizing: border-box;
            border: 2px dashed #570a0a;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            color: #333;
        }

        .nombre-archivo {
            font-weight: bold;
            margin-top: 15px;
        }

        .btn-examinar {
            background-color: #570a0a;
            color: #f7ebdd;
            font-size: 0.9rem;
        }

        .acciones-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-cancelar {
            background-color: #6c757d;
            color: white;
        }

        .btn-publicar {
            background-color: #28a745;
            color: white;
        }

        .publicacion {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 5px solid #a02222;
        }

        .publicacion-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .autor-nombre {
            font-weight: bold;
            color: #a02222;
        }

        .fecha-publicacion {
            font-size: 0.8rem;
            color: #666;
        }

        .contenido-publicacion {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .archivo-adjunto {
            margin-top: 15px;
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 8px;
        }

        .archivo-adjunto a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }

        .btn-eliminar-pub {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            font-size: 0.8rem;
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
    </style>
</head>

<body>
    <div class="contenedor-principal">
        <div class="header-tablon">
            <h1>📋 Tablón de Archivos</h1>
        </div>
        <div class="navegacion">
            <a href="dashboard.php" class="btn btn-navegacion">← Volver al Dashboard</a>
            <a href="clase.php?id=<?php echo $clase_id; ?>" class="btn btn-navegacion">Ver Clase</a>
        </div>

        <?php echo $mensaje; ?>

        <div class="formulario-publicacion">
            <h3>Compartir un Archivo</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear_publicacion_archivo">
                <textarea name="contenido" placeholder="Añade una descripción (opcional)..."></textarea>
                <div class="zona-archivo">
                    <div class="drop-area" id="drop-area">
                        <p>Arrastra y suelta tu archivo aquí</p>
                        <p style="font-style: italic; opacity: 0.8;">o</p>
                        <button type="button" id="btn-examinar" class="btn btn-examinar">Seleccionar Archivo</button>
                        <input type="file" name="archivo" id="input-archivo" style="display: none;">
                        <div class="nombre-archivo" id="nombre-archivo"></div>
                    </div>
                </div>
                <div class="acciones-footer">
                    <button type="reset" class="btn btn-cancelar" id="btn-cancelar-form">Cancelar</button>
                    <button type="submit" class="btn btn-publicar">Publicar Archivo</button>
                </div>
            </form>
        </div>

        <div class="publicaciones-container">
            <?php foreach ($publicaciones as $publicacion): ?>
                <div class="publicacion">
                    <div class="publicacion-header">
                        <div>
                            <div class="autor-nombre">👤 <?php echo htmlspecialchars($publicacion['nom'] . ' ' . $publicacion['paterno']); ?></div>
                            <div class="fecha-publicacion">📅 Publicado: <?php echo date('d/m/Y H:i', strtotime($publicacion['f_crea'])); ?></div>
                            <?php if (!empty($publicacion['f_edit'])): ?>
                                <div class="fecha-publicacion">✏️ Editado: <?php echo date('d/m/Y H:i', strtotime($publicacion['f_edit'])); ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <?php if ($publicacion['autor_id'] == $usuario_id): ?>
                                <button class="btn btn-eliminar-pub" style="background:#ffc107;color:#212529;" onclick="openEditModal(<?php echo $publicacion['idpublicaciones']; ?>, '<?php echo htmlspecialchars(addslashes($publicacion['contenido'])); ?>', '<?php echo htmlspecialchars(addslashes($publicacion['archivo'])); ?>'); return false;">Editar</button>
                            <?php endif; ?>
                            <?php if ($rol === 'profesor' || $rol === 'administrador'): ?>
                                <a href="?clases_idclases=<?php echo $clase_id; ?>&accion=eliminar_pub&pub_id=<?php echo $publicacion['idpublicaciones']; ?>" class="btn btn-eliminar-pub" onclick="return confirm('¿Estás seguro de que quieres eliminar esta publicación?');">
                                    Eliminar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Modal de edicion-->
                    <div id="editModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); justify-content:center; align-items:center;">
                        <div class="modal-content" style="background:#a02222; color:#f7ebdd; padding:32px 28px 24px 28px; border-radius:20px; max-width:480px; width:95%; position:relative; box-shadow:0 8px 24px rgba(0,0,0,0.18); font-family:'Lora',serif;">
                            <span id="closeEditModal" style="position:absolute; top:14px; right:22px; font-size:28px; cursor:pointer; color:#fff;">&times;</span>
                            <h3 style="margin-top:0; color:#f7ebdd; font-family:'Cinzel',serif; font-size:1.5rem; margin-bottom:18px;">Editar Publicación</h3>
                            <form id="editPubForm" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="accion" value="editar_publicacion">
                                <input type="hidden" name="pub_id" id="editPubId">
                                <div style="margin-bottom:18px;">
                                    <label for="editContenido" style="font-weight:bold; color:#fff;">Contenido</label>
                                    <textarea name="contenido" id="editContenido" style="width:100%; min-height:60px; border-radius:10px; padding:12px; border:2px solid #ddd; font-family:'Lora',serif; font-size:1rem; margin-top:6px; color:#212529;"></textarea>
                                </div>
                                <div style="margin-bottom:18px; display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                                    <label for="editArchivo" id="editArchivoLabel" style="
                                        display:inline-block;
                                        background:#f7ebdd;
                                        color:#a02222;
                                        font-weight:bold;
                                        padding:7px 18px;
                                        border-radius:7px;
                                        text-decoration:none;
                                        box-shadow:0 2px 8px rgba(0,0,0,0.07);
                                        border:1.5px solid #a02222;
                                        transition:background 0.2s, color 0.2s;
                                        cursor:pointer;
                                        margin-top:0;
                                    "
                                        onmouseover="this.style.background='#a02222';this.style.color='#f7ebdd';" onmouseout="this.style.background='#f7ebdd';this.style.color='#a02222';">📁 Elegir archivo</label>
                                    <input type="file" name="archivo" id="editArchivo" style="display:none;">
                                    <span id="editArchivoNombre" style="margin-left:10px; color:#f7ebdd;"></span>
                                    <label for="editArchivo" style="font-weight:bold; color:#fff; margin-bottom:0; margin-left:10px;">Archivo (opcional)</label>
                                </div>
                                <div id="editArchivoActual" style="margin-top:7px;"></div>
                                <div style="display:flex; gap:14px; justify-content:flex-end; margin-top:22px;">
                                    <button type="button" id="cancelEditBtn" style="background:#6c757d; color:white; border:none; border-radius:7px; padding:11px 28px; font-size:1rem; font-family:'Lora',serif;">Cancelar</button>
                                    <button type="submit" style="background:#28a745; color:white; border:none; border-radius:7px; padding:11px 28px; font-size:1rem; font-family:'Lora',serif; font-weight:bold;">Guardar Cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <script>
                        function openEditModal(pubId, contenido, archivo) {
                            document.getElementById('editModal').style.display = 'flex';
                            document.getElementById('editPubId').value = pubId;
                            document.getElementById('editContenido').value = contenido.replace(/\\n/g, '\n');
                            let archivoHtml = '';
                            if (archivo && archivo !== 'null') {
                                archivoHtml = `<a href="${archivo}" download style="
                                    display:inline-block;
                                    background:#f7ebdd;
                                    color:#a02222;
                                    font-weight:bold;
                                    padding:7px 18px;
                                    border-radius:7px;
                                    margin-top:6px;
                                    text-decoration:none;
                                    box-shadow:0 2px 8px rgba(0,0,0,0.07);
                                    border:1.5px solid #a02222;
                                    transition:background 0.2s, color 0.2s;"
                                    onmouseover="this.style.background='#a02222';this.style.color='#f7ebdd';" onmouseout="this.style.background='#f7ebdd';this.style.color='#a02222';"
                                >📎 Archivo actual</a>`;
                            }
                            document.getElementById('editArchivoActual').innerHTML = archivoHtml;
                            // Reset nombre archivo
                            document.getElementById('editArchivoNombre').textContent = '';
                            // Mostrar nombre del archivo seleccionado en el modal
                            document.getElementById('editArchivo').addEventListener('change', function() {
                                const nombre = this.files.length > 0 ? this.files[0].name : '';
                                document.getElementById('editArchivoNombre').textContent = nombre;
                            });
                        }
                        document.getElementById('closeEditModal').onclick = function() {
                            document.getElementById('editModal').style.display = 'none';
                        };
                        document.getElementById('cancelEditBtn').onclick = function(e) {
                            e.preventDefault();
                            document.getElementById('editModal').style.display = 'none';
                        };
                        // Cerrar modal al hacer click fuera
                        window.onclick = function(event) {
                            if (event.target === document.getElementById('editModal')) {
                                document.getElementById('editModal').style.display = 'none';
                            }
                        };
                    </script>
                    <?php if ($publicacion['archivo']): ?>
                        <div class="archivo-adjunto">
                            📎 <a href="<?php echo htmlspecialchars($publicacion['archivo']); ?>" download>
                                Descargar Archivo Adjunto
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($publicacion['contenido'])): ?>
                        <div class="contenido-publicacion">
                            <?php echo nl2br(htmlspecialchars($publicacion['contenido'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        const btnExaminar = document.getElementById('btn-examinar');
        const inputArchivo = document.getElementById('input-archivo');
        const nombreArchivo = document.getElementById('nombre-archivo');
        const btnCancelar = document.getElementById('btn-cancelar-form');

        btnExaminar.addEventListener('click', () => inputArchivo.click());
        inputArchivo.addEventListener('change', () => {
            if (inputArchivo.files.length > 0) {
                nombreArchivo.textContent = `Archivo: ${inputArchivo.files[0].name}`;
            }
        });

        btnCancelar.addEventListener('click', () => {
            inputArchivo.value = '';
            nombreArchivo.textContent = '';
            document.querySelector('textarea[name="contenido"]').value = '';
        });
    </script>
</body>

</html>