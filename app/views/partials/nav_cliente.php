<?php
/**
 * Vista: nav_cliente.php
 * Modulo: Cliente (compartido con las vistas publicas del modulo)
 * Responsable: [nombre del integrante]
 * Descripcion: Barra de navegacion del panel cliente. Si no hay sesion de
 *              cliente muestra los enlaces publicos (directorio, contacto,
 *              login). Antes de incluirla, la vista define $paginaActual con
 *              el nombre de su archivo (ej. 'proforma') para resaltar el enlace.
 */

$paginaActual = $paginaActual ?? '';
$rolNav       = $_SESSION['rol'] ?? null;

$panelPorRol = [
    'empresa'       => '../empresa/dashboard.php',
    'administrador' => '../admin/dashboard.php',
];

if ($rolNav === 'cliente') {
    $enlacesNav = [
        'dashboard'           => ['../cliente/dashboard.php', 'Mi panel'],
        'buscar'              => ['../cliente/buscar.php', 'Buscar por ruta'],
        'historial_proformas' => ['../cliente/historial_proformas.php', 'Proformas'],
        'solicitudes'         => ['../cliente/solicitudes.php', 'Solicitudes'],
        'calificar'           => ['../cliente/calificar.php', 'Reseñas'],
    ];
} else {
    $enlacesNav = [
        'home'       => ['../publico/home.php', 'Inicio'],
        'directorio' => ['../publico/directorio.php', 'Empresas'],
        'contacto'   => ['../publico/contacto.php', 'Contacto'],
    ];
}
?>
<header class="barra">
  <nav class="navbar navbar-expand-lg container p-0" aria-label="Navegación principal">
    <a class="marca" href="../publico/home.php">Muda<span>Conecta</span></a>
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
        <?php if ($rolNav === 'cliente'): ?>
          <span class="usuario"><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></span>
          <a class="btn btn-sm btn-mostaza" href="../cliente/proforma.php">Cotizar mudanza</a>
          <a class="btn btn-sm btn-contorno" href="../auth/logout.php">Cerrar sesión</a>
        <?php elseif (isset($panelPorRol[$rolNav])): ?>
          <a class="nav-link" href="<?= $panelPorRol[$rolNav] ?>">Mi panel</a>
          <a class="btn btn-sm btn-contorno" href="../auth/logout.php">Cerrar sesión</a>
        <?php else: ?>
          <a class="nav-link" href="../auth/login.php">Iniciar sesión</a>
          <a class="btn btn-sm btn-tinta" href="../auth/registro.php">Crear cuenta</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
</header>
