<?php
/**
 * Vista: nav_admin.php
 * Modulo: Administrador
 * Responsable: Josue Azofeifa
 * Descripcion: Barra de navegacion del panel de administracion.
 * Antes de incluirla, la vista define $paginaActual para resaltar el enlace.
 */

$paginaActual = $paginaActual ?? '';

$enlacesNav = [
    'dashboard' => ['../admin/dashboard.php', 'Mi panel'],
    'empresas'  => ['../admin/empresas.php', 'Empresas'],
    'zonas'     => ['../admin/zonas.php', 'Zonas'],
];
?>

<header class="barra">
  <nav class="navbar navbar-expand-lg container p-0" aria-label="Navegación principal">
    <a class="marca" href="../admin/dashboard.php">Muda<span>Conecta</span></a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#menuPrincipal" aria-controls="menuPrincipal"
            aria-expanded="false" aria-label="Abrir menú">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="menuPrincipal">
      <ul class="navbar-nav ms-lg-4 me-auto">
        <?php foreach ($enlacesNav as $clave => [$url, $texto]): ?>
          <li class="nav-item">
            <a class="nav-link<?= $clave === $paginaActual ? ' activo' : '' ?>"
               href="<?= $url ?>"
               <?= $clave === $paginaActual ? 'aria-current="page"' : '' ?>>
              <?= h($texto) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0">
        <span class="usuario"><?= h($_SESSION['nombre'] ?? 'Administrador') ?></span>
        <a class="btn btn-sm btn-contorno" href="../auth/logout.php">Cerrar sesión</a>
      </div>
    </div>
  </nav>
</header>