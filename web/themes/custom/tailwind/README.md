# Tailwind Theme

A clean, blank Drupal 11 tailwind theme built with Tailwind CSS.

## Features

- Drupal 11 compatible
- Tailwind CSS integration
- Single Directory Components support (optional)
- Clean, minimal structure
- Ready for customization

## Setup

The CSS build process natively uses the `tailwind_drush` module, which bundles the Tailwind standalone CLI.

1. Build CSS (from within this theme directory):
   ```bash
   ddev drush tailwind:build --input src/css/styles.css --output dist/css/styles.css
   ```

2. Watch for changes during development:
   ```bash
   ddev drush tailwind:watch --input src/css/styles.css --output dist/css/styles.css
   ```

## Structure

```
tailwind/
├── components/          # Single Directory Components (optional)
├── dist/               # Compiled CSS (gitignored)
├── js/                 # JavaScript files
├── src/                # Source files
│   └── css/           # Source CSS files
├── templates/          # Twig templates
├── tailwind.config.js  # Tailwind CSS configuration
└── tailwind.info.yml # Theme definition
```

## Customization

- **Colors**: Edit `tailwind.config.js` to add custom colors
- **Styles**: Edit `src/css/styles.css` to add custom styles
- **Templates**: Edit files in `templates/` to customize markup
- **Components**: Add Single Directory Components in `components/`

## Drupal Setup

1. Enable the theme:
   ```bash
   ddev drush theme:enable tailwind
   ```

2. Set as default theme:
   ```bash
   ddev drush config:set system.theme default tailwind
   ```

Note: The theme uses "tailwind" as the namespace throughout.

3. Clear cache:
   ```bash
   ddev drush cr
   ```

## License

GPL-2.0-or-later
