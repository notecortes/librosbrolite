<?php
/**
 * BookSwap · admin/reservas/index.php — Gestión administrativa de reservas (v4.2).
 *
 * Funcionalidades:
 * - Listado de todas las transacciones de reserva con filtros por estado, usuario y fechas.
 * - Cancelación de reserva activa (libera ejemplar a 'disponible', notifica al lector y audita).
 * - Botón "Ejecutar expiración ahora" (ejecuta reserva_expirar_vencidas y audita).
 */
declare(strict_types=1);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="/admin">Administración</a></li>
        <li class="breadcrumb-item active" aria-current="page">Reservas</li>
      </ol>
    </nav>
    <h1 class="h2 fw-800 mb-1">Gestión de Reservas</h1>
    <p class="text-muted mb-0">Control global de reservas, estados de recogida, cancelaciones y expiración.</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaReserva">
      <i class="bi bi-bookmark-plus me-1"></i>+ Nueva Reserva
    </button>
    <form method="POST" action="/admin/reservas/expirar-ahora" class="d-inline" onsubmit="return confirm('¿Ejecutar la comprobación inmediata de reservas expiradas? Las que hayan superado el plazo serán canceladas y sus copias liberadas.');">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
      <button type="submit" class="btn btn-outline-warning text-dark fw-bold shadow-sm">
        <i class="bi bi-clock-history me-1"></i>Ejecutar Expiración Ahora
      </button>
    </form>
  </div>
</div>

<!-- Filtros de búsqueda -->
<div class="card border-0 shadow-sm rounded-4 bg-surface p-3 mb-4">
  <form method="GET" action="/admin/reservas" class="row g-2 align-items-center">
    <div class="col-md-4">
      <div class="input-group">
        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control border-start-0" placeholder="Código, usuario o título..." value="<?= htmlspecialchars($filtros['q'] ?? '') ?>">
      </div>
    </div>
    <div class="col-6 col-md-2">
      <select name="estado" class="form-select">
        <option value="">Todos los estados</option>
        <option value="activa" <?= ($filtros['estado'] ?? '') === 'activa' ? 'selected' : '' ?>>Activas</option>
        <option value="entregada" <?= ($filtros['estado'] ?? '') === 'entregada' ? 'selected' : '' ?>>Entregadas</option>
        <option value="expirada" <?= ($filtros['estado'] ?? '') === 'expirada' ? 'selected' : '' ?>>Expiradas</option>
        <option value="cancelada" <?= ($filtros['estado'] ?? '') === 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <input type="date" name="desde" class="form-control" title="Desde" value="<?= htmlspecialchars($filtros['desde'] ?? '') ?>" placeholder="Desde">
    </div>
    <div class="col-6 col-md-2">
      <input type="date" name="hasta" class="form-control" title="Hasta" value="<?= htmlspecialchars($filtros['hasta'] ?? '') ?>" placeholder="Hasta">
    </div>
    <div class="col-6 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-primary fw-semibold flex-grow-1">Filtrar</button>
      <a href="/admin/reservas" class="btn btn-outline-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div>

<!-- Tabla de reservas -->
<div class="card border-0 shadow-sm rounded-4 bg-surface overflow-hidden mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Código / Fecha</th>
          <th>Libro Reservado</th>
          <th>Lector</th>
          <th>Estado</th>
          <th>Límite de Recogida</th>
          <th class="text-end pe-4">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($reservas)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-bookmark-x fs-1 d-block mb-2"></i>
              No se encontraron reservas con los filtros aplicados.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($reservas as $r): ?>
            <tr>
              <td class="ps-4">
                <div class="fw-bold font-monospace text-primary"><?= htmlspecialchars($r['codigo'] ?? 'S/C') ?></div>
                <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></div>
              </td>
              <td>
                <div class="fw-bold text-body">
                  <a href="/libro/<?= $r['libro_id'] ?>" class="text-decoration-none text-body" target="_blank">
                    <?= htmlspecialchars($r['libro_titulo'] ?? 'Desconocido') ?>
                  </a>
                </div>
                <div class="small text-muted">
                  <?= htmlspecialchars($r['libro_autor'] ?? '') ?> · Ejemplar #<?= $r['ejemplar_id'] ?> (<?= htmlspecialchars($r['ubicacion'] ?? '') ?>)
                </div>
              </td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($r['usuario_nombre'] ?? '') ?></div>
                <div class="d-flex align-items-center gap-1 mt-1">
                  <span class="small text-muted"><?= htmlspecialchars($r['usuario_email'] ?? '') ?></span>
                </div>
              </td>
              <td>
                <?php if ($r['estado'] === 'activa'): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                    <i class="bi bi-clock me-1"></i>Activa
                  </span>
                <?php elseif ($r['estado'] === 'entregada'): ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-check-circle me-1"></i>Entregada
                  </span>
                <?php elseif ($r['estado'] === 'expirada'): ?>
                  <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                    <i class="bi bi-hourglass-bottom me-1"></i>Expirada
                  </span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                    <i class="bi bi-x-circle me-1"></i>Cancelada
                  </span>
                <?php endif; ?>
              </td>
              <td class="small">
                <?php if (!empty($r['fecha_limite'])): ?>
                  <?php $vencida = strtotime($r['fecha_limite']) < time() && $r['estado'] === 'activa'; ?>
                  <span class="<?= $vencida ? 'text-danger fw-bold' : 'text-muted' ?>">
                    <?= date('d/m/Y H:i', strtotime($r['fecha_limite'])) ?>
                    <?php if ($vencida): ?>
                      <span class="badge bg-danger ms-1">Vencida</span>
                    <?php endif; ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-end pe-4">
                <?php if ($r['estado'] === 'activa'): ?>
                  <form method="POST" action="/admin/reservas/cancelar" onsubmit="return confirm('¿Cancelar esta reserva activa? La copia física volverá a estar disponible inmediatamente y se notificará al lector.');" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="transaccion_id" value="<?= $r['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar Reserva">
                      <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <?php if ($totalPaginas > 1): ?>
    <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
      <div class="small text-muted">
        Mostrando página <?= $pagina ?> de <?= $totalPaginas ?> (total: <?= $total ?> reservas)
      </div>
      <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
          <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
            <a class="page-link" href="/admin/reservas?pagina=<?= $p ?>&q=<?= urlencode($filtros['q'] ?? '') ?>&estado=<?= urlencode($filtros['estado'] ?? '') ?>&desde=<?= urlencode($filtros['desde'] ?? '') ?>&hasta=<?= urlencode($filtros['hasta'] ?? '') ?>">
              <?= $p ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<!-- Modal Nueva Reserva -->
<div class="modal fade" id="modalNuevaReserva" tabindex="-1" aria-labelledby="modalNuevaReservaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalNuevaReservaLabel">
          <i class="bi bi-bookmark-plus text-primary me-2"></i>Nueva Reserva
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST" action="/admin/reservas/crear">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <input type="hidden" name="retorno" value="/admin/reservas">
        <div class="modal-body pt-3">
          <p class="text-muted small mb-3">
            Como administrador o personal de la biblioteca, puedes formalizar una reserva directa para cualquier lector utilizando su correo electrónico o ID.
          </p>
          <div class="mb-3">
            <label for="inputLector" class="form-label fw-semibold small">Email o ID del Lector:</label>
            <input type="text" name="lector" id="inputLector" class="form-control font-monospace" placeholder="Ej: usuario@bookswap.local o ID" required autofocus>
            <div class="form-text small">Indica el correo electrónico o ID del usuario en el centro.</div>
          </div>
          <div class="mb-3">
            <label for="selectLibro" class="form-label fw-semibold small">Libro a Reservar (con ejemplares disponibles):</label>
            <select name="libro_id" id="selectLibro" class="form-select" required>
              <option value="">-- Seleccionar libro --</option>
              <?php foreach ($librosDisponibles as $ld): ?>
                <option value="<?= (int) $ld['id'] ?>">
                  <?= htmlspecialchars($ld['titulo']) ?> (<?= htmlspecialchars($ld['autor']) ?>) · <?= (int) $ld['copias_disponibles'] ?> disp.
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary fw-bold">
            <i class="bi bi-check2-circle me-1"></i>Confirmar Reserva
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
