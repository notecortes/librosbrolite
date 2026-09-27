<?php
/**
 * BookSwap · dashboard.php — Mi BookSwap (Home del Usuario).
 *
 * Bloques en orden estricto:
 * 1. Saldo grande + botón "Ver catálogo"
 * 2. Reservas activas: cards con barcode Code 128, código en texto, cuenta atrás y Cancelar (vía modal)
 * 3. Aviso anti-bloqueo/depósito si saldo < coste y sin reservas
 * 4. Últimos ingresos en el catálogo (5 cards disponibles)
 * 5. Últimos movimientos (5) con enlace a /mi-historial
 */
declare(strict_types=1);

$saldoTokens = (int) ($saldo ?? 0);
$reservasActivas = $reservasActivas ?? [];
$ultimosLibros = $ultimosLibros ?? [];
$ultimosMovimientos = $ultimosMovimientos ?? [];
$ahoraTs = time();
$costeLibro = (int) ($config['coste_libro'] ?? 1);
?>

<!-- BANNER SUPERIOR DE BIENVENIDA -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
  <div>
    <h1 class="h2 fw-800 mb-1">¡Hola, <?= e($usuario['nombre']) ?>! 👋</h1>
    <p class="text-muted mb-0">Bienvenido a tu espacio personal en <?= e($config['centro_nombre'] ?? 'BookSwap') ?>.</p>
  </div>
</div>

<!-- BLOQUE 1: SALDO GRANDE + BOTÓN "VER CATÁLOGO" (§3.2) -->
<div class="card border border-warning shadow-sm rounded-4 p-4 p-md-5 mb-4 bg-surface" id="bloque-saldo">
  <div class="row align-items-center g-4">
    <div class="col-md-7">
      <div class="text-muted small fw-bold text-uppercase mb-1">Tu saldo disponible</div>
      <div class="d-flex align-items-baseline gap-2 mb-1">
        <span class="display-3 fw-800 text-warning-emphasis" id="saldo-dashboard-valor" data-contador="<?= $saldoTokens ?>">
          <?= $saldoTokens ?>
        </span>
        <span class="fs-2 fw-bold text-warning-emphasis">🪙 tokens</span>
      </div>
      <?php
        $numResActivas = count($reservasActivas);
        $tokComprometidos = 0;
        foreach ($reservasActivas as $r) {
            $tokComprometidos += (int) ($r['tokens'] > 0 ? $r['tokens'] : 1);
        }
      ?>
      <div class="small text-secondary mb-2" id="saldo-comprometido-reservas">
        <i class="bi bi-lock-fill text-warning me-1"></i><strong><?= $tokComprometidos ?> 🪙 comprometidos en <?= $numResActivas ?> reserva(s) activa(s)</strong>
      </div>
      <p class="text-muted mb-0 small">
        Cada reserva descuenta 1 🪙 hasta que la recoges o la anulas. Tráenos libros al mostrador para acumular más saldo.
      </p>
    </div>
    <div class="col-md-5 text-md-end">
      <a href="/catalogo" class="btn btn-primary btn-lg rounded-pill px-4 fw-bold shadow-sm" id="btn-ver-catalogo-saldo">
        <i class="bi bi-book me-2"></i>Ver catálogo
      </a>
    </div>
  </div>
</div>

<!-- BLOQUE 2: RESERVAS ACTIVAS (CODE 128 + TEXTO + CUENTA ATRÁS + CANCELAR MODAL) (§3.2) -->
<div class="mb-5" id="bloque-reservas-activas">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h5 fw-bold mb-0">
      <i class="bi bi-upc-scan text-primary me-2"></i>Mis Reservas Activas
    </h2>
    <?php if (!empty($reservasActivas)): ?>
      <a href="/mis-reservas" class="btn btn-sm btn-outline-primary rounded-pill">
        Gestionar reservas <i class="bi bi-arrow-right ms-1"></i>
      </a>
    <?php endif; ?>
  </div>

  <?php if (empty($reservasActivas)): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-surface">
      <div class="py-3">
        <i class="bi bi-bookmark-x text-muted display-4 mb-2 d-inline-block"></i>
        <h3 class="h6 fw-bold text-dark">No tienes ninguna reserva activa</h3>
        <p class="text-muted small mb-3">Reserva títulos gratis en el catálogo y recógelos en el mostrador físico.</p>
        <a href="/catalogo" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold">
          <i class="bi bi-compass me-1"></i>Explorar libros para reservar
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($reservasActivas as $res): ?>
        <?php
          $limiteTs = !empty($res['fecha_limite']) ? strtotime((string) $res['fecha_limite']) : (!empty($res['expira_en']) ? strtotime((string) $res['expira_en']) : 0);
          $segundosRestantes = max(0, $limiteTs - $ahoraTs);
          $horasRestantes = (int) floor($segundosRestantes / 3600);
          $minutosRestantes = (int) floor(($segundosRestantes % 3600) / 60);
          $portada = catalogo_resolver_url_portada($res);
        ?>
        <div class="col-md-6 col-xl-4">
          <div class="card border-0 shadow-sm rounded-4 p-3 bg-surface h-100 d-flex flex-column">
            <div class="d-flex gap-3 mb-3">
              <img src="<?= e($portada) ?>" alt="" width="60" height="85" class="rounded-3 object-fit-cover shadow-xs border flex-shrink-0" loading="lazy">
              <div class="flex-grow-1 min-w-0">
                <h3 class="h6 fw-bold mb-1 text-truncate" title="<?= e($res['titulo']) ?>">
                  <a href="/libro/<?= (int) $res['libro_id'] ?>" class="text-decoration-none text-reset">
                    <?= e($res['titulo']) ?>
                  </a>
                </h3>
                <p class="small text-muted mb-2 text-truncate"><?= e($res['autor']) ?></p>
                <div class="small fw-semibold <?= $horasRestantes < 12 ? 'text-danger' : 'text-primary' ?>">
                  <i class="bi bi-clock me-1"></i>Vence en: <?= $horasRestantes ?>h <?= $minutosRestantes ?>m
                </div>
              </div>
            </div>

            <!-- Código de barras Code 128 client-side -->
            <div class="p-2 bg-white rounded-3 border text-center mb-2 overflow-auto shadow-xs">
              <svg id="barcode-dash-<?= (int) $res['id'] ?>" class="barcode-svg" style="max-width: 100%; height: 60px;"></svg>
            </div>
            <div class="text-center font-monospace fw-bold fs-6 text-primary mb-3 bg-light rounded py-1 border">
              <?= e($res['codigo']) ?>
            </div>

            <!-- Botón Cancelar con MODAL (Fase 18 §3.5: sin confirm() nativo) -->
            <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
              <a href="/mis-reservas" class="btn btn-sm btn-outline-secondary rounded-pill">
                Ver detalle
              </a>
              <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCancelarDash-<?= (int) $res['id'] ?>">
                <i class="bi bi-x-circle me-1"></i>Cancelar
              </button>
            </div>

            <!-- Modal de Cancelación -->
            <div class="modal fade" id="modalCancelarDash-<?= (int) $res['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                  <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fs-6 fw-bold text-danger">
                      <i class="bi bi-exclamation-triangle me-1"></i>Cancelar Reserva
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body py-3">
                    <p class="mb-1">¿Deseas cancelar la reserva de <strong><?= e($res['titulo']) ?></strong>?</p>
                    <p class="small text-muted mb-0">El ejemplar volverá a estar disponible de inmediato para otros lectores.</p>
                  </div>
                  <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">No cancelar</button>
                    <form method="POST" action="/mis-reservas/cancelar" class="d-inline">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="transaccion_id" value="<?= (int) $res['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger fw-bold">Sí, cancelar reserva</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- BLOQUE 3: AVISO SI SALDO < COSTE_LIBRO Y SIN RESERVAS -->
<?php if ($saldoTokens < $costeLibro && empty($reservasActivas)): ?>
<div class="card border border-warning shadow-sm rounded-4 p-4 mb-5 bg-warning-subtle" id="bloque-aviso-tokens">
  <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="p-3 bg-warning text-dark rounded-circle">
        <i class="bi bi-coin fs-3"></i>
      </div>
      <div>
        <h2 class="h5 fw-bold text-dark mb-1">Deposita tus primeros libros para ganar tokens</h2>
        <p class="text-dark small mb-0">
          Trae libros en buen estado a nuestro punto físico para recibir tokens y poder solicitar libros del catálogo.
        </p>
      </div>
    </div>
    <div>
      <a href="/como-funciona" class="btn btn-dark fw-bold px-4 rounded-pill flex-shrink-0 shadow-sm">
        <i class="bi bi-info-circle me-1"></i>Cómo funciona
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- BLOQUE 4: ÚLTIMOS INGRESOS EN EL CATÁLOGO (5 CARDS DISPONIBLES) (§3.2) -->
<div class="mb-5" id="bloque-ultimos-ingresos">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h5 fw-bold mb-0">
      <i class="bi bi-stars text-warning me-2"></i>Últimos Ingresos en el Catálogo
    </h2>
    <a href="/catalogo" class="btn btn-sm btn-outline-primary rounded-pill">
      Ver catálogo completo <i class="bi bi-arrow-right ms-1"></i>
    </a>
  </div>

  <?php if (empty($ultimosLibros)): ?>
    <p class="text-muted small">No hay libros con copias disponibles en este momento.</p>
  <?php else: ?>
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-5 g-3">
      <?php foreach ($ultimosLibros as $lib): ?>
        <?php
          $portadaLib = catalogo_resolver_url_portada($lib);
          $dispCount = (int) ($lib['disponibles_count'] ?? 0);
        ?>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden bg-surface transition-hover">
            <a href="/libro/<?= (int) $lib['id'] ?>" class="ratio ratio-4x3 bg-light overflow-hidden d-block">
              <img src="<?= e($portadaLib) ?>" alt="<?= e($lib['titulo']) ?>" width="200" height="150" class="object-fit-cover w-100 h-100" loading="lazy">
            </a>
            <div class="p-3 d-flex flex-column flex-grow-1">
              <h3 class="h6 fw-bold mb-1 text-truncate" title="<?= e($lib['titulo']) ?>">
                <a href="/libro/<?= (int) $lib['id'] ?>" class="text-decoration-none text-reset">
                  <?= e($lib['titulo']) ?>
                </a>
              </h3>
              <p class="small text-muted mb-2 text-truncate"><?= e($lib['autor']) ?></p>
              <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                <span class="badge bg-success-subtle text-success small rounded-pill">
                  <?= $dispCount ?> disp.
                </span>
                <a href="/libro/<?= (int) $lib['id'] ?>" class="btn btn-xs btn-outline-primary rounded-pill py-0 px-2 fw-semibold">
                  Ver
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- BLOQUE 5: ÚLTIMOS MOVIMIENTOS (5) CON ENLACE A /MI-HISTORIAL (§3.2) -->
<div class="mb-4" id="bloque-ultimos-movimientos">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h5 fw-bold mb-0">
      <i class="bi bi-clock-history text-secondary me-2"></i>Últimos Movimientos
    </h2>
    <a href="/mi-historial" class="btn btn-sm btn-outline-secondary rounded-pill">
      Ver historial completo <i class="bi bi-arrow-right ms-1"></i>
    </a>
  </div>

  <?php if (empty($ultimosMovimientos)): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-surface text-muted small">
      Aún no se han registrado movimientos de tokens en tu cuenta.
    </div>
  <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-surface">
      <div class="table-responsive mb-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col" class="ps-4">Fecha</th>
              <th scope="col">Concepto</th>
              <th scope="col" class="text-end pe-4">Tokens</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ultimosMovimientos as $mov): ?>
              <?php $cant = (int) $mov['cantidad']; ?>
              <tr>
                <td class="ps-4 text-muted small font-monospace">
                  <?= date('d/m/Y H:i', strtotime((string) ($mov['fecha'] ?? $mov['creado_en'] ?? 'now'))) ?>
                </td>
                <td>
                  <span class="badge bg-secondary-subtle text-secondary text-uppercase me-2 font-monospace small">
                    <?= e($mov['tipo']) ?>
                  </span>
                  <span class="small fw-semibold"><?= e($mov['concepto'] ?? $mov['motivo'] ?? '') ?></span>
                </td>
                <td class="text-end pe-4 font-monospace fw-bold <?= $cant > 0 ? 'text-success' : 'text-danger' ?>">
                  <?= $cant > 0 ? '+' . $cant : $cant ?> 🪙
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($reservasActivas)): ?>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof JsBarcode === 'function') {
      <?php foreach ($reservasActivas as $res): ?>
        try {
          JsBarcode('#barcode-dash-<?= (int) $res['id'] ?>', '<?= addslashes((string) $res['codigo']) ?>', {
            format: 'CODE128',
            lineColor: '#000000',
            width: 2,
            height: 50,
            displayValue: false
          });
        } catch (e) {
          console.error('Error renderizando JsBarcode:', e);
        }
      <?php endforeach; ?>
    }
  });
  </script>
<?php endif; ?>
