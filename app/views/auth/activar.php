<?php
/**
 * BookSwap · activar.php — Activación de cuenta mediante número de socio (v4.1).
 */
declare(strict_types=1);
?>

<div class="row justify-content-center my-4">
  <div class="col-md-7 col-lg-5">
    <div class="card p-4 p-md-5 border-0 shadow-sm rounded-4 bg-surface">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle mb-3" style="width: 56px; height: 56px;">
          <i class="bi bi-person-vcard fs-3 text-warning-emphasis"></i>
        </div>
        <h1 class="h3 fw-800 mb-1">Activa tu cuenta</h1>
        <p class="text-muted small">
          Para realizar reservas e intercambiar libros en LibrosBro necesitas vincular tu número de socio oficial.
        </p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <div class="alert alert-info py-2 px-3 small mb-4">
        <i class="bi bi-info-circle me-1"></i> El personal te entrega tu tarjeta física con tu número de socio (ej. <strong>SOC-0003</strong>). Si no lo tienes, acércate a nuestro mostrador.
      </div>

      <form method="post" action="/activar" class="mb-3">
        <?= csrf_campo() ?>

        <div class="mb-4">
          <label for="numero" class="form-label fw-semibold">Número de socio</label>
          <div class="input-group">
            <span class="input-group-text bg-surface-2 border-end-0 text-muted"><i class="bi bi-upc"></i></span>
            <input type="text" class="form-control border-start-0 text-uppercase fw-bold" id="numero" name="numero"
                   value="<?= e($numero ?? '') ?>" required autofocus placeholder="SOC-XXXX" style="letter-spacing: 1px;">
          </div>
          <div class="form-text small">Introduce el código tal como figura en tu tarjeta de socio.</div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-check-circle me-1"></i> Activar mi cuenta
        </button>
      </form>

      <div class="text-center mt-4 pt-3 border-top">
        <form method="post" action="/logout">
          <?= csrf_campo() ?>
          <button type="submit" class="btn btn-link text-muted small text-decoration-none">
            <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
