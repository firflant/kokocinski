<?php

namespace Drupal\drush_tailwindcss\Commands;

use Drush\Commands\DrushCommands;
use Drupal\drush_tailwindcss\TailwindRuntime;

/**
 * A Drush commandfile wrapping Tailwind CSS standalone CLI.
 */
class TailwindCssCommands extends DrushCommands {

  /**
   * @var \Drupal\drush_tailwindcss\TailwindRuntime
   */
  protected $tailwindRuntime;

  /**
   * TailwindCssCommands constructor.
   *
   * @param \Drupal\drush_tailwindcss\TailwindRuntime $tailwindRuntime
   *   The Tailwind Runtime service.
   */
  public function __construct(TailwindRuntime $tailwindRuntime) {
    parent::__construct();
    $this->tailwindRuntime = $tailwindRuntime;
  }

  /**
   * Download the Tailwind CSS CLI binary for the current platform.
   *
   * @command tailwind:install
   * @aliases twi
   * @usage drush tailwind:install
   *   Downloads the Tailwind CSS standalone binary for your OS and architecture.
   */
  public function install() {
    try {
      $existingPath = $this->tailwindRuntime->getBinaryPath();
      $this->logger()->success(dt('Tailwind CLI v@major.x is already installed at @path', [
        '@major' => \Drupal\drush_tailwindcss\TailwindRuntime::TAILWIND_MAJOR_VERSION,
        '@path' => $existingPath,
      ]));
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      // Binary not present or version mismatch — proceed with download.
    }

    $this->logger()->notice(dt('Resolving latest Tailwind CSS CLI v@major.x release...', [
      '@major' => \Drupal\drush_tailwindcss\TailwindRuntime::TAILWIND_MAJOR_VERSION,
    ]));

    try {
      $path = $this->tailwindRuntime->downloadBinary();
      $this->logger()->success(dt('Tailwind CLI installed at @path', ['@path' => $path]));
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error(dt('Download failed: @message', ['@message' => $e->getMessage()]));
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Build Tailwind CSS.
   *
   * @command tailwind:build
   * @aliases twb
   * @option input The input CSS file path.
   * @option output The output CSS file path.
   * @option content The content files to watch.
   * @option minify Minify the output.
   * @usage drush tailwind:build --input web/themes/custom/my_theme/src/tailwind.css --output web/themes/custom/my_theme/css/style.css
   */
  public function build(array $options = ['input' => '', 'output' => '', 'content' => '', 'minify' => false]) {
    return $this->executeTailwind($options, false);
  }

  /**
   * Watch and build Tailwind CSS.
   *
   * @command tailwind:watch
   * @aliases tww
   * @option input The input CSS file path.
   * @option output The output CSS file path.
   * @option content The content files to watch.
   * @usage drush tailwind:watch --input web/themes/custom/my_theme/src/tailwind.css --output web/themes/custom/my_theme/css/style.css
   */
  public function watch(array $options = ['input' => '', 'output' => '', 'content' => '']) {
    // Watch typically does not exit on its own unless terminated.
    // The underlying passthru command in executeTailwind will run until the user stops it.
    return $this->executeTailwind($options, true);
  }

  /**
   * Executes the Tailwind CLI binary.
   */
  protected function executeTailwind(array $options, bool $watch) {
    try {
      $binaryPath = $this->tailwindRuntime->getBinaryPath();
    }
    catch (\Exception $e) {
      $this->logger()->warning(dt('Tailwind CLI binary unavailable: @reason. Downloading latest v@major.x now...', [
        '@reason' => $e->getMessage(),
        '@major' => \Drupal\drush_tailwindcss\TailwindRuntime::TAILWIND_MAJOR_VERSION,
      ]));
      try {
        $binaryPath = $this->tailwindRuntime->downloadBinary();
        $this->logger()->success(dt('Tailwind CLI binary downloaded successfully.'));
      }
      catch (\Exception $downloadException) {
        $this->logger()->error(dt('Failed to download Tailwind binary: @message', ['@message' => $downloadException->getMessage()]));
        return self::EXIT_FAILURE;
      }
    }

    // Smart defaults: automatically resolve paths for the default theme.
    $auto_detected = FALSE;
    if (empty($options['input']) || empty($options['output'])) {
      $default_theme = \Drupal::config('system.theme')->get('default');
      if ($default_theme) {
        $theme_path = \Drupal::service('extension.list.theme')->getPath($default_theme);
        $absolute_theme_path = \Drupal::root() . '/' . $theme_path;

        if (empty($options['input'])) {
          $inputCandidates = [
            $absolute_theme_path . '/src/css/styles.css',
            $absolute_theme_path . '/src/css/style.css',
            $absolute_theme_path . '/src/css/main.css',
            $absolute_theme_path . '/src/css/index.css',
            $absolute_theme_path . '/src/main.css',
            $absolute_theme_path . '/src/styles.css',
            $absolute_theme_path . '/src/style.css',
            $absolute_theme_path . '/src/index.css',
            $absolute_theme_path . '/css/styles.css',
            $absolute_theme_path . '/css/style.css',
            $absolute_theme_path . '/css/main.css',
            $absolute_theme_path . '/css/index.css',
          ];
          foreach ($inputCandidates as $candidate) {
            if (file_exists($candidate)) {
              $options['input'] = $candidate;
              break;
            }
          }
          if (empty($options['input'])) {
            $options['input'] = $absolute_theme_path . '/src/css/styles.css';
          }
          $auto_detected = TRUE;
        }

        if (empty($options['output'])) {
          $libraries_file = $absolute_theme_path . '/' . $default_theme . '.libraries.yml';
          $css_path = NULL;
          if (file_exists($libraries_file)) {
            $libraries = \Drupal\Component\Serialization\Yaml::decode(file_get_contents($libraries_file));

            // 1. global-styling library (explicit custom-theme convention).
            if (isset($libraries['global-styling']['css']['theme']) && is_array($libraries['global-styling']['css']['theme'])) {
              $css_path = array_key_first($libraries['global-styling']['css']['theme']);
            }
            if (!$css_path && isset($libraries['global-styling']['css']['base']) && is_array($libraries['global-styling']['css']['base'])) {
              $css_path = array_key_first($libraries['global-styling']['css']['base']);
            }

            // 2. Any CSS file explicitly marked minified: true (Mercury: build/main.min.css).
            if (!$css_path && is_array($libraries)) {
              foreach ($libraries as $lib_data) {
                foreach (['theme', 'base', 'component'] as $bucket) {
                  foreach ($lib_data['css'][$bucket] ?? [] as $path => $attrs) {
                    if (!empty($attrs['minified'])) {
                      $css_path = $path;
                      break 3;
                    }
                  }
                }
              }
            }

            // 3. First css.theme file whose path starts with build/ or dist/.
            if (!$css_path && is_array($libraries)) {
              foreach ($libraries as $lib_data) {
                foreach ($lib_data['css']['theme'] ?? [] as $path => $attrs) {
                  if (str_starts_with($path, 'build/') || str_starts_with($path, 'dist/')) {
                    $css_path = $path;
                    break 2;
                  }
                }
              }
            }

            // 4. First css.theme file from any library.
            if (!$css_path && is_array($libraries)) {
              foreach ($libraries as $lib_data) {
                if (isset($lib_data['css']['theme']) && is_array($lib_data['css']['theme'])) {
                  $css_path = array_key_first($lib_data['css']['theme']);
                  break;
                }
              }
            }
          }

          if ($css_path) {
            $options['output'] = $absolute_theme_path . '/' . $css_path;
          }
          else {
            $options['output'] = $absolute_theme_path . '/dist/css/styles.css';
          }
          $auto_detected = TRUE;
        }

        if ($auto_detected) {
          $this->logger()->notice(dt('Auto-detected default theme: @theme', ['@theme' => $default_theme]));
          $this->logger()->notice(dt('  Input:  @input', ['@input' => $options['input']]));
          $this->logger()->notice(dt('  Output: @output', ['@output' => $options['output']]));
        }
      }
      else {
        // Fallback if no default theme is determined.
        if (empty($options['input'])) {
          $options['input'] = 'src/css/styles.css';
        }
        if (empty($options['output'])) {
          $options['output'] = 'dist/css/styles.css';
        }
      }
    }

    $command = [$binaryPath];

    if (!empty($options['input'])) {
      $command[] = '-i ' . escapeshellarg($options['input']);
    }
    if (!empty($options['output'])) {
      $command[] = '-o ' . escapeshellarg($options['output']);
    }
    if (!empty($options['content'])) {
      // Content might contain wildcards, like **/*.twig, which we don't want the shell to prematurely expand in unexpected ways, but we do need the CLI to receive them.
      $command[] = '--content ' . escapeshellarg($options['content']);
    }
    if (!empty($options['minify']) && !$watch) {
      $command[] = '--minify';
    }
    if ($watch) {
      $command[] = '--watch';
    }

    $commandString = implode(' ', $command);
    $this->logger()->success(dt('Executing: @command', ['@command' => escapeshellcmd($binaryPath) . ' ...']));

    // Use passthru so that the output of the Tailwind CLI streams directly to the Drush terminal.
    $return_var = 0;
    passthru($commandString, $return_var);

    if ($return_var !== 0) {
      $this->logger()->error('Tailwind build failed!');
      return self::EXIT_FAILURE;
    }

    $this->logger()->success('Tailwind build completed.');
    return self::EXIT_SUCCESS;
  }

}
