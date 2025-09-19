<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    session_start();
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Tarea</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Lora", serif;
            background-color: #570a0a;
            color: #000000;
            margin: 0;
            padding: 20px;
        }

        .contenedor-editar-tarea {
            max-width: 500px;
            margin: 50px auto;
            background-color: #f7ebdd;
            border-radius: 15px;
            padding: 30px 30px 20px 30px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
        }

        .titulo-editar {
            color: #a02222;
            font-family: "Cinzel", serif;
            font-size: 28px;
            text-align: center;
            margin-bottom: 25px;
        }

        .form-editar-tarea label {
            font-weight: bold;
            color: #a02222;
            margin-top: 10px;
            margin-bottom: 5px;
            display: block;
        }

        .form-editar-tarea input[type="text"],
        .form-editar-tarea input[type="number"],
        .form-editar-tarea textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: "Lora", serif;
            font-size: 16px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        .form-editar-tarea textarea {
            min-height: 70px;
            resize: vertical;
        }

        .btn-guardar {
            background-color: #570a0a;
            color: #f7ebdd;
            padding: 12px 0;
            border-radius: 8px;
            border: none;
            font-size: 18px;
            font-weight: bold;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-guardar:hover {
            background-color: #a02222;
        }
    </style>
</head>

<body>
    <div class="contenedor-editar-tarea">
        <div class="titulo-editar">✏️ Editar Tarea</div>
        <form class="form-editar-tarea" action="terminareditadotarea.php?id_tarea=<?php echo $_GET['id_tarea'] ?>" method="POST">
            <?php
            $conexion = mysqli_connect("localhost", "root", "", "bd_vicmac");
            $id_tarea = $_GET['id_tarea'];
            $consultaTarea = "SELECT * FROM tarea WHERE idtarea = $id_tarea ;";
            $resultadoTarea = mysqli_query($conexion, $consultaTarea);
            $filaTarea = mysqli_fetch_assoc($resultadoTarea);
            ?>
            <label for="titulo">Título de la tarea</label>
            <input type="text" id="titulo" name="titulo" value="<?php echo trim($filaTarea['titulo']) ?>" required>

            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" required><?php echo trim($filaTarea['desc']) ?></textarea>

            <label for="tema">Tema</label>
            <input type="text" id="tema" name="tema" value="<?php echo trim($filaTarea['tema']) ?>" required>

            <label for="puntos">Puntos</label>
            <input type="number" id="puntos" name="puntos" value="<?php echo $filaTarea['nota'] ?>" min="0" required>

            <button type="submit" class="btn-guardar">Guardar cambios</button>
        </form>
    </div>
</body>

</html>