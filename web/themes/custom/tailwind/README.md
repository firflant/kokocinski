# Tailwind Theme

A clean, blank Drupal 11 tailwind theme built with Tailwind CSS.

## Features

- Drupal 11 compatible
- Tailwind CSS integration
- Single Directory Components support (optional)
- Clean, minimal structure
- Ready for customization

## Setup

1. Install dependencies:
   ```bash
   yarn install
   ```

2. Build CSS for development:
   ```bash
   yarn build:dev
   ```

3. Watch for changes during development:
   ```bash
   yarn dev
   ```

4. Build CSS for production:
   ```bash
   yarn build
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
├── package.json        # Node.js dependencies
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
