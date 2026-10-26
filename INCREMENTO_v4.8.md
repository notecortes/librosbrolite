# INCREMENTO v4.8 — BookSwap: ALTA DE LIBROS CON 0 EJEMPLARES ("solo catalogar")
# (a ejecutar tras completar v4.7 con suite en verde; añade la Fase 23)
# Lee BRIEF.md, PROGRESS.md e INCREMENTO_v4.2→v4.7: TODAS sus reglas siguen
# vigentes (regresión = fallo, archivos completos). NO se toca ningún fichero
# canónico: libros y ejemplares ya son independientes en BD y el importador CSV
# ya crea libros sin copias. El cambio es de VALIDACIÓN y UI.

## 0. OBJETIVO
Hoy la Entrada de copias exige nº de copias entre 1 y 20, así que no se puede
catalogar un libro admitido hasta tenerlo físicamente. Objetivo: permitir
guardar libros del catálogo con 0 copias ("solo catalogar; las copias llegarán
después"), con estados vacíos claros y sin romper ningún flujo existente.

## 1. CAMBIOS
1.1 Entrada de copias (/libros/entrada, tanto para libro NUEVO en el CASO B
    del alta asistida como para libro EXISTENTE en el CASO A):
    - El campo "número de copias" pasa a aceptar 0-20 (default sigue siendo 1).
      Si prefieres UI de casilla, añade un checkbox "Solo catalogar sin copias
      ahora" que fija copias=0; elige UNA de las dos formas y documéntalo.
    - Con copias=0: se crea/actualiza el libro SIN crear ejemplares, se audita
      (accion 'entrada_copias' con detalle N=0) y el flujo aterriza en la
      FICHA del libro, NO en un listado.
    - Localiza y elimina la validación de mínimo 1 en SERVIDOR (busca en el
      código del endpoint: min=1, >=1, "1-20" o equivalente) — la UI sola no
      basta; la validación server-side debe aceptar 0..20.
1.2 Ficha del libro con 0 copias: muestra el estado vacío del tema ("Sin copias
    ahora") con CTA "➕ Añadir copias cuando lleguen" para staff (con
    catalogo.editar) y, para usuarios, el hint "¿Lo tienes en casa? Tráelo al
    mostrador y gana tokens" (ya previsto en v4.5 §3.3; verificar que funciona
    con 0 copias, no solo "sin disponibles").
1.3 /catalogo: los libros con 0 copias APARECEN en resultados con chip
    "Sin copias ahora" (el catálogo es la lista cerrada de admitidos: ver el
    libro sin copia es información útil). Su card muestra el hint en lugar del
    botón Reservar, y quedan excluidos del filtro "solo disponibles".
1.4 Dashboard: "Últimos ingresos" lista SOLO libros con ≥1 copia disponible
    (un alta de 0 copias no es un ingreso físico).
1.5 Escáner universal: escanear el ISBN de un libro con 0 copias → ficha del
    libro con "➕ Añadir copias" RESALTADO (v4.6 §2.4 ya lo pedía para "sin
    disponibles"; verifica que funciona con 0 copias totales).
1.6 CSV: el importador de catálogo ya crea libros sin copias; verifica que el
    informe, la ficha y el catálogo no rompen con ellos (probablemente no haya
    nada que cambiar: solo regresión).
1.7 Docs: docs/FLOW_MAP.md actualizado (alta sin copias: buscar/escanear → 0 →
    confirmar → ficha; ≤3 interacciones, coherente con §0.C3 de v4.6) y una
    línea en el README ("puedes catalogar libros antes de tener copias").

## 2. TESTS
ALTAS (Fase 23):
| ID | Verifica |
|---|---|
| T-ZERO-01 | Alta de libro NUEVO con 0 copias → libro creado, 0 ejemplares, auditado con N=0, aterriza en ficha con empty state y CTA para staff |
| T-ZERO-02 | Entrada con copias=0 sobre libro EXISTENTE → ejemplares sin cambios, ledger sin movimientos, auditado con N=0 |
| T-ZERO-03 | Libro con 0 copias: aparece en /catalogo con chip "Sin copias ahora", excluido del filtro "solo disponibles" y de "Últimos ingresos"; reservarlo por POST directo → denegado server-side |
REGRESIÓN OBLIGATORIA: T-CTX-01..07, T-RESV-01..05, T-SCAN-01..05, T-UX-01..07
y la suite completa en verde al cerrar.

## 3. FASE 23 — DEFINICIÓN DE HECHO
1. Flujo de entrada acepta 0 copias en alta nueva y sobre libro existente, con
   validación server-side corregida (no solo UI).
2. Empty states en ficha, catálogo y escáner verificados con un libro 0-copias.
3. Suite completa 0 FAIL (incluidos T-ZERO-01..03 y toda la regresión).
4. docs/FLOW_MAP.md y README actualizados; reporte docs/PHASE_REPORTS/fase23.md
   (decisión de UI elegida: campo 0-20 vs checkbox); PROGRESS.md.
5. Benchmark sin regresión >20% en rutas clave.
