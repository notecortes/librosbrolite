<?php
/**
 * BookSwap · home.php — Vista de la página principal (v4.1).
 *
 * Muestra:
 * - Sección Hero con contadores interactivos en tiempo real.
 * - Cómo funciona en 3 sencillos pasos (modelo presencial con tokens).
 * - Muestra de libros disponibles en el catálogo cerrado con coste uniforme.
 * - Acceso a catálogo, registro y punto físico (Visítanos).
 */
declare(strict_types=1);

$costeLibro = (int) ($config['coste_libro'] ?? 1);
$bonoDeposito = (int) ($config['bono_deposito'] ?? 1);
?>

<!-- 1. Hero principal con llamada a la acción y contadores -->
<section class="hero p-4 p-md-5 mb-5 rounded-4 shadow-sm">
  <div class="row align-items-center g-4">
    <div class="col-lg-7">
      <span class="badge bg-primary-subtle text-primary mb-3 px-3 py-2 rounded-pill fw-semibold">
        <i class="bi bi-geo-alt-fill me-1"></i> Punto de intercambio ciudadano
      </span>
      <h1 class="hero-titulo mb-3 fw-800 display-5">
        Comparte libros.<br>Gana <span class="text-primary">tokens</span>. Repite.
      </h1>
      <p class="lead text-muted mb-4">
        Trae tus libros leídos a nuestro punto físico, gana tokens por cada entrega y llévate nuevas lecturas. Todos los libros tienen un coste uniforme de <strong>🪙 <?= $costeLibro ?></strong> y los tokens <strong>nunca caducan</strong>.
      </p>
      <div class="d-flex flex-wrap gap-3">
        <?php if ($usuario === null): ?>
          <a class="btn btn-primary btn-lg px-4" href="/registro">
            <i class="bi bi-person-plus me-1"></i> Crear cuenta gratis
          </a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg px-4" href="/catalogo">
            <i class="bi bi-search me-1"></i> Explorar catálogo
          </a>
        <?php endif; ?>
        <a class="btn btn-soft btn-lg px-4" href="/visitanos">
          <i class="bi bi-compass me-1"></i> Dónde encontrarnos
        </a>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card p-4 border-0 shadow-sm rounded-4 bg-surface">
        <h2 class="h6 text-muted text-uppercase fw-bold mb-3 tracking-wide">
          <i class="bi bi-activity me-1 text-primary"></i> Impacto de la comunidad
        </h2>
        <div class="d-flex justify-content-around text-center py-2">
          <div>
            <div class="stat-num fs-2 fw-800 text-primary">
              <span data-contador="<?= (int) ($stats['disponibles'] ?? 0) ?>"><?= (int) ($stats['disponibles'] ?? 0) ?></span>
            </div>
            <div class="stat-label small text-muted">Libros disponibles</div>
          </div>
          <div class="border-end"></div>
          <div>
            <div class="stat-num fs-2 fw-800 text-primary">
              <span data-contador="<?= (int) ($stats['intercambios'] ?? 0) ?>"><?= (int) ($stats['intercambios'] ?? 0) ?></span>
            </div>
            <div class="stat-label small text-muted">Intercambios</div>
          </div>
          <div class="border-end"></div>
          <div>
            <div class="stat-num fs-2 fw-800 text-primary">
              <span data-contador="<?= (int) ($stats['lectores'] ?? 0) ?>"><?= (int) ($stats['lectores'] ?? 0) ?></span>
            </div>
            <div class="stat-label small text-muted">Lectores</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Cómo funciona: 3 pasos claros -->
<section class="mb-5">
  <div class="text-center mb-4">
    <h2 class="h3 fw-800">¿Cómo funciona LibrosBro?</h2>
    <p class="text-muted">Intercambio presencial, sencillo, transparente y sin dinero.</p>
  </div>
  <div class="row g-4">
    <div class="col-md-4">
      <div class="card h-100 p-4 border-0 shadow-sm rounded-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 52px; height: 52px;">
          <i class="bi bi-box-seam fs-4"></i>
        </div>
        <h3 class="h5 fw-bold mb-2">1. Deposita tus libros</h3>
        <p class="text-muted mb-0">
          Trae los libros que ya no lees a nuestro mostrador físico. Comprobamos que estén en buen estado y recibes <strong>+<?= $bonoDeposito ?> token</strong> por cada uno.
        </p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 p-4 border-0 shadow-sm rounded-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 52px; height: 52px;">
          <i class="bi bi-journal-bookmark fs-4"></i>
        </div>
        <h3 class="h5 fw-bold mb-2">2. Reserva online</h3>
        <p class="text-muted mb-0">
          Explora nuestro catálogo de libros admitidos. Si un libro tiene ejemplares disponibles, resérvalo online sin coste para asegurarte tu copia durante <?= (int) ($config['horas_reserva'] ?? 72) ?> horas.
        </p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 p-4 border-0 shadow-sm rounded-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 52px; height: 52px;">
          <i class="bi bi-upc-scan fs-4"></i>
        </div>
        <h3 class="h5 fw-bold mb-2">3. Recoge en mostrador</h3>
        <p class="text-muted mb-0">
          Pasa por las instalaciones dentro del plazo y enseña el código de barras 1D de tu reserva. Paga con <strong>🪙 <?= $costeLibro ?> token</strong> o trayendo otro libro admitido.
        </p>
      </div>
    </div>
  </div>
  <div class="text-center mt-4">
    <a href="/como-funciona" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
      <i class="bi bi-question-circle me-1"></i>Conoce cómo funciona al detalle y preguntas frecuentes
    </a>
  </div>
</section>

<!-- 3. Muestra de libros disponibles en el catálogo -->
<section class="mb-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="h4 fw-800 mb-1">En el catálogo ahora</h2>
      <p class="text-muted small mb-0">Ejemplares admitidos con coste uniforme de 🪙 <?= $costeLibro ?> token.</p>
    </div>
    <a class="btn btn-soft btn-sm px-3" href="/catalogo">
      Ver catálogo completo <i class="bi bi-arrow-right ms-1"></i>
    </a>
  </div>

  <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-md-4">
    <?php foreach ($destacados as $libro): ?>
      <div class="col">
        <article class="card-libro h-100 d-flex flex-column position-relative shadow-sm">
          <a href="/libro/<?= (int) $libro['id'] ?>" class="text-decoration-none text-reset d-block overflow-hidden" title="Ver ficha de <?= e($libro['titulo']) ?>">
            <?php
              $portadaHome = catalogo_resolver_url_portada($libro);
              $fallbackSvgHome = '/portada-svg?titulo=' . urlencode($libro['titulo']) . '&autor=' . urlencode($libro['autor']);
            ?>
            <img class="card-libro-portada" src="<?= e($portadaHome) ?>" alt="Portada de <?= e($libro['titulo']) ?>"
                 width="240" height="340"
                 referrerpolicy="no-referrer"
                 data-fallback="<?= e($fallbackSvgHome) ?>"
                 onerror="this.onerror=null; this.src=this.dataset.fallback;"
                 loading="lazy">
          </a>
          <div class="card-libro-cuerpo d-flex flex-column flex-grow-1 p-3">
            <h3 class="card-libro-titulo mb-1">
              <a href="/libro/<?= (int) $libro['id'] ?>" class="text-decoration-none text-reset" title="<?= e($libro['titulo']) ?>">
                <?= e($libro['titulo']) ?>
              </a>
            </h3>
            <p class="card-libro-autor text-muted small mb-3"><?= e($libro['autor']) ?></p>
            <div class="card-libro-pie mt-auto pt-2 border-top d-flex justify-content-between align-items-center mb-2">
              <span class="badge-estado badge-estado-disponible">Admitido</span>
              <span class="precio-token">🪙 <?= $costeLibro ?></span>
            </div>
            <a href="/libro/<?= (int) $libro['id'] ?>" class="btn btn-sm btn-outline-primary w-100 rounded-pill fw-semibold mt-1">
              <i class="bi bi-bookmark-plus me-1"></i>Ver y reservar
            </a>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- 4. Llamada a visitarnos -->
<section class="p-4 p-md-5 rounded-4 shadow-sm bg-surface border mb-4">
  <div class="row align-items-center g-4">
    <div class="col-md-8">
      <h2 class="h4 fw-800 mb-2">Ven a visitarnos a nuestro espacio físico</h2>
      <p class="text-muted mb-0">
        <?= e($config['centro_nombre'] ?? 'LibrosBro') ?> · <?= e($config['centro_direccion'] ?? 'Consulta nuestra ubicación') ?>.
        Gestionamos las recogidas y los depósitos en persona para garantizar la mejor calidad en cada libro.
      </p>
    </div>
    <div class="col-md-4 text-md-end">
      <a class="btn btn-primary px-4 py-2" href="/visitanos">
        <i class="bi bi-geo-alt me-1"></i> Ver plano y horarios
      </a>
    </div>
  </div>
</section>
