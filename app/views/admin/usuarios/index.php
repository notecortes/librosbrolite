<?php
/**
 * BookSwap · admin/usuarios/index.php — Gestión administrativa de usuarios (v4.2).
 *
 * Funcionalidades:
 * - Búsqueda en vivo y filtrado por rol y estado (activo/inactivo).
 * - Alta de usuario (rol configurable).
 * - Modificación de nombre y rol.
 * - Activación / Desactivación de cuenta.
 * - Restablecimiento de contraseña temporal (10 caracteres, visible una sola vez, auditado).
 * - Ajuste manual de tokens (cantidad con signo + motivo obligatorio).
  * - Enlace directo al historial completo (/admin/usuarios/{id}/historial).
 */
declare(strict_types=1);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="/admin">Administración</a></li>
        <li class="breadcrumb-item active" aria-current="page">Usuarios</li>
      </ol>
    </nav>
    <h1 class="h2 fw-800 mb-1">Gestión de Usuarios</h1>
    <p class="text-muted mb-0">Control de cuentas, roles, saldos de tokens y credenciales de acceso.</p>
  </div>
  <div>
    <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
      <i class="bi bi-person-plus-fill me-1"></i>Nuevo Usuario
    </button>
  </div>
</div>

<!-- Tarjeta de Contraseña Temporal (si se acaba de restablecer) -->
<?php if (!empty($tempPasswordInfo)): ?>
  <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
    <div class="d-flex align-items-start gap-3">
      <div class="p-2 bg-warning text-dark rounded-circle"><i class="bi bi-key-fill fs-4"></i></div>
      <div class="flex-grow-1">
        <h2 class="h5 fw-bold text-dark mb-1">Contraseña Temporal Generada</h2>
        <p class="text-dark small mb-2">
          Se ha restablecido la contraseña para el usuario <strong><?= htmlspecialchars($tempPasswordInfo['email']) ?></strong>.
          Esta contraseña solo se muestra en esta ocasión:
        </p>
        <div class="d-flex align-items-center gap-2">
          <code class="fs-4 fw-bold bg-white text-dark px-3 py-1 rounded border border-warning">
            <?= htmlspecialchars($tempPasswordInfo['password']) ?>
          </code>
          <button class="btn btn-sm btn-outline-dark" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($tempPasswordInfo['password']) ?>'); this.innerText='¡Copiada!';">
            <i class="bi bi-clipboard me-1"></i>Copiar
          </button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Tarjeta de Enlace de Acceso / Reset (Fase 22) -->
<?php if (!empty($resetEnlaceInfo)): ?>
  <div class="alert alert-primary border-0 shadow-sm rounded-4 p-4 mb-4">
    <div class="d-flex align-items-start gap-3">
      <div class="p-2 bg-primary text-white rounded-circle"><i class="bi bi-key-fill fs-4"></i></div>
      <div class="flex-grow-1">
        <h2 class="h5 fw-bold text-dark mb-1">🔑 Enlace de Acceso Generado</h2>
        <p class="text-dark small mb-2">
          Se ha generado un enlace de acceso presencial para <strong><?= htmlspecialchars($resetEnlaceInfo['email']) ?></strong>.
          Este enlace solo se muestra en esta ocasión, caduca en <strong><?= (int) $resetEnlaceInfo['expira_horas'] ?> hora(s)</strong> y es de un solo uso:
        </p>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
          <input type="text" readonly class="form-control font-monospace form-control-sm w-auto flex-grow-1" id="enlaceResetInput" value="<?= htmlspecialchars($resetEnlaceInfo['enlace']) ?>">
          <button type="button" class="btn btn-sm btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('enlaceResetInput').value); this.innerText='¡Copiado!';">
            <i class="bi bi-clipboard me-1"></i>Copiar Enlace
          </button>
        </div>
        <div class="small text-muted">
          <i class="bi bi-shield-exclamation me-1"></i>Entrégaselo en persona al usuario para que configure su nueva contraseña de acceso.
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Barra de filtros y búsqueda -->
<div class="card border-0 shadow-sm rounded-4 bg-surface p-3 mb-4">
  <form method="GET" action="/admin/usuarios" class="row g-2 align-items-center">
    <div class="col-md-5">
      <div class="input-group">
        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control border-start-0" placeholder="Buscar por nombre o email..." value="<?= htmlspecialchars($filtros['q'] ?? '') ?>">
      </div>
    </div>
    <div class="col-6 col-md-3">
      <select name="rol_id" class="form-select">
        <option value="">Todos los roles</option>
        <option value="1" <?= ($filtros['rol_id'] ?? '') === '1' ? 'selected' : '' ?>>Administrador</option>
        <option value="2" <?= ($filtros['rol_id'] ?? '') === '2' ? 'selected' : '' ?>>Personal</option>
        <option value="3" <?= ($filtros['rol_id'] ?? '') === '3' ? 'selected' : '' ?>>Lector (Usuario)</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select name="estado" class="form-select">
        <option value="">Cualquier estado</option>
        <option value="activo" <?= ($filtros['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Solo Activos</option>
        <option value="inactivo" <?= ($filtros['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Solo Inactivos</option>
      </select>
    </div>
    <div class="col-12 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-primary fw-semibold flex-grow-1">Filtrar</button>
      <a href="/admin/usuarios" class="btn btn-outline-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div>

<!-- Tabla de usuarios -->
<div class="card border-0 shadow-sm rounded-4 bg-surface overflow-hidden mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Usuario</th>
          <th>Rol</th>
                    <th>Saldo Tokens</th>
          <th>Estado</th>
          <th>Registro</th>
          <th class="text-end pe-4">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($usuarios)): ?>
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="bi bi-people fs-1 d-block mb-2"></i>
              No se encontraron usuarios con los criterios especificados.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td class="ps-4">
                <div class="fw-bold text-body"><?= htmlspecialchars($u['nombre']) ?></div>
                <div class="small text-muted"><?= htmlspecialchars($u['email']) ?></div>
              </td>
              <td>
                <?php if ($u['rol_id'] == 1): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle">ADMIN</span>
                <?php elseif ($u['rol_id'] == 2): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle">PERSONAL</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">USUARIO</span>
                <?php endif; ?>
              </td>

              <td>
                <span class="fw-bold fs-6 <?= (int) $u['saldo'] > 0 ? 'text-success' : 'text-muted' ?>">
                  <i class="bi bi-coin text-warning me-1"></i><?= (int) $u['saldo'] ?>
                </span>
              </td>
              <td>
                <?php if ($u['activo']): ?>
                  <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Activo</span>
                <?php else: ?>
                  <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-circle me-1"></i>Inactivo</span>
                <?php endif; ?>
              </td>
              <td class="text-muted small">
                <?= !empty($u['fecha_registro']) ? date('d/m/Y', strtotime($u['fecha_registro'])) : '—' ?>
              </td>
              <td class="text-end pe-4">
                <div class="btn-group">
                  <!-- Ver historial -->
                  <a href="/admin/usuarios/<?= $u['id'] ?>/historial" class="btn btn-sm btn-outline-secondary" title="Ver Historial">
                    <i class="bi bi-clock-history"></i>
                  </a>

                  <!-- Ajustar tokens -->
                  <button type="button" class="btn btn-sm btn-outline-warning text-dark" title="Ajuste Manual de Tokens"
                          data-bs-toggle="modal" data-bs-target="#modalAjusteTokens"
                          data-usuario-id="<?= $u['id'] ?>" data-usuario-nombre="<?= htmlspecialchars($u['nombre']) ?>" data-saldo="<?= (int) $u['saldo'] ?>">
                    <i class="bi bi-coin"></i>
                  </button>

                  <!-- Editar usuario -->
                  <button type="button" class="btn btn-sm btn-outline-primary" title="Editar Nombre y Rol"
                          data-bs-toggle="modal" data-bs-target="#modalEditarUsuario"
                          data-usuario-id="<?= $u['id'] ?>" data-nombre="<?= htmlspecialchars($u['nombre']) ?>" data-rol-id="<?= $u['rol_id'] ?>">
                    <i class="bi bi-pencil"></i>
                  </button>

                  <!-- Menú de más opciones -->
                  <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Más acciones</span>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <!-- Reset / Generar enlace de acceso (solo cuentas no-Google) -->
                    <?php if (!empty($u['password_hash'])): ?>
                      <li>
                        <form method="POST" action="/admin/usuarios/reset-enlace" onsubmit="return confirm('¿Generar un enlace presencial de acceso para este usuario?');">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                          <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                          <button type="submit" class="dropdown-item text-primary">
                            <i class="bi bi-key me-2"></i>🔑 Generar enlace de acceso
                          </button>
                        </form>
                      </li>
                    <?php endif; ?>

                    <!-- Activar / Desactivar -->
                    <li>
                      <form method="POST" action="/admin/usuarios/cambiar-estado">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="activo" value="<?= $u['activo'] ? '0' : '1' ?>">
                        <button type="submit" class="dropdown-item <?= $u['activo'] ? 'text-danger' : 'text-success' ?>">
                          <i class="bi <?= $u['activo'] ? 'bi-person-slash' : 'bi-person-check' ?> me-2"></i>
                          <?= $u['activo'] ? 'Desactivar Cuenta' : 'Activar Cuenta' ?>
                        </button>
                      </form>
                    </li>
                  </ul>
                </div>
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
        Mostrando página <?= $pagina ?> de <?= $totalPaginas ?> (total: <?= $total ?> usuarios)
      </div>
      <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
          <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
            <a class="page-link" href="/admin/usuarios?pagina=<?= $p ?>&q=<?= urlencode($filtros['q'] ?? '') ?>&rol_id=<?= urlencode((string)($filtros['rol_id'] ?? '')) ?>&estado=<?= urlencode($filtros['estado'] ?? '') ?>">
              <?= $p ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<!-- Modal: Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/admin/usuarios/crear">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <div class="modal-header border-0 pb-0">
          <h2 class="h5 fw-bold modal-title" id="modalCrearUsuarioLabel">Alta de Nuevo Usuario</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Nombre Completo</label>
            <input type="text" name="nombre" class="form-control" required placeholder="Ej: María García">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Correo Electrónico</label>
            <input type="email" name="email" class="form-control" required placeholder="usuario@ejemplo.com">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Contraseña Inicial</label>
            <input type="password" name="password" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Rol en el Sistema</label>
            <select name="rol_id" id="crearRolId" class="form-select">
              <option value="3" selected>Lector (Usuario estándar)</option>
              <option value="2">Personal de Biblioteca</option>
              <option value="1">Administrador</option>
            </select>
          </div>

        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary fw-semibold">Crear Usuario</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/admin/usuarios/editar">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <input type="hidden" name="usuario_id" id="editUsuarioId">
        <div class="modal-header border-0 pb-0">
          <h2 class="h5 fw-bold modal-title">Editar Usuario</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Nombre Completo</label>
            <input type="text" name="nombre" id="editNombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Rol</label>
            <select name="rol_id" id="editRolId" class="form-select">
              <option value="1">Administrador</option>
              <option value="2">Personal</option>
              <option value="3">Lector (Usuario)</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary fw-semibold">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Ajuste Manual de Tokens -->
<div class="modal fade" id="modalAjusteTokens" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/admin/usuarios/ajuste-tokens">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <input type="hidden" name="usuario_id" id="ajusteUsuarioId">
        <div class="modal-header border-0 pb-0">
          <h2 class="h5 fw-bold modal-title">Ajuste Manual de Tokens</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="p-3 bg-light rounded-3 mb-3">
            <div class="small text-muted">Usuario: <strong id="ajusteUsuarioNombre" class="text-body"></strong></div>
            <div class="small text-muted">Saldo Actual: <strong id="ajusteSaldoActual" class="text-primary fs-6"></strong> tokens</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Cantidad a Ajustar</label>
            <input type="number" name="cantidad" class="form-control" required placeholder="Ej: 3 (sumar) o -2 (restar)">
            <div class="form-text small">Usa valores positivos para añadir tokens o negativos para restar. El saldo nunca puede quedar por debajo de 0.</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Motivo del Ajuste (Obligatorio)</label>
            <textarea name="motivo" class="form-control" rows="2" required placeholder="Indica el motivo que quedará registrado en el ledger y auditoría..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning fw-semibold text-dark">Aplicar Ajuste</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>


document.addEventListener('DOMContentLoaded', function() {
  var modalEditar = document.getElementById('modalEditarUsuario');
  if (modalEditar) {
    modalEditar.addEventListener('show.bs.modal', function(event) {
      var button = event.relatedTarget;
      document.getElementById('editUsuarioId').value = button.getAttribute('data-usuario-id');
      document.getElementById('editNombre').value = button.getAttribute('data-nombre');
      document.getElementById('editRolId').value = button.getAttribute('data-rol-id');
    });
  }

  var modalAjuste = document.getElementById('modalAjusteTokens');
  if (modalAjuste) {
    modalAjuste.addEventListener('show.bs.modal', function(event) {
      var button = event.relatedTarget;
      document.getElementById('ajusteUsuarioId').value = button.getAttribute('data-usuario-id');
      document.getElementById('ajusteUsuarioNombre').innerText = button.getAttribute('data-usuario-nombre');
      document.getElementById('ajusteSaldoActual').innerText = button.getAttribute('data-saldo');
    });
  }
});
</script>
