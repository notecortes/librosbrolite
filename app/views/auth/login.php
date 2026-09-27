<?php
/**
 * BookSwap · login.php — Vista de inicio de sesión (v4.1).
 *
 * Incluye:
 * - Formulario local con protección contra fuerza bruta y CSRF.
 * - Botón de inicio de sesión con Google (se renderiza ÚNICAMENTE si está configurado).
 * - Enlace a registro y recuperación.
 */
declare(strict_types=1);

$googleHabilitado = !empty(trim((string) ($config['google_client_id'] ?? '')));
?>

<div class="row justify-content-center my-4">
  <div class="col-md-7 col-lg-5">
    <div class="card p-4 p-md-5 border-0 shadow-sm rounded-4 bg-surface">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 56px; height: 56px;">
          <i class="bi bi-box-arrow-in-right fs-3"></i>
        </div>
        <h1 class="h3 fw-800 mb-1">Iniciar sesión</h1>
        <p class="text-muted small">Accede a tu cuenta de LibrosBro para gestionar tus libros y reservas.</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <!-- Formulario local -->
      <form method="post" action="/login" class="mb-3">
        <?= csrf_campo() ?>

        <div class="mb-3">
          <label for="email" class="form-label fw-semibold">Correo electrónico</label>
          <div class="input-group">
            <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control border-start-0" id="email" name="email"
                   value="<?= e($email ?? '') ?>" required autofocus autocomplete="email" placeholder="tu@email.com">
          </div>
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="password" class="form-label fw-semibold mb-0">Contraseña</label>
            <a href="/olvidar" class="small text-decoration-none">¿Olvidaste tu contraseña?</a>
          </div>
          <div class="input-group">
            <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control border-start-0" id="password" name="password"
                   required autocomplete="current-password" placeholder="Tu contraseña">
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-1"></i> Entrar a LibrosBro
        </button>
      </form>

      <?php if ($googleHabilitado): ?>
        <div class="d-flex align-items-center my-3">
          <hr class="flex-grow-1">
          <span class="px-3 text-muted small fw-semibold">o también</span>
          <hr class="flex-grow-1">
        </div>

        <a href="/auth/google" class="btn btn-soft w-100 py-2 d-flex align-items-center justify-content-center gap-2 fw-semibold">
          <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
          Continuar con Google
        </a>
      <?php endif; ?>

      <div class="text-center mt-4 pt-3 border-top">
        <p class="text-muted small mb-0">
          ¿Aún no tienes cuenta? <a href="/registro" class="fw-semibold text-primary">Regístrate gratis</a>
        </p>
      </div>
    </div>
  </div>
</div>
