// Dependency-free static build: src/menu.json + src/* -> dist/ (prerendered HTML, minified CSS/JS)
import fs from 'node:fs';
import path from 'node:path';
const root = path.dirname(new URL(import.meta.url).pathname);
const dist = path.join(root, 'dist');
const menu = JSON.parse(fs.readFileSync(path.join(root, 'src/menu.json'), 'utf8'));
const { site, categories } = menu;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const price = p => (typeof p === 'number' ? `${p}<small>${esc(site.currency)}</small>` : '—');
const brand = (cls) => site.logo
  ? `<img src="${esc(site.logo)}" alt="${esc(site.name)}" width="220" height="110">`
  : `<div class="wordmark${cls}" aria-hidden="true">Tre<b>III</b></div>`;
const item = i => `<li class="item${i.available === false ? ' off' : ''}">${i.image ? `<img src="${esc(i.image)}" alt="" width="64" height="64" loading="lazy" decoding="async">` : ''}<div class="info"><div class="name">${esc(i.name)}${i.badge ? `<span class="badge">${esc(i.badge)}</span>` : ''}</div>${i.description ? `<p class="desc">${esc(i.description)}</p>` : ''}</div><div class="price">${i.available === false ? 'Unavailable' : price(i.price)}</div></li>`;
const cats = categories.filter(c => c.items.length);
const jsonld = { '@context': 'https://schema.org', '@type': 'CafeOrCoffeeShop', name: site.name, url: site.url, hasMenu: site.url };
const html = `<!doctype html>
<html lang="${esc(site.lang)}"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>${esc(site.title)}</title>
<meta name="description" content="${esc(site.description)}">
<meta name="theme-color" content="#1d130e">
<link rel="canonical" href="${esc(site.url)}/">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<meta property="og:type" content="website"><meta property="og:title" content="${esc(site.title)}">
<meta property="og:description" content="${esc(site.description)}"><meta property="og:url" content="${esc(site.url)}/">
<meta property="og:image" content="${esc(site.url)}/${esc(site.ogImage)}"><meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Allura&family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap">
<style>${fs.readFileSync(path.join(root, 'src/style.css'), 'utf8').replace(/\/\*[\s\S]*?\*\/|\n\s*/g, '').replace(/\s*([{};:,>])\s*/g, '$1')}</style>
<script type="application/ld+json">${JSON.stringify(jsonld)}</script></head>
<body>
<header class="hero">${brand('')}<h1 class="sr">${esc(site.name)}</h1><p class="tagline">${esc(site.tagline)}</p><p class="explore">Explore Our Menu</p></header>
<nav class="cats" aria-label="Menu categories"><ul>${cats.map((c, n) => `<li><a href="#${esc(c.id)}"${n === 0 ? ' class="active"' : ''}>${esc(c.name)}</a></li>`).join('')}</ul></nav>
<main>${cats.map(c => `<section id="${esc(c.id)}"><h2>${esc(c.name)}</h2><ul class="items">${c.items.map(item).join('')}</ul></section>`).join('')}</main>
<footer>${brand('')}<p>© ${new Date().getFullYear()} ${esc(site.name)}</p></footer>
<script defer src="app.js"></script></body></html>`;
fs.rmSync(dist, { recursive: true, force: true });
fs.cpSync(path.join(root, 'public'), dist, { recursive: true });
fs.cpSync(path.join(root, 'deploy/.htaccess'), path.join(dist, '.htaccess'));
fs.writeFileSync(path.join(dist, 'index.html'), html);
fs.writeFileSync(path.join(dist, 'app.js'), fs.readFileSync(path.join(root, 'src/app.js'), 'utf8').replace(/\n\s*/g, ''));
fs.writeFileSync(path.join(dist, 'robots.txt'), `User-agent: *\nAllow: /\nSitemap: ${site.url}/sitemap.xml\n`);
fs.writeFileSync(path.join(dist, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>${site.url}/</loc></url></urlset>`);
console.log('Built dist/ —', cats.length, 'categories,', cats.reduce((n, c) => n + c.items.length, 0), 'items');
