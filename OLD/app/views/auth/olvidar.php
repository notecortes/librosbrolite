<?php
/**
 * BookSwap · olvidar.php — Vista de solicitud de recuperación de contraseña (v4.7).
 *
 * Implementa:
 * - Solicitud de restablecimiento vía email con protección CSRF.
 * - Mensaje genérico uniforme anti-enumeración.
 * - Degradación controlada para entornos de desarrollo (muestra enlace directo en pantalla).
 */
declare(strict_types=1);
?>

<div class="row justify-content-center my-4">
  <div class="col-md-7 col-lg-5">
    <div class="card p-4 p-md-5 border-0 shadow-sm rounded-4 bg-surface">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 56px; height: 56px;">
          <i class="bi bi-key-fill fs-3"></i>
        </div>
        <h1 class="h3 fw-800 mb-1">Recuperar contraseña</h1>
        <p class="text-muted small">Introduce tu correo electrónico para restablecer el acceso a tu cuenta.</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
          <i class="bi bi-check-circle-fill me-2 fs-5"></i>
          <div><?= e($mensaje) ?></div>
        </div>
      <?php endif; ?>

      <?php if (defined('APP_ENV') && APP_ENV === 'development'): ?>
        <?php if (!empty($devResetUrl)): ?>
          <div class="alert alert-warning border-warning shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge bg-warning text-dark fw-bold">SOLO ENTORNO DE DESARROLLO</span>
            </div>
            <p class="small text-muted mb-2">
              En producción este enlace se remite vía correo electrónico. En este entorno sin servidor de correo activo, puedes continuar directamente desde aquí:
            </p>
            <div class="p-2 bg-white rounded border">
              <a href="<?= e($devResetUrl) ?>" class="fw-bold font-monospace text-break text-primary small">
                <?= e($devResetUrl) ?>
              </a>
            </div>
          </div>
        <?php elseif (!empty($devGoogle)): ?>
          <div class="alert alert-warning border-warning shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="badge bg-warning text-dark fw-bold">SOLO ENTORNO DE DESARROLLO</span>
            </div>
            <p class="small text-muted mb-0">
              La cuenta está vinculada a Google. El correo informativo remitido solicita iniciar sesión mediante el botón oficial de Google.
            </p>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Formulario de solicitud -->
      <form method="post" action="/olvidar" class="mb-3">
        <?= csrf_campo() ?>

        <div class="mb-4">
          <label for="email" class="form-label fw-semibold">Correo electrónico registrado</label>
          <div class="input-group">
            <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control border-start-0" id="email" name="email"
                   value="<?= e($email ?? '') ?>" required autofocus autocomplete="email" placeholder="tu@email.com">
          </div>
          <div class="form-text small text-muted">
            Por motivos de seguridad, si el correo existe enviaremos un enlace de un solo uso con 1 hora de validez.
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-send me-1"></i> Enviar instrucciones
        </button>
      </form>

      <div class="text-center mt-4 pt-3 border-top">
        <p class="text-muted small mb-0">
          ¿Te acuerdas de tu contraseña? <a href="/login" class="fw-semibold text-primary">Volver a iniciar sesión</a>
        </p>
      </div>
    </div>
  </div>
</div>
