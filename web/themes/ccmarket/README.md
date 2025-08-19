# CCMarket — Drupal 11 Theme

A minimal, production-ready starter theme for Drupal 11.

## Install

1. Download and unzip into: `web/themes/custom/ccmarket`
2. Clear caches: `drush cr` (or via UI).
3. Enable the theme at **Appearance** and set as default.

## Structure

```
ccmarket/
├─ ccmarket.info.yml
├─ ccmarket.libraries.yml
├─ ccmarket.breakpoints.yml
├─ ccmarket.theme
├─ screenshot.png
├─ assets/
│  ├─ css/style.css
│  └─ js/script.js
└─ templates/
   ├─ layout/html.html.twig
   ├─ layout/page.html.twig
   └─ content/node.html.twig
```

## Notes

- The theme registers one global library (`ccmarket/global`) that includes `assets/css/style.css` and `assets/js/script.js`.
- Breakpoints are defined in `ccmarket.breakpoints.yml`.
- You can add more templates in `templates/` as needed (e.g. `node--article.html.twig`, `page--front.html.twig`).
- To use a base theme like Olivero, uncomment the `base theme` line in `ccmarket.info.yml`.
