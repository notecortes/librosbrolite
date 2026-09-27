<?php
/**
 * LibrosBro · cambiar_password.php — Vista para cambiar la contraseña de usuario autenticado.
 *
 * Características:
 * - Compatible con cuentas locales (solicita contraseña actual) y Google (establece contraseña local inicial).
 * - Ocultar/mostrar contraseña con visor de ojo interactivo.
 * - Validación de política de seguridad (mínimo 6 caracteres).
 * - Protección CSRF obligatoria.
 */
declare(strict_types=1);
?>

<div class="row justify-content-center my-4">
  <div class="col-md-8 col-lg-6 col-xl-5">

    <!-- Navegación de retorno -->
    <div class="mb-3">
      <a href="/dashboard" class="btn btn-sm btn-outline-secondary rounded-pill">
        <i class="bi bi-arrow-left me-1"></i>Volver a mi panel
      </a>
    </div>

    <div class="card p-4 p-md-5 border-0 shadow-sm rounded-4 bg-surface">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 56px; height: 56px;">
          <i class="bi bi-key-fill fs-3"></i>
        </div>
        <h1 class="h3 fw-800 mb-1">Cambiar Contraseña</h1>
        <p class="text-muted small mb-2">Actualiza tu clave de acceso personal para tu cuenta de LibrosBro.</p>
        <span class="badge bg-light text-dark border font-monospace small px-3 py-1">
          <i class="bi bi-person-circle me-1 text-primary"></i><?= e($usuario['email'] ?? '') ?>
        </span>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($exito)): ?>
        <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
          <i class="bi bi-check-circle-fill me-2 fs-5 flex-shrink-0"></i>
          <div><?= e($exito) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!$tienePassword): ?>
        <div class="alert alert-info border-0 rounded-3 p-3 small mb-4">
          <i class="bi bi-google me-1 text-primary"></i>
          <strong>Cuenta vinculada a Google:</strong> Actualmente accedes mediante Google. Al definir una contraseña aquí, podrás acceder también mediante tu correo electrónico y esta contraseña directamente.
        </div>
      <?php endif; ?>

      <form method="post" action="/cambiar-password" class="mb-3" id="formCambiarPassword">
        <?= csrf_campo() ?>

        <?php if ($tienePassword): ?>
          <div class="mb-3">
            <label for="password_actual" class="form-label fw-semibold small">Contraseña actual <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
              <input type="password" class="form-control border-start-0 border-end-0" id="password_actual" name="password_actual"
                     required autocomplete="current-password" placeholder="Tu contraseña actual">
              <button class="btn btn-outline-secondary border-start-0 text-muted" type="button" onclick="toggleVerPassword('password_actual', this)" title="Mostrar/ocultar">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="form-text extra-small text-muted">Introduce la clave con la que iniciaste sesión.</div>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label for="password_nueva" class="form-label fw-semibold small">Nueva contraseña <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control border-start-0 border-end-0" id="password_nueva" name="password_nueva"
                   required minlength="6" autocomplete="new-password" placeholder="Mínimo 6 caracteres">
            <button class="btn btn-outline-secondary border-start-0 text-muted" type="button" onclick="toggleVerPassword('password_nueva', this)" title="Mostrar/ocultar">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="form-text extra-small text-muted">Debe contener un mínimo de 6 caracteres.</div>
        </div>

        <div class="mb-4">
          <label for="password_confirm" class="form-label fw-semibold small">Confirmar nueva contraseña <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-lock-fill"></i></span>
            <input type="password" class="form-control border-start-0 border-end-0" id="password_confirm" name="password_confirm"
                   required minlength="6" autocomplete="new-password" placeholder="Repite la nueva contraseña">
            <button class="btn btn-outline-secondary border-start-0 text-muted" type="button" onclick="toggleVerPassword('password_confirm', this)" title="Mostrar/ocultar">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary py-2 fw-semibold shadow-sm">
            <i class="bi bi-check-circle me-1"></i>Actualizar Contraseña
          </button>
          <a href="/dashboard" class="btn btn-outline-secondary py-2">
            Cancelar
          </a>
        </div>
      </form>

      <!-- Consejos de seguridad -->
      <div class="border-top pt-3 mt-2">
        <div class="d-flex align-items-start gap-2 text-muted small">
          <i class="bi bi-shield-check text-success fs-5"></i>
          <div>
            <span class="fw-semibold text-dark">Consejos de seguridad:</span>
            <ul class="mb-0 ps-3 extra-small mt-1 text-muted" style="font-size: 0.8rem;">
              <li>Utiliza al menos 6 caracteres combinando letras y números.</li>
              <li>No utilices la misma contraseña en diferentes plataformas.</li>
              <li>Tu sesión se mantendrá iniciada de forma protegida tras el cambio.</li>
            </ul>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function toggleVerPassword(inputId, btnEl) {
  var input = document.getElementById(inputId);
  if (!input) return;
  var esPassword = input.type === 'password';
  input.type = esPassword ? 'text' : 'password';
  var icono = btnEl.querySelector('i');
  if (icono) {
    icono.className = esPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
  }
}
</script>
