# TreIII — Café Menu (PHP, no database)

- `site/` is the whole website (upload its contents to `public_html`).
- Menu data lives in `site/data/menu.json` (edited by the dashboard); uploaded images go to `site/uploads/`.
- Dashboard: https://treiii.online/admin/ — add/edit/delete products and categories, images, dark/light mode, password change.
- When redeploying code, do NOT overwrite `data/menu.json`, `data/auth.json` or `uploads/` on the server — they hold live content.
