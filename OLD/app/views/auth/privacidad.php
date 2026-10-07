<?php
/**
 * BookSwap · privacidad.php — Política de privacidad y protección de datos (v4.1).
 *
 * Cumple con los requisitos de Google OAuth y el test T-PRIV-01:
 * Presenta con claridad los datos de contacto y la información del centro físico.
 */
declare(strict_types=1);
?>

<div class="row justify-content-center my-4">
  <div class="col-lg-9">
    <div class="card p-4 p-md-5 border-0 shadow-sm rounded-4 bg-surface">
      <h1 class="h2 fw-800 mb-3">Política de Privacidad y Protección de Datos</h1>
      <p class="text-muted mb-4">Última actualización: Septiembre de 2026</p>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-2">1. Responsable del Tratamiento</h2>
        <p class="mb-2">
          El responsable del tratamiento de los datos recabados en esta plataforma es el centro ciudadano <strong><?= e($config['centro_nombre'] ?? 'LibrosBro — Biblioteca Ciudadana') ?></strong>.
        </p>
        <div class="card p-3 bg-surface-2 border-0 rounded-3 mb-3">
          <ul class="list-unstyled mb-0 small">
            <li class="mb-1"><i class="bi bi-geo-alt-fill me-2 text-primary"></i><strong>Dirección:</strong> <?= e($config['centro_direccion'] ?? 'Calle de los Libros 42, 28004 Madrid') ?></li>
            <li class="mb-1"><i class="bi bi-telephone-fill me-2 text-primary"></i><strong>Teléfono:</strong> <?= e($config['centro_telefono'] ?? '+34 910 123 456') ?></li>
            <li><i class="bi bi-envelope-fill me-2 text-primary"></i><strong>Correo de contacto:</strong> <a href="mailto:<?= e($config['centro_email'] ?? 'hola@bookswap.local') ?>"><?= e($config['centro_email'] ?? 'hola@bookswap.local') ?></a></li>
          </ul>
        </div>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-2">2. Finalidad del Tratamiento</h2>
        <p>
          Los datos personales solicitados (nombre, correo electrónico y número de socio) se utilizan exclusivamente para:
        </p>
        <ul>
          <li>Gestionar tu cuenta de lector y el saldo de tokens de intercambio.</li>
          <li>Tramitar las reservas de libros y las entregas presenciales en el mostrador.</li>
          <li>Comunicarte el estado de tus reservas y notificaciones del servicio.</li>
        </ul>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-2">3. Inicio de Sesión con Google</h2>
        <p>
          Si decides utilizar el acceso mediante Google OAuth 2.0, únicamente solicitamos acceso a tu identificador único, dirección de correo electrónico verificado, nombre y foto de perfil. No accedemos a tus contactos, documentos ni ningún otro dato personal de tu cuenta de Google.
        </p>
      </section>

      <section class="mb-4">
        <h2 class="h5 fw-bold text-primary mb-2">4. Derechos del Usuario</h2>
        <p>
          Puedes ejercer en cualquier momento tus derechos de acceso, rectificación, supresión y limitación del tratamiento contactando directamente con nuestro centro en el teléfono <strong><?= e($config['centro_telefono'] ?? '') ?></strong> o escribiendo a <strong><?= e($config['centro_email'] ?? '') ?></strong>.
        </p>
      </section>
    </div>
  </div>
</div>
