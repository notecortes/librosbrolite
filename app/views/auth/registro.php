<?php
/**
 * BookSwap · registro.php — Vista de registro de nuevos usuarios (v4.1).
 */
declare(strict_types=1);
?>

<div class="row justify-content-center my-4">
  <div class="col-md-7 col-lg-5">
    <div class="card p-4 p-md-5 border-0 shadow-sm rounded-4 bg-surface">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 56px; height: 56px;">
          <i class="bi bi-person-plus fs-3"></i>
        </div>
        <h1 class="h3 fw-800 mb-1">Crea tu cuenta</h1>
        <p class="text-muted small">Únete a la red ciudadana de intercambio de libros.</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" action="/registro" class="mb-3">
        <?= csrf_campo() ?>

        <div class="mb-3">
          <label for="nombre" class="form-label fw-semibold">Nombre y apellidos</label>
          <div class="input-group">
            <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control border-start-0" id="nombre" name="nombre"
                   value="<?= e($nombre ?? '') ?>" required autofocus autocomplete="name" placeholder="Tu nombre">
          </div>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label fw-semibold">Correo electrónico</label>
          <div class="input-group">
            <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control border-start-0" id="email" name="email"
                   value="<?= e($email ?? '') ?>" required autocomplete="email" placeholder="tu@email.com">
          </div>
        </div>


        <div class="mb-4">
          <label for="password" class="form-label fw-semibold">Contraseña</label>
          <div class="input-group">
            <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control border-start-0" id="password" name="password"
                   required minlength="6" autocomplete="new-password" placeholder="Mínimo 6 caracteres">
          </div>
          <div class="form-text small">Debe tener al menos 6 caracteres.</div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-check2-circle me-1"></i> Completar registro
        </button>
      </form>

      <div class="text-center mt-4 pt-3 border-top">
        <p class="text-muted small mb-0">
          ¿Ya tienes cuenta? <a href="/login" class="fw-semibold text-primary">Inicia sesión</a>
        </p>
      </div>
    </div>
  </div>
</div>
