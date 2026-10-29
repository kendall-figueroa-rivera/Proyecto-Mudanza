<?php
/**
 * Vista: nav_empresa.php
 * Modulo: Empresa (compartido con las vistas del modulo)
 * Responsable: Santiago Correa Fallas
 * Descripcion: Barra de navegacion del panel empresa. Antes de incluirla, la
 *              vista define $paginaActual con el nombre de su archivo
 *              (ej. 'flota') para resaltar el enlace.
 */

$paginaActual = $paginaActual ?? '';

$enlacesNav = [
    'perfil' => ['../empresa/perfil.php', 'Perfil'],
    'flota'  => ['../empresa/flota.php', 'Flota'],
    'tarifas' => ['../empresa/tarifas.php', 'Rutas y tarifas'],
];
?>
<header class="barra">
  <nav class="navbar navbar-expand-lg container p-0" aria-label="Navegación principal">
    <a class="marca" href="../empresa/perfil.php">Muda<span>Conecta</span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal"
            aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menú">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menuPrincipal">
      <ul class="navbar-nav ms-lg-4 me-auto">
        <?php foreach ($enlacesNav as $clave => [$url, $texto]): ?>
          <li class="nav-item">
            <a class="nav-link<?= $clave === $paginaActual ? ' activo' : '' ?>" href="<?= $url ?>"
               <?= $clave === $paginaActual ? 'aria-current="page"' : '' ?>><?= $texto ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0">
        <span class="usuario"><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></span>
        <a class="btn btn-sm btn-contorno" href="../auth/logout.php">Cerrar sesión</a>
      </div>
    </div>
  </nav>
</header>
