<?php
session_start();
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    header('Location: logueo.php?error=no_autenticado');
    exit();
}

require_once 'universal.php';

if (!isset($con) || !$con) {
    die("Error Crítico: La conexión a la base de datos no se pudo establecer. Verifique el archivo 'universal.php'.");
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nom = trim($_POST['nom'] ?? '');
        $paterno = trim($_POST['paterno'] ?? '');
        $materno = trim($_POST['materno'] ?? '');
        $fecha_n = !empty($_POST['fecha_n']) ? $_POST['fecha_n'] : null;
        $direc = trim($_POST['dir'] ?? '');
        $rude = trim($_POST['rude'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $curso = ($rol === 'profesor') ? trim($_POST['curso'] ?? '') : null;

        if (empty($nom) || empty($paterno)) {
            throw new Exception("Nombre y Apellido Paterno son obligatorios.");
        }

        $stmt_check = $con->prepare("SELECT COUNT(*) FROM info WHERE cuenta_iduser = ?");
        $stmt_check->execute([$usuario_id]);
        $info_exists = $stmt_check->fetchColumn();

        if ($info_exists) {
            $sql = "UPDATE info SET nom = ?, paterno = ?, materno = ?, fecha_n = ?, direc = ?, rude = ?, telefono = ?, curso = ? WHERE cuenta_iduser = ?";
            $params = [$nom, $paterno, $materno, $fecha_n, $direc, $rude, $telefono, $curso, $usuario_id];
        } else {
            $sql = "INSERT INTO info (nom, paterno, materno, fecha_n, direc, rude, telefono, curso, cuenta_iduser) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$nom, $paterno, $materno, $fecha_n, $direc, $rude, $telefono, $curso, $usuario_id];
        }

        $stmt = $con->prepare($sql);
        if ($stmt->execute($params)) {
            $_SESSION['nombre'] = $nom;
            $mensaje = $info_exists ? "Perfil actualizado correctamente." : "Perfil creado correctamente.";
        } else {
            throw new Exception("No se pudo ejecutar la consulta.");
        }

    } catch (PDOException $e) {
        $error = "Error de Base de Datos: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$info_usuario = [];
try {
    $stmt_info = $con->prepare("SELECT * FROM info WHERE cuenta_iduser = ?");
    $stmt_info->execute([$usuario_id]);
    $info_usuario = $stmt_info->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $error = "Error al cargar la información del perfil: " . $e->getMessage();
}

$nombre_display = htmlspecialchars($info_usuario['nom'] ?? $_SESSION['nombre'] ?? 'Usuario');
$paterno_display = htmlspecialchars($info_usuario['paterno'] ?? '');
$materno_display = htmlspecialchars($info_usuario['materno'] ?? '');
$fecha_n_display = htmlspecialchars($info_usuario['fecha_n'] ?? '');
$dir_display = htmlspecialchars($info_usuario['direc'] ?? '');
$rude_display = htmlspecialchars($info_usuario['rude'] ?? '');
$telefono_display = htmlspecialchars($info_usuario['telefono'] ?? '');
$curso_display = htmlspecialchars($info_usuario['curso'] ?? '');

$nombre_completo = trim("$nombre_display $paterno_display $materno_display");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Usuario</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/perfil.css">
</head>
<body>
    <div class="perfil-container">
        <div class="perfil-titulo">Perfil de Usuario</div>

        <?php if ($mensaje): ?><div class="mensaje exito"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mensaje error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="perfil-dato"><span class="perfil-label">Nombre:</span> <?php echo $nombre_completo; ?></div>
        <div class="perfil-dato"><span class="perfil-label">Rol:</span> <?php echo htmlspecialchars($rol); ?></div>
        <?php if($fecha_n_display): ?><div class="perfil-dato"><span class="perfil-label">Fecha de Nac.:</span> <?php echo $fecha_n_display; ?></div><?php endif; ?>
        <div class="perfil-dato"><span class="perfil-label">Dirección:</span> <?php echo $dir_display; ?></div>
        <?php if($rude_display): ?><div class="perfil-dato"><span class="perfil-label">RUDE:</span> <?php echo $rude_display; ?></div><?php endif; ?>
        <?php if($telefono_display): ?><div class="perfil-dato"><span class="perfil-label">Teléfono:</span> <?php echo $telefono_display; ?></div><?php endif; ?>
        <?php if($rol === 'profesor' && $curso_display): ?><div class="perfil-dato"><span class="perfil-label">Curso:</span> <?php echo $curso_display; ?></div><?php endif; ?>

        <button class="btn-editar" id="editBtn">Editar</button>
        <a href="dashboard.php" class="btn-volver">Volver Atrás</a>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-button" id="closeBtn">&times;</span>
            <h2 style="color: #a02222; font-family: 'Cinzel', serif; text-align: center;">Editar Información</h2>
            <form action="perfil.php" method="POST" class="modal-form">
                <div class="form-group"><label for="nom">Nombre(s)</label><input type="text" id="nom" name="nom" value="<?php echo $nombre_display; ?>" required></div>
                <div class="form-group"><label for="paterno">Apellido Paterno</label><input type="text" id="paterno" name="paterno" value="<?php echo $paterno_display; ?>" required></div>
                <div class="form-group"><label for="materno">Apellido Materno</label><input type="text" id="materno" name="materno" value="<?php echo $materno_display; ?>"></div>
                <div class="form-group"><label for="fecha_n">Fecha de Nacimiento</label><input type="date" id="fecha_n" name="fecha_n" value="<?php echo $fecha_n_display; ?>"></div>
                <div class="form-group"><label for="dir">Dirección</label><input type="text" id="dir" name="dir" value="<?php echo $dir_display; ?>"></div>
                <div class="form-group"><label for="rude">RUDE</label><input type="text" id="rude" name="rude" value="<?php echo $rude_display; ?>"></div>
                <div class="form-group"><label for="telefono">Teléfono</label><input type="text" id="telefono" name="telefono" value="<?php echo $telefono_display; ?>"></div>

                <?php if ($rol === 'profesor'): ?>
                <div class="form-group">
                    <label for="curso">Curso</label>
                    <input type="text" id="curso" name="curso" value="<?php echo $curso_display; ?>">
                </div>
                <?php endif; ?>

                <div class="form-buttons">
                    <button type="submit" class="btn-guardar">Guardar Cambios</button>
                    <button type="button" id="cancelBtn" class="btn-cancelar">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let modal = document.getElementById('editModal');
        let editBtn = document.getElementById('editBtn');
        let closeBtn = document.getElementById('closeBtn');
        let cancelBtn = document.getElementById('cancelBtn');

        if(editBtn) { editBtn.addEventListener('click', function() { modal.style.display = 'block'; }); }
        if(closeBtn) { closeBtn.addEventListener('click', function() { modal.style.display = 'none'; }); }
        if(cancelBtn) { cancelBtn.addEventListener('click', function() { modal.style.display = 'none'; }); }

        setTimeout(function() {
            document.querySelectorAll('.mensaje').forEach(function(message) {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(function() { message.remove(); }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
