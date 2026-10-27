<?php
/**
 * Vista: dashboard.php
 * Modulo: Administrador
 * Responsable: Josué Azofeifa
 * Descripcion: Panel principal del administrador.
 */

session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

AdminController::exigirAdministrador();

$nombreAdmin = htmlspecialchars(
    $_SESSION['nombre'] ?? 'Administrador',
    ENT_QUOTES,
    'UTF-8'
);

$paginaActual = 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel administrador — MudaConecta</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../../public/assets/css/estilos.css">
  <link rel="stylesheet" href="../../../public/assets/css/cliente.css">
</head>
<body>

<?php require __DIR__ . '/../partials/nav_admin.php'; ?>

<main class="container">
  <h1>Hola, <?= $nombreAdmin ?></h1>
  <p class="intro">
    Desde aquí puedes administrar las empresas y el catálogo de zonas de la plataforma.
  </p>

  <div class="row g-4">
    <div class="col-md-6">
      <section class="panel h-100 d-flex flex-column">
        <h2>Empresas</h2>
        <p>Revisa las empresas registradas y gestiona su aprobación o suspensión.</p>
        <a class="btn btn-mostaza mt-auto align-self-start" href="empresas.php">
          Administrar empresas
        </a>
      </section>
    </div>

    <div class="col-md-6">
      <section class="panel h-100 d-flex flex-column">
        <h2>Catálogo de zonas</h2>
        <p>Mantén las zonas geográficas con su provincia y cantón.</p>
        <a class="btn btn-mostaza mt-auto align-self-start" href="zonas.php">
          Administrar zonas
        </a>
      </section>
    </div>
  </div>
</main>

<footer class="pie">
  <div class="container">
    <span>MudaConecta</span>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
