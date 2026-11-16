# TreIII — Café Menu

Static, dependency-free menu site for https://treiii.online (prerendered HTML, ~10 KB, one small JS file).

## Edit the menu
Edit `src/menu.json` (categories, items: `name`, `description`, `price`, `image`, `available`, `badge`), then run `node build.mjs`.
- `price: null` shows a dash. Replace with a number (e.g. `65`); currency is `site.currency`.
- `available: false` greys the item out. `badge`: "Best Seller" / "New" / "Recommended".
- Images: put files in `public/assets/` and set `"image": "assets/latte.webp"` (64×64 display, lazy-loaded).
- Logo: add `public/assets/logo.png` and set `site.logo` to `"assets/logo.png"`. Replace `public/favicon.svg` and `public/assets/og.png` (1200×630) with logo-based versions.

## Deploy (Hostinger / Apache)
Run `node build.mjs`, then upload the **contents of `dist/`** (including the hidden `.htaccess`) to `public_html/` via hPanel File Manager or FTP. Enable free SSL in hPanel. Nginx users: see `deploy/nginx.conf`.
