<?php
/**
 * BookSwap · Mis Reservas (Usuario).
 * Muestra las reservas activas con cuenta atrás, código de recogida, QR y opción de cancelación,
 * además del historial de reservas anteriores (entregadas, canceladas, expiradas).
 */
declare(strict_types=1);

$activas = $activas ?? [];
$historial = $historial ?? [];
$config = $config ?? [];
$horasReserva = (int) ($config['horas_reserva'] ?? 72);
?>

<div class="container-xxl py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
      <h1 class="h2 fw-800 mb-1">Mis Reservas</h1>
      <p class="text-muted mb-0">Gestiona tus libros reservados y consulta tus códigos de recogida en mostrador.</p>
    </div>
    <a href="/catalogo" class="btn btn-outline-primary fw-semibold">
      <i class="bi bi-search me-2"></i>Explorar Catálogo
    </a>
  </div>

  <!-- SECCIÓN: RESERVAS ACTIVAS -->
  <div class="mb-5">
    <div class="d-flex align-items-center gap-2 mb-3">
      <h2 class="h5 fw-bold mb-0">
        <i class="bi bi-bookmark-check-fill text-primary me-2"></i>Reservas Activas
      </h2>
      <span class="badge bg-primary rounded-pill"><?= count($activas) ?></span>
    </div>

    <?php if (empty($activas)): ?>
      <div class="card border-0 shadow-sm rounded-4 text-center p-5">
        <div class="display-6 text-muted mb-3"><i class="bi bi-journal-x"></i></div>
        <h3 class="h5 fw-bold mb-2">No tienes ninguna reserva activa</h3>
        <p class="text-muted mb-4">¿Buscas tu próxima lectura? Explora el catálogo de nuestra biblioteca ciudadana.</p>
        <div>
          <a href="/catalogo" class="btn btn-primary fw-bold px-4 shadow-sm">
            <i class="bi bi-book me-2"></i>Ver Libros Disponibles
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($activas as $res): ?>
          <?php
            $fechaLimiteTs = !empty($res['fecha_limite']) ? strtotime($res['fecha_limite']) : 0;
            $segundosRestantes = max(0, $fechaLimiteTs - time());
            $horasRestantes = (int) floor($segundosRestantes / 3600);
            $minutosRestantes = (int) floor(($segundosRestantes % 3600) / 60);
          ?>
          <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 position-relative">
              <div class="card-body p-4">
                <div class="d-flex gap-3">
                  <!-- Portada -->
                  <div class="flex-shrink-0" style="width: 100px;">
                    <div class="ratio ratio-2x3 rounded-3 shadow-xs overflow-hidden bg-light border">
                      <?php if (!empty($res['portada_url'])): ?>
                        <img src="<?= e($res['portada_url']) ?>" alt="<?= e($res['titulo']) ?>" width="100" height="150" loading="lazy" class="object-fit-cover w-100 h-100">
                      <?php else: ?>
                        <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center p-2 text-center text-muted">
                          <i class="bi bi-book fs-3 mb-1"></i>
                          <span class="small fw-semibold lh-1" style="font-size: 0.65rem;"><?= e(mb_strimwidth($res['titulo'], 0, 20, '...')) ?></span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Datos del Libro y Reserva -->
                  <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                      <h3 class="h6 fw-bold mb-0 text-truncate">
                        <a href="/libro/<?= (int) $res['libro_id'] ?>" class="text-decoration-none text-body">
                          <?= e($res['titulo']) ?>
                        </a>
                      </h3>
                      <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                        Activa
                      </span>
                    </div>
                    <p class="small text-muted mb-2 text-truncate"><?= e($res['autor'] ?? 'Autor desconocido') ?></p>

                    <!-- Código de Recogida y Código de Barras -->
                    <div class="p-2 px-3 bg-light rounded-3 mb-3 border d-flex justify-content-between align-items-center">
                      <div>
                        <div class="text-muted" style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Código de recogida</div>
                        <div class="font-monospace fw-800 fs-5 text-primary tracking-wide">
                          <?= e($res['codigo'] ?? '') ?>
                        </div>
                      </div>
                      <!-- Botón modal Código de Barras -->
                      <button type="button" class="btn btn-sm btn-outline-primary fw-bold" 
                              data-bs-toggle="modal" data-bs-target="#barcodeModal-<?= (int) $res['id'] ?>">
                        <i class="bi bi-upc-scan me-1"></i>Ver Código de Barras
                      </button>
                    </div>

                    <!-- Plazo restante -->
                    <div class="small mb-3 d-flex align-items-center gap-2">
                      <i class="bi bi-clock-history <?= $horasRestantes < 12 ? 'text-danger' : 'text-primary' ?>"></i>
                      <span>
                        <?php if ($segundosRestantes > 0): ?>
                          Vence en: <strong class="<?= $horasRestantes < 12 ? 'text-danger' : 'text-body' ?>"><?= $horasRestantes ?>h <?= $minutosRestantes ?>m</strong>
                          <span class="text-muted">(<?= date('d/m/Y H:i', $fechaLimiteTs) ?>)</span>
                        <?php else: ?>
                          <span class="text-danger fw-bold">Plazo vencido (en proceso de expiración)</span>
                        <?php endif; ?>
                      </span>
                    </div>

                    <!-- Botón de cancelación con Modal (Fase 18 §3.5 / T-UX-07) -->
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelarReserva-<?= (int) $res['id'] ?>">
                      <i class="bi bi-x-circle me-1"></i>Cancelar Reserva
                    </button>

                    <!-- Modal de confirmación para cancelar reserva -->
                    <div class="modal fade" id="modalCancelarReserva-<?= (int) $res['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-4 border-0 shadow text-start">
                          <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fs-6 fw-bold text-danger">
                              <i class="bi bi-exclamation-triangle me-1"></i>Cancelar Reserva
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                          </div>
                          <div class="modal-body py-3">
                            <p class="mb-1">¿Seguro que deseas cancelar tu reserva de <strong><?= e($res['titulo']) ?></strong>?</p>
                            <p class="small text-muted mb-0">El ejemplar volverá a estar disponible para otros usuarios de forma inmediata.</p>
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
              </div>
            </div>

            <!-- Modal Código de Barras para mostrar en mostrador -->
            <div class="modal fade" id="barcodeModal-<?= (int) $res['id'] ?>" tabindex="-1" aria-labelledby="barcodeModalLabel-<?= (int) $res['id'] ?>" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                  <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fs-6 fw-bold" id="barcodeModalLabel-<?= (int) $res['id'] ?>">
                      <i class="bi bi-upc-scan text-primary me-2"></i>Código de Barras de Recogida
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body text-center p-4">
                    <p class="small text-muted mb-3">Muestra este código de barras al personal en el mostrador para escanear y retirar tu libro:</p>
                    <div class="p-3 bg-white border rounded-3 d-inline-block shadow-xs mb-3 w-100 overflow-auto text-center">
                      <svg id="barcode-<?= (int) $res['id'] ?>" class="barcode-svg" style="max-width: 100%; height: 95px;"></svg>
                    </div>
                    <div class="font-monospace fs-5 fw-bold text-primary p-2 bg-light rounded-3 border mb-3">
                      <?= e($res['codigo'] ?? '') ?>
                    </div>
                    <div class="d-flex justify-content-center gap-2">
                      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="imprimirComprobanteReserva('<?= e($res['codigo']) ?>', '<?= e($res['titulo']) ?>', 'barcode-<?= (int) $res['id'] ?>');">
                        <i class="bi bi-printer me-1"></i>Imprimir comprobante
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <script>
              document.addEventListener('DOMContentLoaded', function() {
                var codeVal = '<?= addslashes((string) ($res['codigo'] ?? '')) ?>';
                if (typeof JsBarcode === 'function') {
                  try {
                    JsBarcode("#barcode-<?= (int) $res['id'] ?>", codeVal, {
                      format: "CODE128",
                      width: 2,
                      height: 60,
                      displayValue: true
                    });
                  } catch (e) {
                    console.warn("JsBarcode error:", e);
                  }
                } else if (window.BS_Barcode && typeof window.BS_Barcode.render === 'function') {
                  window.BS_Barcode.render('barcode-<?= (int) $res['id'] ?>', codeVal, {
                    altura: 70,
                    anchoEstrecho: 2,
                    anchoAncho: 5,
                    gap: 2
                  });
                }
              });
            </script>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- SECCIÓN: HISTORIAL DE RESERVAS ANTERIORES -->
  <div>
    <h2 class="h5 fw-bold mb-3">
      <i class="bi bi-clock-history me-2 text-muted"></i>Historial de Reservas Anteriores
    </h2>

    <?php if (empty($historial)): ?>
      <div class="card border-0 shadow-sm rounded-4 p-4 text-center text-muted">
        <p class="mb-0 small">Aún no tienes reservas archivadas en tu historial.</p>
      </div>
    <?php else: ?>
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase fw-bold">
              <tr>
                <th>Libro</th>
                <th>Código</th>
                <th>Fecha Reserva</th>
                <th>Estado</th>
                <th>Fecha Cierre</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($historial as $h): ?>
                <tr>
                  <td>
                    <a href="/libro/<?= (int) $h['libro_id'] ?>" class="fw-bold text-decoration-none text-body">
                      <?= e($h['titulo']) ?>
                    </a>
                    <div class="small text-muted"><?= e($h['autor'] ?? '') ?></div>
                  </td>
                  <td class="font-monospace small"><?= e($h['codigo'] ?? '—') ?></td>
                  <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                  <td>
                    <?php if ($h['estado'] === 'entregada'): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                        <i class="bi bi-check-circle me-1"></i>Entregada
                      </span>
                    <?php elseif ($h['estado'] === 'cancelada'): ?>
                      <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                        <i class="bi bi-x-circle me-1"></i>Cancelada
                      </span>
                    <?php elseif ($h['estado'] === 'expirada'): ?>
                      <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">
                        <i class="bi bi-exclamation-circle me-1"></i>Expirada
                      </span>
                    <?php else: ?>
                      <span class="badge bg-light text-body border rounded-pill"><?= e($h['estado']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="small text-muted">
                    <?= !empty($h['fecha_entrega']) ? date('d/m/Y H:i', strtotime($h['fecha_entrega'])) : '—' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Librería JsBarcode para renderizado 1D Code 128 (Fase 17 §2.1) -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="/assets/js/barcode.js"></script>
<script>
function imprimirComprobanteReserva(codigo, titulo, svgId) {
  var svgEl = document.getElementById(svgId);
  var svgHtml = svgEl ? svgEl.outerHTML : '';
  var w = window.open('', '_blank', 'width=600,height=500');
  if (!w) return;
  w.document.write('<!DOCTYPE html><html><head><title>Comprobante de Reserva - ' + codigo + '</title><style>body{font-family:sans-serif;text-align:center;padding:40px;} .box{border:2px dashed #333;border-radius:12px;padding:30px;display:inline-block;} h2{margin-bottom:5px;} .codigo{font-family:monospace;font-size:24px;font-weight:bold;margin:15px 0;} @media print{button{display:none;}}</style></head><body><div class="box"><h2>BookSwap — Reserva</h2><p>' + titulo + '</p>' + svgHtml + '<div class="codigo">' + codigo + '</div><p><small>Presenta este código de barras en el mostrador del centro.</small></p><button onclick="window.print()">Imprimir</button></div><script>setTimeout(function(){window.print();},300);<\/script></body></html>');
  w.document.close();
}
</script>
