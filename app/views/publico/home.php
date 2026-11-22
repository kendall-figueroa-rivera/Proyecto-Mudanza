<?php
/**
 * Vista: home.php
 * Modulo: Publico
 * Responsable: Esteban Montero
 * Descripcion: Pagina de inicio. Punto de entrada visual a todos los modulos
 *              del sistema. Muestra empresas activas reales y permite
 *              arrancar una cotizacion directo desde el buscador del hero.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';

$haySesion = isset($_SESSION['id_usuario']);
$rolActual = $_SESSION['rol'] ?? null;

$panelPorRol = [
    'empresa'       => '../empresa/dashboard.php',
    'administrador' => '../admin/dashboard.php',
    'cliente'       => '../cliente/dashboard.php',
];

// Empresas activas mas recientes, con su calificacion promedio real.
// TODO (futuro refactor): mover esta consulta a app/models/Empresa.php
$stmt = $pdo->query(
    "SELECT TOP 4 e.id_empresa, e.nombre_comercial, e.descripcion,
            (SELECT AVG(CAST(calificacion AS DECIMAL(3,2))) FROM resenas r
             WHERE r.id_empresa = e.id_empresa AND r.estado = 'visible') AS promedio,
            (SELECT COUNT(*) FROM resenas r
             WHERE r.id_empresa = e.id_empresa AND r.estado = 'visible') AS total_resenas
     FROM empresas e
     WHERE e.estado = 'activa'
     ORDER BY e.fecha_registro DESC"
);
$empresasDestacadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MudaConecta — Encuentra tu mudanza ideal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../../public/assets/css/home.css">
</head>
<body>

<header class="wrap nav">
  <div class="brand">Muda<span>Conecta</span></div>
  <nav class="nav-actions">
    <?php if ($haySesion): ?>
      <?php if ($rolActual === 'empresa'): ?>
        <a href="../empresa/perfil.php">Mi empresa</a>
      <?php endif; ?>
      <a href="<?= htmlspecialchars($panelPorRol[$rolActual] ?? '../cliente/dashboard.php') ?>">Mi panel</a>
      <a href="../auth/logout.php" class="btn btn-ghost">Cerrar sesión</a>
    <?php else: ?>
      <a href="../auth/login.php">Iniciar sesión</a>
      <a href="../auth/registro.php" class="btn btn-solid">Crear cuenta</a>
    <?php endif; ?>
  </nav>
</header>

<section class="hero" style="border-top:none; padding-top:12px;">
  <div class="wrap">
    <h1>Tu mudanza,<br>de origen a destino,<br>en un solo lugar</h1>
    <p class="lead">Compara empresas de transporte por zona, flota y calificación real de otros clientes, y recibe el costo exacto antes de reservar.</p>

    <form class="ruta-buscador" action="directorio.php" method="GET">
      <div class="campo">
        <label for="origen">Origen</label>
        <input type="text" id="origen" name="origen" placeholder="¿Desde dónde te mudás?">
      </div>
      <div class="ruta-icono">→</div>
      <div class="campo">
        <label for="destino">Destino</label>
        <input type="text" id="destino" name="destino" placeholder="¿Hacia dónde?">
      </div>
      <button type="submit">Buscar empresas</button>
    </form>

    <div class="ruta-puntos">
      <span>Sin costo por cotizar</span>
      <span class="linea"></span>
      <span>Empresas verificadas</span>
      <span class="linea"></span>
      <span>Reseñas reales</span>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="section-head">
      <h2>Cómo funciona</h2>
      <p>De la búsqueda a la mudanza, en tres pasos.</p>
    </div>
    <div class="pasos">
      <div class="paso">
        <div class="num">1</div>
        <h3>Buscás y comparás</h3>
        <p>Filtrá empresas por zona de cobertura, tipo de vehículo y calificación.</p>
      </div>
      <div class="paso">
        <div class="num">2</div>
        <h3>Cotizás tu ruta</h3>
        <p>Indicá origen y destino y mirá el costo exacto calculado al instante.</p>
      </div>
      <div class="paso">
        <div class="num">3</div>
        <h3>Reservás y calificás</h3>
        <p>Confirmá el servicio y, al terminar, dejá tu reseña para otros usuarios.</p>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="section-head">
      <h2>Empresas activas</h2>
      <p>Perfiles ya aprobados y disponibles para recibir solicitudes.</p>
    </div>

    <?php if (empty($empresasDestacadas)): ?>
      <p class="vacio">Todavía no hay empresas aprobadas visibles aquí. En cuanto el administrador apruebe la primera, va a aparecer en esta sección.</p>
    <?php else: ?>
      <div class="empresas">
        <?php foreach ($empresasDestacadas as $emp): ?>
          <a class="empresa-card" href="perfil_empresa.php?id=<?= (int)$emp['id_empresa'] ?>">
            <h3><?= htmlspecialchars($emp['nombre_comercial']) ?></h3>
            <div class="rating">
              <?= $emp['total_resenas'] > 0
                    ? '★ ' . number_format((float)$emp['promedio'], 1) . ' (' . (int)$emp['total_resenas'] . ')'
                    : 'Sin reseñas aún' ?>
            </div>
            <p><?= htmlspecialchars($emp['descripcion'] ?? 'Empresa de transporte y mudanzas.') ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="section-head">
      <h2>Explorá la plataforma</h2>
      <p>Accesos directos a cada módulo según lo que necesités hacer.</p>
    </div>
    <div class="modulos">
      <div class="modulo">
        <div class="etiqueta">Para clientes</div>
        <h3>Buscar empresas</h3>
        <p>Filtrá por zona, precio y calificación.</p>
        <a class="ir" href="directorio.php">Ir al buscador</a>
      </div>
      <div class="modulo">
        <div class="etiqueta">Para clientes</div>
        <h3>Cotizar mudanza</h3>
        <p>Calculá el costo de tu ruta al instante.</p>
        <a class="ir" href="<?= $haySesion ? '../cliente/proforma.php' : '../auth/login.php' ?>">Cotizar ahora</a>
      </div>
      <div class="modulo">
        <div class="etiqueta">Para clientes</div>
        <h3>Mis solicitudes</h3>
        <p>Seguí el estado de tus mudanzas reservadas.</p>
        <a class="ir" href="<?= $haySesion ? '../cliente/solicitudes.php' : '../auth/login.php' ?>">Ver solicitudes</a>
      </div>
      <div class="modulo">
        <div class="etiqueta">Soporte</div>
        <h3>Contacto</h3>
        <p>Escribinos si tenés dudas o problemas.</p>
        <a class="ir" href="contacto.php">Escribir mensaje</a>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="cta-empresa">
      <div>
        <h2>¿Tenés una empresa de transporte?</h2>
        <p>Registrá tu flota, definí tus tarifas por ruta y empezá a recibir solicitudes de clientes reales.</p>
      </div>
      <div class="acciones-empresa">
        <a href="<?= $rolActual === 'empresa' ? '../empresa/perfil.php' : '../auth/login.php' ?>" class="btn btn-solid">Acceder a mi empresa</a>
        <?php if (!$haySesion): ?>
          <a href="../auth/registro.php" class="btn btn-accent">Registrar mi empresa</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="wrap">
    <span>MudaConecta — Proyecto académico, SC-502 Ambiente Web Cliente/Servidor</span>
    <a href="contacto.php">Contacto</a>
  </div>
</footer>

</body>
</html>
