<?php
/**
 * BookSwap · Configuración del Sistema (Panel de Administración).
 *
 * Permite editar (§8.9):
 * - Economía: coste_libro, bono_deposito, bono_bienvenida.
 * - Operativa: horas_reserva, max_reservas_activas.
 * - Backups: dias_backup_auto, retencion_backups.
 * - Datos del punto físico: centro_nombre, centro_direccion, centro_telefono, centro_email, centro_horario, centro_como_llegar, centro_mapa_lat, centro_mapa_lng, centro_mapa_proveedor.
 * - Integración Google OAuth: google_client_id, google_client_secret, google_redirect_uri.
 */
declare(strict_types=1);

$config = $config ?? [];
?>

<div class="container-xxl py-4">
  <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
    <div>
      <h1 class="h3 fw-bold mb-1">
        <i class="bi bi-sliders text-primary me-2"></i>Configuración del Sistema
      </h1>
      <p class="text-muted small mb-0">Ajusta los parámetros operativos, económicos y de contacto de BookSwap.</p>
    </div>
    <a href="/admin" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Volver al Panel
    </a>
  </div>

  <form method="POST" action="/admin/configuracion">
    <?= csrf_campo() ?>

    <div class="row g-4">
      <!-- BLOQUE 1: Economía y Operativa -->
      <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-header bg-white border-bottom p-4">
            <h2 class="h5 fw-bold mb-0 text-primary">
              <i class="bi bi-coin me-2"></i>1. Parámetros Económicos y Reservas
            </h2>
          </div>
          <div class="card-body p-4">
            <div class="mb-3">
              <label class="form-label fw-bold small">Coste de recogida por libro (tokens):</label>
              <input type="number" min="0" name="coste_libro" class="form-control" value="<?= e($config['coste_libro'] ?? '1') ?>" required>
              <div class="form-text small">Tokens debitados al retirar un libro reservado en mostrador.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Bono por depósito admitido (tokens):</label>
              <input type="number" min="0" name="bono_deposito" class="form-control" value="<?= e($config['bono_deposito'] ?? '1') ?>" required>
              <div class="form-text small">Tokens acreditados al socio cuando deposita un libro admitido.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Bono de bienvenida al activar socio (tokens):</label>
              <input type="number" min="0" name="bono_bienvenida" class="form-control" value="<?= e($config['bono_bienvenida'] ?? '0') ?>" required>
              <div class="form-text small">Tokens de regalo concedidos al vincular por primera vez un número de socio.</div>
            </div>

            <hr class="my-3">

            <div class="mb-3">
              <label class="form-label fw-bold small">Horas de vigencia de cada reserva:</label>
              <input type="number" min="1" name="horas_reserva" class="form-control" value="<?= e($config['horas_reserva'] ?? '72') ?>" required>
              <div class="form-text small">Plazo máximo en horas antes de que la reserva expire y se libere la copia.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Máximo de reservas activas simultáneas por socio:</label>
              <input type="number" min="1" max="20" name="max_reservas_activas" class="form-control" value="<?= e($config['max_reservas_activas'] ?? '3') ?>" required>
              <div class="form-text small">Límite de libros que un mismo usuario puede tener reservados al mismo tiempo.</div>
            </div>
          </div>
        </div>

        <!-- BLOQUE 2: Copias de Seguridad -->
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-header bg-white border-bottom p-4">
            <h2 class="h5 fw-bold mb-0 text-primary">
              <i class="bi bi-database me-2"></i>2. Copias de Seguridad Automáticas
            </h2>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label fw-bold small">Frecuencia (días):</label>
                <input type="number" min="0" name="dias_backup_auto" class="form-control" value="<?= e($config['dias_backup_auto'] ?? '7') ?>" required>
                <div class="form-text small">0 desactiva las copias automáticas.</div>
              </div>
              <div class="col-6">
                <label class="form-label fw-bold small">Retención de copias:</label>
                <input type="number" min="1" name="retencion_backups" class="form-control" value="<?= e($config['retencion_backups'] ?? '10') ?>" required>
                <div class="form-text small">Número máximo de archivos a conservar.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BLOQUE 3: Punto Físico y Google OAuth -->
      <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-header bg-white border-bottom p-4">
            <h2 class="h5 fw-bold mb-0 text-primary">
              <i class="bi bi-geo-alt me-2"></i>3. Datos del Centro y Visítanos
            </h2>
          </div>
          <div class="card-body p-4">
            <div class="mb-3">
              <label class="form-label fw-bold small">Nombre del centro:</label>
              <input type="text" name="centro_nombre" class="form-control" value="<?= e($config['centro_nombre'] ?? 'BookSwap — Biblioteca Ciudadana') ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Dirección física completa:</label>
              <input type="text" name="centro_direccion" class="form-control" value="<?= e($config['centro_direccion'] ?? 'Calle de los Libros 42, 28004 Madrid') ?>" required>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label fw-bold small">Teléfono de atención:</label>
                <input type="text" name="centro_telefono" class="form-control" value="<?= e($config['centro_telefono'] ?? '+34 910 123 456') ?>" required>
              </div>
              <div class="col-6">
                <label class="form-label fw-bold small">Email oficial:</label>
                <input type="email" name="centro_email" class="form-control" value="<?= e($config['centro_email'] ?? 'hola@bookswap.local') ?>" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Horario semanal (JSON):</label>
              <textarea name="centro_horario" rows="3" class="form-control font-monospace small"><?= e($config['centro_horario'] ?? '') ?></textarea>
              <div class="form-text small">Formato JSON con tramos por día (lunes, martes, ..., domingo).</div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-4">
                <label class="form-label fw-bold small">Latitud GPS:</label>
                <input type="text" name="centro_mapa_lat" class="form-control font-monospace" value="<?= e($config['centro_mapa_lat'] ?? '40.4168') ?>" required>
              </div>
              <div class="col-4">
                <label class="form-label fw-bold small">Longitud GPS:</label>
                <input type="text" name="centro_mapa_lng" class="form-control font-monospace" value="<?= e($config['centro_mapa_lng'] ?? '-3.7038') ?>" required>
              </div>
              <div class="col-4">
                <label class="form-label fw-bold small">Proveedor mapa:</label>
                <select name="centro_mapa_proveedor" class="form-select">
                  <option value="google" <?= ($config['centro_mapa_proveedor'] ?? '') === 'google' ? 'selected' : '' ?>>Google Maps</option>
                  <option value="osm" <?= ($config['centro_mapa_proveedor'] ?? '') === 'osm' ? 'selected' : '' ?>>OpenStreetMap</option>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Indicaciones de transporte (Cómo llegar):</label>
              <textarea name="centro_como_llegar" rows="2" class="form-control"><?= e($config['centro_como_llegar'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <!-- BLOQUE 4: Google OAuth -->
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-header bg-white border-bottom p-4">
            <h2 class="h5 fw-bold mb-0 text-primary">
              <i class="bi bi-google me-2"></i>4. Integración con Google Sign-In
            </h2>
          </div>
          <div class="card-body p-4">
            <div class="mb-3">
              <label class="form-label fw-bold small">Google Client ID:</label>
              <input type="text" name="google_client_id" class="form-control font-monospace" value="<?= e($config['google_client_id'] ?? '') ?>">
              <div class="form-text small">Dejar en blanco para desactivar el botón de Google en el login.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Google Client Secret:</label>
              <input type="password" name="google_client_secret" class="form-control font-monospace" value="<?= e($config['google_client_secret'] ?? '') ?>">
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Redirect URI de retorno:</label>
              <input type="text" name="google_redirect_uri" class="form-control font-monospace" value="<?= e($config['google_redirect_uri'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Botón de guardado fijo/destacado -->
    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="submit" class="btn btn-primary btn-lg fw-bold px-5 shadow-sm">
        <i class="bi bi-check2-circle me-2"></i>Guardar Cambios
      </button>
    </div>
  </form>
</div>
