<?php
/**
 * BookSwap · reset.php — Vista de restablecimiento de contraseña mediante token (v4.7).
 *
 * Incluye:
 * - Verificación visual del estado del token (válido, expirado o ya usado).
 * - Formulario con validación de política de contraseñas (mínimo 8 caracteres).
 * - Protección CSRF obligatoria.
 */
declare(strict_types=1);
?>

<div class="row justify-content-center my-4">
  <div class="col-md-7 col-lg-5">
    <div class="card p-4 p-md-5 border-0 shadow-sm rounded-4 bg-surface">

      <?php if (!empty($valido)): ?>
        <div class="text-center mb-4">
          <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 56px; height: 56px;">
            <i class="bi bi-shield-lock-fill fs-3"></i>
          </div>
          <h1 class="h3 fw-800 mb-1">Nueva contraseña</h1>
          <p class="text-muted small">Crea una nueva contraseña segura para acceder a BookSwap.</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= e($error) ?></div>
          </div>
        <?php endif; ?>

        <form method="post" action="/reset/<?= e($token) ?>" class="mb-3">
          <?= csrf_campo() ?>

          <div class="mb-3">
            <label for="password" class="form-label fw-semibold">Nueva contraseña</label>
            <div class="input-group">
              <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-lock"></i></span>
              <input type="password" class="form-control border-start-0" id="password" name="password"
                     required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
            </div>
            <div class="form-text small text-muted">
              Debe contener al menos 8 caracteres.
            </div>
          </div>

          <div class="mb-4">
            <label for="password_confirm" class="form-label fw-semibold">Confirmar nueva contraseña</label>
            <div class="input-group">
              <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-lock-fill"></i></span>
              <input type="password" class="form-control border-start-0" id="password_confirm" name="password_confirm"
                     required minlength="8" autocomplete="new-password" placeholder="Repite la contraseña">
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
            <i class="bi bi-check-lg me-1"></i> Guardar nueva contraseña
          </button>
        </form>

      <?php else: ?>
        <div class="text-center py-3">
          <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle mb-3" style="width: 64px; height: 64px;">
            <i class="bi bi-exclamation-triangle-fill fs-2"></i>
          </div>
          <h1 class="h4 fw-bold mb-2">El enlace no es válido o ha caducado</h1>
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger mb-3"><?= e($error) ?></div>
          <?php endif; ?>
          <p class="text-muted small mb-4">
            El enlace de recuperación no es válido, ya ha sido utilizado para restablecer la contraseña, o ha superado el tiempo máximo de validez (1 hora).
          </p>

          <div class="d-grid gap-2">
            <a href="/olvidar" class="btn btn-primary fw-semibold">
              <i class="bi bi-arrow-repeat me-1"></i> Solicitar un nuevo enlace
            </a>
            <a href="/login" class="btn btn-outline-secondary">
              Volver a iniciar sesión
            </a>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>
