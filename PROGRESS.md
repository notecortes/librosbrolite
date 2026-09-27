BookSwap · Progreso de fases

El agente marca [x] al completar cada fase y añade el enlace a su reporte.

- [x] Fase 0 — Andamiaje (router, home coste uniforme, tests en verde) · [Reporte](docs/PHASE_REPORTS/fase0.md)
- [x] Fase 1 — Auth + número de socio + Google (T-AUTH, T-GOOG, T-SOCIO, T-SEC, T-PRIV) · [Reporte](docs/PHASE_REPORTS/fase1.md)
- [x] Fase 2 — Catálogo + búsqueda + alta por ISBN (T-CAT, T-BUSQ, T-API) · [Reporte](docs/PHASE_REPORTS/fase2.md)
- [x] Fase 3 — CSV + pool de socios (T-CSV-*) · [Reporte](docs/PHASE_REPORTS/fase3.md)
- [x] Fase 4 — Reservas (T-RESV-*) · [Reporte](docs/PHASE_REPORTS/fase4.md)
- [x] Fase 5 — Mostrador y economía (T-ENTR, T-DEPO, T-LEDGER, T-MOST, T-AUDIT-01) · [Reporte](docs/PHASE_REPORTS/fase5.md)
- [x] Fase 6 — Config, métricas, backups, visitanos (T-VISIT, T-BAK, T-MET, T-AUDIT-02) · [Reporte](docs/PHASE_REPORTS/fase6.md)
- [x] Fase 7 — QA final + docs/DEPLOY_FTP.md · [Reporte](docs/PHASE_REPORTS/fase7.md)
- [x] Fase 8 — Transición integral a NIA y gestión del pool (T-NIA-*, T-AUTH-01, T-CSV-04) · [Reporte](docs/PHASE_REPORTS/fase8.md)
- [x] Fase 9 — Historial de libros y tokens (/mi-historial, export CSV, vista admin) (T-HIST-*, T-RGRC-03) · [Reporte](docs/PHASE_REPORTS/fase9.md)
- [x] Fase 10 — Catálogo de administración completo (9 secciones, ajuste tokens, reset contraseña, cancelar reserva) (T-ADMIN-*) · [Reporte](docs/PHASE_REPORTS/fase10.md)
- [x] Fase 11 — QA final v4.2 + Documentación actualizada (README, DEPLOY_FTP) y cierre · [Reporte](docs/PHASE_REPORTS/fase11.md)
- [x] Fase 12 — Rendimiento y consumo de recursos (T-PERF-*) · [Reporte](docs/PHASE_REPORTS/fase12.md)
- [x] Fase 13 — Auditoría integral de seguridad, hardening y T-SEC-06..15 · [Reporte](docs/PHASE_REPORTS/fase13.md)
- [x] Fase 14 — QA final v4.3, verificación 0 FAIL y cierre · [Reporte](docs/PHASE_REPORTS/fase14.md)
- [x] Fase 15 — Entrega directa rápida (sin reserva previa) (T-DIR-*) · [Reporte](docs/PHASE_REPORTS/fase15.md)
- [x] Fase 16 — Permisos del personal configurables (matriz en /admin/roles, puede(), T-ROLE-*) · [Reporte](docs/PHASE_REPORTS/fase16.md)
- [x] Fase 17 — Escáner de código de barras 1D + escáner universal (JsBarcode, Code 128, T-SCAN-*) · [Reporte](docs/PHASE_REPORTS/fase17.md)
- [x] Fase 18 — Reorganización UX completa (navbar por rol, dashboard como home, reserva en 1 clic, T-UX-*) · [Reporte](docs/PHASE_REPORTS/fase18.md)
- [x] Fase 20 — Eliminación total del NIA (registro y acceso sólo email/Google, adiós tabla y permiso nias) · [Reporte](docs/PHASE_REPORTS/fase20.md)
- [x] Fase 21 — Reserva con tokens (bloqueo temporal, sin doble cobro, disuasión anti-acaparamiento, T-TOK-*) · [Reporte](docs/PHASE_REPORTS/fase21.md)
- [x] Fase 22 — Recuperación de contraseña (autoservicio por email y generación manual por ADMIN, T-RESET-*) · [Reporte](docs/PHASE_REPORTS/fase22.md)

> Regla: una fase solo se marca completada con tests/run.php en 0 FAIL.