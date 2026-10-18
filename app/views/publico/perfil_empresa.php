<?php
/**
 * Vista: perfil_empresa.php
 * Modulo: Cliente (vista publica)
 * Responsable: [nombre del integrante]
 * Descripcion: Perfil individual de una empresa activa: datos de contacto,
 *              zonas de cobertura, rutas con tarifa, calificacion promedio
 *              (sp_calcular_calificacion_promedio) y resenas visibles (RF-10).
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$cliente   = new ClienteController($pdo);
$idEmpresa = (int) ($_GET['id'] ?? 0);
$empresa   = $idEmpresa > 0 ? $cliente->obtenerEmpresa($idEmpresa) : null;

if ($empresa) {
    $calificacion = $cliente->obtenerCalificacion($idEmpresa);
    $zonas        = $cliente->obtenerZonasEmpresa($idEmpresa);
    $tarifas      = $cliente->obtenerTarifasEmpresa($idEmpresa);
    $resenas      = $cliente->obtenerResenasEmpresa($idEmpresa);
} else {
    http_response_code(404);
}

$esCliente    = ClienteController::esCliente();
$paginaActual = 'directorio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $empresa ? h($empresa['nombre_comercial']) : 'Empresa no encontrada' ?> — MudaConecta</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../../public/assets/css/estilos.css">
  <link rel="stylesheet" href="../../../public/assets/css/cliente.css">
</head>
<body>
<?php require __DIR__ . '/../partials/nav_cliente.php'; ?>

<main class="container">
  <?php if (!$empresa): ?>
    <h1>Empresa no encontrada</h1>
    <div class="vacio">
      <p>Esta empresa no existe o no está disponible en este momento.</p>
      <a class="btn btn-contorno" href="directorio.php">Volver al directorio</a>
    </div>
  <?php else:
    $promedio = $calificacion['promedio'];
    $llenas   = (int) round($promedio);
  ?>
    <a class="d-inline-block mb-3" href="directorio.php">Volver al directorio</a>

    <div class="row g-4 align-items-end mb-4">
      <div class="col-lg-8">
        <h1><?= h($empresa['nombre_comercial']) ?></h1>
        <div class="estrellas" aria-label="Calificación <?= number_format($promedio, 1) ?> de 5">
          <?= str_repeat('★', $llenas) ?><span class="vacias"><?= str_repeat('★', 5 - $llenas) ?></span>
          <span class="texto-suave">
            <?= $calificacion['total_resenas'] > 0
                ? number_format($promedio, 1) . ' de 5 (' . $calificacion['total_resenas'] . ' reseñas)'
                : 'Todavía no tiene reseñas' ?>
          </span>
        </div>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a class="btn btn-mostaza"
           href="<?= $esCliente ? '../cliente/proforma.php?empresa=' . (int) $empresa['id_empresa'] : '../auth/login.php' ?>">
          <?= $esCliente ? 'Cotizar con esta empresa' : 'Inicia sesión para cotizar' ?>
        </a>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <section class="panel mb-4">
          <h2>Sobre la empresa</h2>
          <p><?= nl2br(h($empresa['descripcion'] ?: 'Esta empresa aún no agregó una descripción.')) ?></p>
        </section>

        <section class="panel mb-4">
          <h2>Rutas y tarifas</h2>
          <?php if (empty($tarifas)): ?>
            <p class="texto-suave mb-0">Esta empresa todavía no publicó tarifas.</p>
          <?php else: ?>
            <p class="texto-suave">El precio final se calcula como tarifa base + precio por kilómetro × distancia.</p>
            <div class="tabla-scroll">
              <table class="table tabla-tarifas mb-0">
                <thead>
                  <tr>
                    <th scope="col">Ruta</th>
                    <th scope="col">Distancia</th>
                    <th scope="col">Base</th>
                    <th scope="col">Por km</th>
                    <th scope="col">Estimado</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($tarifas as $t): ?>
                    <tr>
                      <td><?= h($t['zona_origen']) ?> a <?= h($t['zona_destino']) ?></td>
                      <td><?= number_format((float) $t['distancia_km'], 1, ',', '.') ?> km</td>
                      <td><?= colones($t['precio_base']) ?></td>
                      <td><?= colones($t['precio_por_km']) ?></td>
                      <td><strong><?= colones($t['monto_estimado']) ?></strong></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="panel">
          <h2>Reseñas</h2>
          <?php if (empty($resenas)): ?>
            <p class="texto-suave mb-0">Todavía no hay reseñas. Las reseñas solo las pueden dejar clientes que completaron una mudanza con la empresa.</p>
          <?php else: ?>
            <?php foreach ($resenas as $r): ?>
              <article class="resena">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                  <span class="autor"><?= h($r['cliente']) ?></span>
                  <span class="estrellas" aria-label="<?= (int) $r['calificacion'] ?> de 5">
                    <?= str_repeat('★', (int) $r['calificacion']) ?><span class="vacias"><?= str_repeat('★', 5 - (int) $r['calificacion']) ?></span>
                  </span>
                </div>
                <?php if ($r['comentario']): ?>
                  <p class="mb-1 mt-2"><?= nl2br(h($r['comentario'])) ?></p>
                <?php endif; ?>
                <time class="texto-suave small"><?= date('d/m/Y', strtotime($r['fecha'])) ?></time>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      </div>

      <aside class="col-lg-4">
        <section class="panel mb-4">
          <h2>Contacto</h2>
          <dl class="mb-0">
            <?php if ($empresa['telefono']): ?>
              <dt>Teléfono</dt><dd><?= h($empresa['telefono']) ?></dd>
            <?php endif; ?>
            <dt>Correo</dt><dd><a href="mailto:<?= h($empresa['correo']) ?>"><?= h($empresa['correo']) ?></a></dd>
            <?php if ($empresa['direccion']): ?>
              <dt>Dirección</dt><dd><?= h($empresa['direccion']) ?></dd>
            <?php endif; ?>
            <dt>En la plataforma desde</dt><dd class="mb-0"><?= date('m/Y', strtotime($empresa['fecha_registro'])) ?></dd>
          </dl>
        </section>

        <section class="panel">
          <h2>Zonas de cobertura</h2>
          <?php if (empty($zonas)): ?>
            <p class="texto-suave mb-0">Zonas por definir.</p>
          <?php else: ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($zonas as $z): ?>
                <li class="mb-1">
                  <?= h($z['nombre']) ?>
                  <span class="texto-suave small"><?= h($z['canton'] ? $z['canton'] . ', ' : '') ?><?= h($z['provincia']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </aside>
    </div>
  <?php endif; ?>
</main>

<footer class="pie">
  <div class="container d-flex justify-content-between flex-wrap gap-2">
    <span>MudaConecta</span>
    <a href="contacto.php">¿Necesitas ayuda? Escríbenos</a>
  </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../public/assets/js/cliente.js"></script>
</body>
</html>
