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
<style>
  :root{
    --ink:#1B2A41; --paper:#F1EEE4; --paper-2:#E7E1D2;
    --mustard:#C98A2B; --slate:#3F6774; --line:#D8D0BC; --muted:#6B6A5F;
  }
  *{ box-sizing:border-box; }
  body{
    margin:0; background:var(--paper); color:var(--ink);
    font-family:'Inter',sans-serif; line-height:1.5;
  }
  h1,h2,h3,.display{
    font-family:'Big Shoulders Display',sans-serif;
    font-weight:900; text-transform:uppercase; letter-spacing:.01em;
    line-height:0.95; margin:0;
  }
  a{ color:inherit; text-decoration:none; }
  .wrap{ max-width:1120px; margin:0 auto; padding:0 24px; }

  /* Nav */
  .nav{ display:flex; align-items:center; justify-content:space-between; padding:22px 0; }
  .brand{ font-family:'Big Shoulders Display',sans-serif; font-weight:900; font-size:1.5rem; }
  .brand span{ color:var(--mustard); }
  .nav-actions{ display:flex; gap:14px; align-items:center; font-weight:600; font-size:.95rem; }
  .btn{
    display:inline-block; padding:11px 22px; border-radius:2px; font-weight:700;
    font-size:.95rem; border:1.5px solid var(--ink);
  }
  .btn-solid{ background:var(--ink); color:var(--paper); }
  .btn-ghost{ background:transparent; color:var(--ink); }
  .btn-accent{ background:var(--mustard); border-color:var(--mustard); color:var(--ink); }

  /* Hero */
  .hero{ padding:56px 0 64px; }
  .hero h1{ font-size:clamp(2.6rem, 6vw, 4.6rem); max-width:11ch; }
  .hero p.lead{ max-width:46ch; color:var(--muted); font-size:1.08rem; margin:18px 0 32px; }

  .ruta-buscador{
    background:var(--ink); border-radius:4px; padding:22px;
    display:flex; flex-wrap:wrap; gap:14px; align-items:center;
  }
  .ruta-buscador .campo{ flex:1 1 200px; }
  .ruta-buscador label{
    display:block; color:#B7C2CE; font-size:.78rem; margin-bottom:6px; font-weight:600;
  }
  .ruta-buscador input{
    width:100%; padding:12px 14px; border:none; border-radius:2px;
    font-family:'Inter',sans-serif; font-size:1rem;
  }
  .ruta-icono{ color:var(--mustard); font-size:1.4rem; padding-top:18px; }
  .ruta-buscador button{
    flex:0 0 auto; padding:14px 26px; background:var(--mustard); color:var(--ink);
    border:none; border-radius:2px; font-weight:700; font-size:1rem; cursor:pointer;
    margin-top:18px;
  }

  .ruta-puntos{
    display:flex; align-items:center; gap:6px; margin-top:28px; color:var(--muted); font-size:.85rem;
  }
  .ruta-puntos .linea{
    flex:1; height:1px; background-image:linear-gradient(to right, var(--muted) 40%, transparent 0%);
    background-size:8px 1px; background-repeat:repeat-x;
  }

  /* Secciones */
  section{ padding:56px 0; border-top:1px solid var(--line); }
  .section-head{ display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:32px; gap:20px; }
  .section-head h2{ font-size:clamp(1.7rem, 3.5vw, 2.4rem); }
  .section-head p{ color:var(--muted); max-width:38ch; margin:0; }

  /* Como funciona */
  .pasos{ display:grid; grid-template-columns:repeat(3, 1fr); gap:28px; }
  .paso .num{
    font-family:'Big Shoulders Display',sans-serif; font-weight:900; font-size:2.6rem;
    color:var(--mustard); line-height:1;
  }
  .paso h3{ font-size:1.25rem; margin:10px 0 8px; text-transform:none; }
  .paso p{ color:var(--muted); margin:0; font-size:.95rem; }

  /* Empresas */
  .empresas{ display:grid; grid-template-columns:repeat(4, 1fr); gap:18px; }
  .empresa-card{
    background:var(--paper-2); border-top:3px solid var(--slate); padding:20px; border-radius:2px;
  }
  .empresa-card h3{ font-size:1.1rem; text-transform:none; margin:0 0 6px; font-family:'Inter',sans-serif; font-weight:700; }
  .empresa-card .rating{ color:var(--mustard); font-weight:700; font-size:.9rem; margin-bottom:8px; }
  .empresa-card p{ color:var(--muted); font-size:.88rem; margin:0; }
  .vacio{ color:var(--muted); font-style:italic; }

  /* Modulos */
  .modulos{ display:grid; grid-template-columns:repeat(4, 1fr); gap:1px; background:var(--line); border:1px solid var(--line); }
  .modulo{ background:var(--paper); padding:26px 22px; }
  .modulo .etiqueta{ color:var(--slate); font-weight:700; font-size:.78rem; margin-bottom:8px; }
  .modulo h3{ font-size:1.15rem; text-transform:none; font-family:'Inter',sans-serif; font-weight:700; margin:0 0 6px; }
  .modulo p{ color:var(--muted); font-size:.88rem; margin:0 0 14px; }
  .modulo a.ir{ color:var(--ink); font-weight:700; font-size:.9rem; border-bottom:2px solid var(--mustard); padding-bottom:1px; }

  /* CTA empresa */
  .cta-empresa{
    background:var(--ink); color:var(--paper); border-radius:4px; padding:44px;
    display:flex; justify-content:space-between; align-items:center; gap:24px; flex-wrap:wrap;
  }
  .cta-empresa h2{ color:var(--paper); font-size:1.9rem; max-width:16ch; }
  .cta-empresa p{ color:#C9D1D8; max-width:34ch; margin:10px 0 0; }

  footer{ border-top:1px solid var(--line); padding:28px 0; color:var(--muted); font-size:.85rem; }
  footer .wrap{ display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; }

  @media (max-width:860px){
    .pasos, .empresas, .modulos{ grid-template-columns:repeat(2, 1fr); }
    .cta-empresa{ flex-direction:column; align-items:flex-start; }
  }
  @media (max-width:560px){
    .pasos, .empresas, .modulos{ grid-template-columns:1fr; }
    .ruta-buscador button{ width:100%; }
  }
</style>
</head>
<body>

<header class="wrap nav">
  <div class="brand">Muda<span>Conecta</span></div>
  <nav class="nav-actions">
    <?php if ($haySesion): ?>
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
      <a href="../auth/registro.php" class="btn btn-accent">Registrar mi empresa</a>
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
