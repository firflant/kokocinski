# Drush Tailwind CSS

Provides a Drush command that wraps the standalone Tailwind CSS CLI.
This allows you to compile Tailwind CSS directly via Drush — no Node.js or npm required.

**Supported Tailwind CSS version: 4.x**

The Tailwind CSS standalone binary is **not bundled** with this module. It is downloaded automatically from the [official GitHub releases](https://github.com/tailwindlabs/tailwindcss/releases) for your OS and architecture on first use.

## Requirements

The server running Drush must have one of the following available to download the binary:

- PHP `curl` extension (preferred), **or**
- `allow_url_fopen = On` in `php.ini`

## Installation

Enable the module:

```bash
drush en tailwind_drush
```

## Usage

### The simplest way — just run it

```bash
drush tailwind:build
drush tailwind:watch
```

No options needed. The commands **auto-detect** the input CSS file and output path from your active default Drupal theme by reading its `.libraries.yml`. If the binary has not been downloaded yet, it is fetched automatically before compilation starts.

### Explicit binary download (optional)

If you prefer to install the binary upfront before building:

```bash
drush tailwind:install
```

This is entirely optional — `tailwind:build` and `tailwind:watch` will download the binary on their own if it is missing.

### Passing options explicitly

Use explicit options when auto-detection fails, when you want to build a theme other than the current default, or when you simply prefer full control over the paths:

```bash
drush tailwind:build \
  --input web/themes/custom/my_theme/src/tailwind.css \
  --output web/themes/custom/my_theme/css/style.css

drush tailwind:watch \
  --input web/themes/custom/my_theme/src/tailwind.css \
  --output web/themes/custom/my_theme/css/style.css
```

### All Tailwind CLI options are available

This module is a thin Drush wrapper around the official Tailwind CSS standalone CLI — every option the official CLI supports can be passed through:

```bash
# Minify for production
drush tailwind:build --minify

# Restrict scanning to specific content paths
drush tailwind:build --content "web/themes/custom/my_theme/templates/**/*.twig"
```

Refer to the [official Tailwind CSS CLI documentation](https://tailwindcss.com/docs/functions-and-directives) for the full list of available flags.

## Available commands

| Command            | Alias | Description                                                        |
| ------------------ | ----- | ------------------------------------------------------------------ |
| `tailwind:install` | `twi` | Downloads the binary for the current platform                      |
| `tailwind:build`   | `twb` | Compiles Tailwind CSS once (auto-detects theme paths)              |
| `tailwind:watch`   | `tww` | Watches for file changes and recompiles (auto-detects theme paths) |

## How it works

On first use, the module queries the GitHub API for the latest stable v4.x release, then downloads the matching binary for your OS and architecture. The binary is reused on subsequent runs.

### Binary storage location

The binary is stored **outside the module directory** so it survives `composer install` and module updates. The storage path is resolved in this order:

1. `TAILWIND_BINARY_DIR` environment variable — set this for Docker or custom setups
2. `$XDG_DATA_HOME/tailwindcss/` — if the XDG variable is set
3. `~/.local/share/tailwindcss/` — XDG Base Directory standard fallback (default on Linux/macOS)

This follows the same convention as Composer (`~/.composer`), npm (`~/.npm`), and pip (`~/.cache/pip`).

### CI/CD caching

To avoid re-downloading the binary on every CI run, cache the storage path:

**GitHub Actions:**

```yaml
- uses: actions/cache@v4
  with:
    path: ~/.local/share/tailwindcss
    key: tailwindcss-${{ runner.os }}
```

**GitLab CI:**

```yaml
cache:
  paths:
    - ~/.local/share/tailwindcss/
```

**Docker / custom path:**

```bash
TAILWIND_BINARY_DIR=/mnt/cache/tailwindcss drush tailwind:build
```

## Known limitations

### Hosting providers running FreeBSD

Some shared hosting providers run their servers on **FreeBSD** — a Unix-like operating system that is related to, but distinctly different from, Linux.

The Tailwind CSS standalone CLI is only distributed as pre-compiled binaries for Linux and macOS — there is no native FreeBSD build. While FreeBSD includes a compatibility layer that can run Linux binaries, shared hosting providers typically do not enable it, and individual users cannot activate it themselves. As a result, attempting to execute the downloaded binary will fail with:

```
ELF binary type "0" not known
Exec format error
```
