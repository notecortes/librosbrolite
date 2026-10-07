/* ============================================================
   BookSwap · app.js (v1) — CANÓNICO, NO REGENERAR
   Vanilla ES6, sin dependencias. Interacción por delegación de
   eventos con data-atributos (compatible con CSP estricta).
   API global: window.BS = { toast, confeti, animarContador, setTema }
   ============================================================ */
(() => {
  'use strict';
  const BS = { version: '1.0.0' };

  /* ---- Tema (dark mode) ---- */
  const CLAVE_TEMA = 'bs-theme';

  /**
   * Aplica el tema (claro u oscuro) al documento y lo persiste en localStorage.
   *
   * @param {string} tema 'dark' o 'light'
   * @param {boolean} persistir Si se guarda en almacenamiento local
   */
  BS.setTema = (tema, persistir = true) => {
    if (tema !== 'dark' && tema !== 'light') tema = 'light';
    document.documentElement.setAttribute('data-theme', tema);
    document.documentElement.setAttribute('data-bs-theme', tema); // sincroniza Bootstrap 5.3
    if (document.body) {
      document.body.setAttribute('data-theme', tema);
      document.body.setAttribute('data-bs-theme', tema);
    }
    if (persistir) { try { localStorage.setItem(CLAVE_TEMA, tema); } catch (e) {} }
    document.dispatchEvent(new CustomEvent('bs-theme-change', { detail: { tema } }));
  };

  /**
   * Obtiene el tema actualmente aplicado en el DOM o en la preferencia guardada.
   *
   * @return {string} 'dark' o 'light'
   */
  BS.temaActual = () => {
    const attr = document.documentElement.getAttribute('data-theme')
      || document.documentElement.getAttribute('data-bs-theme');
    if (attr === 'dark' || attr === 'light') return attr;
    try {
      const guardado = localStorage.getItem(CLAVE_TEMA);
      if (guardado === 'dark' || guardado === 'light') return guardado;
    } catch (e) {}
    return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
  };

  /* ---- Toasts (feedback sin alert()) ---- */
  BS.toast = (mensaje, tipo = 'info', ms = 3500) => {
    let cont = document.getElementById('toasts');
    if (!cont) { cont = document.createElement('div'); cont.id = 'toasts'; document.body.appendChild(cont); }
    const t = document.createElement('div');
    t.className = `toast-bs toast-${tipo}`;
    t.setAttribute('role', 'status');
    const txt = document.createElement('div');
    txt.className = 'flex-grow-1';
    txt.textContent = mensaje; // siempre escapado
    t.appendChild(txt);
    cont.appendChild(t);
    setTimeout(() => { t.classList.add('saliendo'); setTimeout(() => t.remove(), 320); }, ms);
  };

  /* ---- Contador animado (saldo, estadísticas) ---- */
  BS.animarContador = (el) => {
    const destino = parseFloat(el.dataset.contador || '0');
    const dec = parseInt(el.dataset.decimales || '0', 10);
    const dur = 700, t0 = performance.now();
    (paso => { const tick = (t) => {
      const k = Math.min(1, (t - t0) / dur);
      el.textContent = (destino * (1 - Math.pow(1 - k, 3))).toFixed(dec);
      if (k < 1) requestAnimationFrame(tick);
    }; requestAnimationFrame(tick); })();
  };

  /* ---- Mini confeti (<2KB, canvas) para entregas confirmadas ---- */
  BS.confeti = (duracion = 1500) => {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const c = document.createElement('canvas'), ctx = c.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    c.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:3000';
    document.body.appendChild(c);
    c.width = innerWidth * dpr; c.height = innerHeight * dpr;
    const colores = ['#4F46E5', '#F59E0B', '#10B981', '#EF4444', '#818CF8'];
    const partes = Array.from({ length: 120 }, () => ({
      x: Math.random() * c.width, y: -20 * dpr - Math.random() * c.height * .3,
      r: (4 + Math.random() * 6) * dpr, vy: (2.5 + Math.random() * 3) * dpr,
      vx: (Math.random() - .5) * 2.4 * dpr, rot: Math.random() * Math.PI,
      vr: (Math.random() - .5) * .25, col: colores[(Math.random() * colores.length) | 0]
    }));
    const t0 = performance.now();
    const frame = (t) => {
      ctx.clearRect(0, 0, c.width, c.height);
      for (const p of partes) {
        p.x += p.vx; p.y += p.vy; p.rot += p.vr;
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot);
        ctx.fillStyle = p.col; ctx.fillRect(-p.r / 2, -p.r / 2, p.r, p.r * .6);
        ctx.restore();
      }
      if (t - t0 < duracion) requestAnimationFrame(frame); else c.remove();
    };
    requestAnimationFrame(frame);
  };

  /* ---- Delegación de eventos ---- */
  document.addEventListener('click', (e) => {
    const tema = e.target.closest('[data-toggle-theme], #btn-toggle-theme, button[aria-label*="tema"]');
    if (tema) {
      e.preventDefault();
      BS.setTema(BS.temaActual() === 'dark' ? 'light' : 'dark');
    }
    if (e.target.closest('[data-confeti]')) BS.confeti();
    const tst = e.target.closest('[data-toast]');
    if (tst) BS.toast(tst.dataset.toast, tst.dataset.toastTipo || 'info');
  });

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-contador]').forEach(BS.animarContador);
    const btnTema = document.getElementById('btn-toggle-theme') || document.querySelector('[data-toggle-theme]');
    if (btnTema) {
      btnTema.addEventListener('click', (e) => {
        e.preventDefault();
        BS.setTema(BS.temaActual() === 'dark' ? 'light' : 'dark');
      });
    }
  });

  window.BS = BS;
})();