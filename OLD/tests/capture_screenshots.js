const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BASE_URL = 'http://localhost:8088';
const OUTPUT_DIR = path.join(__dirname, '../public/assets/img/ayuda');

if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

async function capture() {
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1280,850'],
    defaultViewport: { width: 1280, height: 850 }
  });

  async function getLoggedInPage(email, password) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.setViewport({ width: 1280, height: 850 });
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
    await page.type('input[name="email"]', email);
    await page.type('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
      page.click('button[type="submit"]')
    ]);
    return page;
  }

  console.log('--- Capturando INVITADO ---');
  {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.setViewport({ width: 1280, height: 850 });
    await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'guest_home.png') });

    await page.goto(`${BASE_URL}/catalogo`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'guest_catalogo.png') });
    await context.close();
  }

  console.log('--- Capturando USUARIO ---');
  {
    const page = await getLoggedInPage('usuario@bookswap.local', 'password');

    await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'usuario_dashboard.png') });

    await page.goto(`${BASE_URL}/catalogo`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'usuario_catalogo.png') });

    await page.goto(`${BASE_URL}/mis-reservas`, { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1000));
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'usuario_reservas.png') });

    await page.goto(`${BASE_URL}/wishlist`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'usuario_wishlist.png') });

    await page.goto(`${BASE_URL}/mi-historial`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'usuario_historial.png') });

    await page.goto(`${BASE_URL}/cambiar-password`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'usuario_cambiar_password.png') });
  }

  console.log('--- Capturando PERSONAL ---');
  {
    const page = await getLoggedInPage('personal@bookswap.local', 'password');

    await page.goto(`${BASE_URL}/mostrador`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'personal_mostrador.png') });

    await page.goto(`${BASE_URL}/mostrador/entrega-directa`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'personal_entrega_directa.png') });

    await page.goto(`${BASE_URL}/libros/entrada`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'personal_entrada.png') });

    await page.goto(`${BASE_URL}/ejemplar/1`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'personal_trazabilidad.png') });
  }

  console.log('--- Capturando ADMIN ---');
  {
    const page = await getLoggedInPage('admin@bookswap.local', 'password');

    await page.goto(`${BASE_URL}/admin`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'admin_dashboard.png') });

    await page.goto(`${BASE_URL}/admin/usuarios`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'admin_usuarios.png') });

    await page.goto(`${BASE_URL}/admin/roles`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'admin_roles.png') });

    await page.goto(`${BASE_URL}/admin/csv`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'admin_csv.png') });

    await page.goto(`${BASE_URL}/admin/backups`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'admin_backups.png') });

    await page.goto(`${BASE_URL}/admin/libros/editar?id=1`, { waitUntil: 'networkidle2' });
    try {
      await page.waitForSelector('.btn-disparar-buscador-portadas', { timeout: 3000 });
      await page.click('.btn-disparar-buscador-portadas');
      await new Promise(r => setTimeout(r, 2500));
      await page.screenshot({ path: path.join(OUTPUT_DIR, 'admin_buscar_portadas.png') });
    } catch (e) {
      console.log('No se pudo abrir modal portadas para screenshot:', e.message);
    }
  }

  await browser.close();
  console.log('¡Todas las capturas guardadas con éxito en public/assets/img/ayuda/!');
}

capture().catch(err => {
  console.error('Error capturando:', err);
  process.exit(1);
});
