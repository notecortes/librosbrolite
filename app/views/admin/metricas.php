<?php
/**
 * BookSwap · Métricas y Cuadro de Mando Analítico (Panel de Administración).
 *
 * Muestra (§11):
 * - KPIs generales del sistema.
 * - Gráficos Chart.js (copias por estado, flujo mensual de depósitos y entregas).
 * - Ranking de libros con mayor volumen de ejemplares.
 */
declare(strict_types=1);

$metricas = $metricas ?? [];
$totales = $metricas['totales'] ?? [];
$copiasPorEstado = $metricas['copias_por_estado'] ?? [];
$tokensEnCirculacion = (int) ($metricas['tokens_en_circulacion'] ?? 0);
$depositosPorMes = $metricas['depositos_por_mes'] ?? [];
$entregasPorMes = $metricas['entregas_por_mes'] ?? [];
$topLibros = $metricas['top_libros'] ?? [];
?>

<div class="container-xxl py-4">
  <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
    <div>
      <h1 class="h3 fw-bold mb-1">
        <i class="bi bi-bar-chart-line text-primary me-2"></i>Métricas del Sistema
      </h1>
      <p class="text-muted small mb-0">Estadísticas operativas, circulación de tokens y dinamismo del catálogo.</p>
    </div>
    <a href="/admin" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Volver al Panel
    </a>
  </div>

  <!-- Tarjetas KPI -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-primary-subtle text-primary p-3 rounded-3">
            <i class="bi bi-book fs-4"></i>
          </div>
          <div>
            <span class="d-block text-muted small">Títulos Únicos</span>
            <strong class="fs-4"><?= (int) ($totales['libros'] ?? 0) ?></strong>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-success-subtle text-success p-3 rounded-3">
            <i class="bi bi-collection fs-4"></i>
          </div>
          <div>
            <span class="d-block text-muted small">Ejemplares Totales</span>
            <strong class="fs-4"><?= (int) ($totales['ejemplares'] ?? 0) ?></strong>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-warning-subtle text-warning p-3 rounded-3">
            <i class="bi bi-coin fs-4"></i>
          </div>
          <div>
            <span class="d-block text-muted small">Tokens en Circulación</span>
            <strong class="fs-4"><?= $tokensEnCirculacion ?></strong>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-info-subtle text-info p-3 rounded-3">
            <i class="bi bi-people fs-4"></i>
          </div>
          <div>
            <span class="d-block text-muted small">Socios Activos</span>
            <strong class="fs-4"><?= (int) ($totales['socios'] ?? 0) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráficos analíticos -->
  <div class="row g-4 mb-4">
    <!-- Gráfico 1: Estado de las copias -->
    <div class="col-12 col-lg-5">
      <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
        <h2 class="h6 fw-bold mb-3">Distribución de Copias por Estado</h2>
        <div style="height: 280px;" class="d-flex align-items-center justify-content-center">
          <canvas id="chartEstados"></canvas>
        </div>
      </div>
    </div>

    <!-- Gráfico 2: Flujo mensual -->
    <div class="col-12 col-lg-7">
      <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
        <h2 class="h6 fw-bold mb-3">Movimientos Mensuales (Depósitos vs Entregas)</h2>
        <div style="height: 280px;">
          <canvas id="chartMensual"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabla de libros más demandados / con más ejemplares -->
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom p-4">
      <h2 class="h5 fw-bold mb-0">Top Libros por Volumen de Ejemplares</h2>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase">
          <tr>
            <th class="ps-4">Título</th>
            <th>Autor</th>
            <th class="text-end pe-4">Ejemplares en Fondo</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($topLibros)): ?>
            <tr><td colspan="3" class="text-center py-4 text-muted">Sin datos suficientes.</td></tr>
          <?php else: ?>
            <?php foreach ($topLibros as $l): ?>
              <tr>
                <td class="ps-4 fw-bold">
                  <a href="/libro/<?= (int) $l['id'] ?>" class="text-decoration-none text-body">
                    <?= e($l['titulo']) ?>
                  </a>
                </td>
                <td class="small text-muted"><?= e($l['autor']) ?></td>
                <td class="text-end pe-4">
                  <span class="badge bg-primary-subtle text-primary border rounded-pill fs-6 px-3">
                    <?= (int) $l['total_ejemplares'] ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Chart.js desde CDN oficial sin dependencias locales -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Gráfico Doughnut: Estados
  const ctxEstados = document.getElementById('chartEstados');
  if (ctxEstados && typeof Chart !== 'undefined') {
    new Chart(ctxEstados, {
      type: 'doughnut',
      data: {
        labels: ['Disponibles', 'Reservados', 'Retirados', 'Baja'],
        datasets: [{
          data: [
            <?= (int) ($copiasPorEstado['disponible'] ?? 0) ?>,
            <?= (int) ($copiasPorEstado['reservado'] ?? 0) ?>,
            <?= (int) ($copiasPorEstado['retirado'] ?? 0) ?>,
            <?= (int) ($copiasPorEstado['baja'] ?? 0) ?>
          ],
          backgroundColor: ['#10B981', '#3B82F6', '#6B7280', '#EF4444'],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  }

  // Gráfico Barras: Mensual
  const ctxMensual = document.getElementById('chartMensual');
  if (ctxMensual && typeof Chart !== 'undefined') {
    const meses = <?= json_encode(array_values(array_unique(array_merge(array_keys($depositosPorMes), array_keys($entregasPorMes))))) ?>;
    const depData = <?= json_encode(array_values($depositosPorMes)) ?>;
    const entData = <?= json_encode(array_values($entregasPorMes)) ?>;

    new Chart(ctxMensual, {
      type: 'bar',
      data: {
        labels: meses.length ? meses : ['Actual'],
        datasets: [
          {
            label: 'Depósitos',
            data: depData.length ? depData : [0],
            backgroundColor: '#10B981',
            borderRadius: 6
          },
          {
            label: 'Entregas',
            data: entData.length ? entData : [0],
            backgroundColor: '#4F46E5',
            borderRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }
});
</script>
