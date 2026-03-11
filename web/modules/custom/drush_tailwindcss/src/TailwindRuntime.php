<?php

namespace Drupal\drush_tailwindcss;

use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Service to manage Tailwind CLI runtime.
 */
class TailwindRuntime {

  /**
   * Required Tailwind CSS major version.
   *
   * The module will always download the latest release within this major
   * version. Bump this constant to allow a future major version.
   */
  const TAILWIND_MAJOR_VERSION = 4;

  /**
   * Base URL for Tailwind CSS GitHub releases.
   */
  const GITHUB_RELEASES_BASE_URL = 'https://github.com/tailwindlabs/tailwindcss/releases/download';

  /**
   * GitHub API endpoint for listing releases.
   */
  const GITHUB_API_RELEASES_URL = 'https://api.github.com/repos/tailwindlabs/tailwindcss/releases';

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs a new TailwindRuntime object.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   */
  public function __construct(ModuleExtensionList $moduleExtensionList) {
    $this->moduleExtensionList = $moduleExtensionList;
  }

  /**
   * Gets the path to the correct Tailwind CLI binary for the current OS/Arch.
   *
   * @return string
   *   The absolute path to the binary.
   *
   * @throws \Exception
   *   If the OS/Arch is unsupported or the binary is not present.
   */
  public function getBinaryPath(): string {
    $fullPath = $this->getExpectedBinaryPath();

    if (!file_exists($fullPath)) {
      throw new \Exception("Tailwind binary not found at $fullPath");
    }

    if (!is_executable($fullPath)) {
      chmod($fullPath, 0755);
    }

    return $fullPath;
  }

  /**
   * Resolves the directory where the Tailwind binary should be stored.
   *
   * Stored outside the module directory so it survives composer installs and
   * module updates. Follows the XDG Base Directory convention (~/.local/share),
   * the same approach used by Composer, npm, and pip.
   *
   * Resolution order:
   *   1. TAILWIND_BINARY_DIR env var — for Docker or custom setups,
   *      e.g. TAILWIND_BINARY_DIR=/mnt/cache/tailwindcss
   *   2. $XDG_DATA_HOME/tailwindcss/ — Linux standard for user data location,
   *      defaults to ~/.local/share but can be overridden by the system;
   *      e.g. XDG_DATA_HOME=/custom/data resolves to /custom/data/tailwindcss
   *   3. ~/.local/share/tailwindcss/ — default; works on shared hosting,
   *      GitHub Actions (cache path: ~/.local/share/tailwindcss),
   *      and GitLab CI out of the box.
   *
   * @return string
   *   Absolute path to the binary storage directory (no trailing slash).
   *
   * @throws \Exception
   *   If no storage directory can be determined. Set TAILWIND_BINARY_DIR.
   */
  protected function getBinaryDir(): string {
    // 1. Explicit env var override (Docker, unusual CI setups).
    if ($dir = getenv('TAILWIND_BINARY_DIR')) {
      return rtrim($dir, '/');
    }

    // 2. XDG Base Directory standard.
    if ($xdgDataHome = getenv('XDG_DATA_HOME')) {
      return rtrim($xdgDataHome, '/') . '/tailwindcss';
    }

    // 3. XDG default fallback: ~/.local/share/tailwindcss
    $home = getenv('HOME');
    if (!$home && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
      $pwEntry = posix_getpwuid(posix_geteuid());
      $home = $pwEntry['dir'] ?? NULL;
    }

    if ($home) {
      return rtrim($home, '/') . '/.local/share/tailwindcss';
    }

    throw new \Exception(
      'Cannot determine binary storage directory. ' .
      'Set the TAILWIND_BINARY_DIR environment variable to an absolute path.'
    );
  }

  /**
   * Returns the expected binary path for the current OS/Arch without checking
   * whether it exists.
   *
   * @return string
   *   The absolute path where the binary should be located.
   *
   * @throws \Exception
   *   If the OS/Arch is unsupported or no storage directory can be resolved.
   */
  public function getExpectedBinaryPath(): string {
    return $this->getBinaryDir() . '/' . $this->getBinaryFilename();
  }

  /**
   * Downloads the Tailwind CLI binary for the current OS/Arch from GitHub.
   *
   * @return string
   *   The absolute path to the downloaded binary.
   *
   * @throws \Exception
   *   If the download fails or the OS/Arch is unsupported.
   */
  public function downloadBinary(): string {
    $version = $this->resolveLatestVersion();
    $filename = $this->getBinaryFilename();
    $url = self::GITHUB_RELEASES_BASE_URL . '/' . $version . '/' . $filename;
    $destination = $this->getExpectedBinaryPath();

    $binDir = dirname($destination);
    if (!is_dir($binDir)) {
      mkdir($binDir, 0755, TRUE);
    }

    if (extension_loaded('curl')) {
      $this->downloadWithCurl($url, $destination);
    }
    elseif (ini_get('allow_url_fopen')) {
      $this->downloadWithFileGetContents($url, $destination);
    }
    else {
      throw new \Exception(
        'Cannot download binary: neither the cURL extension nor allow_url_fopen is available. ' .
        'Enable one of these or download the binary manually from: ' . $url
      );
    }

    chmod($destination, 0755);
    file_put_contents($binDir . '/.version', $version);

    return $destination;
  }

  /**
   * Resolves the latest Tailwind CSS release tag for the required major version
   * by querying the GitHub API.
   *
   * @return string
   *   A version tag, e.g. "v4.3.0".
   *
   * @throws \Exception
   *   If the API cannot be reached or no matching release is found.
   */
  public function resolveLatestVersion(): string {
    $url = self::GITHUB_API_RELEASES_URL . '?per_page=20';
    $requiredPrefix = 'v' . self::TAILWIND_MAJOR_VERSION . '.';
    $headers = ['User-Agent: tailwind-drush'];

    if (extension_loaded('curl')) {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_TIMEOUT, 15);
      $response = curl_exec($ch);
      $error = curl_error($ch);
      curl_close($ch);
      if ($error || $response === FALSE) {
        throw new \Exception("GitHub API request failed: $error");
      }
    }
    elseif (ini_get('allow_url_fopen')) {
      $context = stream_context_create([
        'http' => [
          'header' => implode("\r\n", $headers),
          'timeout' => 15,
        ],
      ]);
      $response = file_get_contents($url, FALSE, $context);
      if ($response === FALSE) {
        throw new \Exception("GitHub API request failed.");
      }
    }
    else {
      throw new \Exception(
        'Cannot resolve version: neither the cURL extension nor allow_url_fopen is available.'
      );
    }

    $releases = json_decode($response, TRUE);
    if (!is_array($releases)) {
      throw new \Exception("Unexpected response from GitHub API.");
    }

    foreach ($releases as $release) {
      $tag = $release['tag_name'] ?? '';
      if (str_starts_with($tag, $requiredPrefix) && empty($release['prerelease'])) {
        return $tag;
      }
    }

    throw new \Exception(
      "No stable v" . self::TAILWIND_MAJOR_VERSION . ".x release found on GitHub."
    );
  }

  /**
   * Resolves the binary filename for the current OS and architecture.
   *
   * @return string
   *   The binary filename, e.g. "tailwindcss-macos-arm64".
   *
   * @throws \Exception
   *   If the OS/Arch is unsupported.
   */
  protected function getBinaryFilename(): string {
    $os = php_uname('s');
    $arch = php_uname('m');

    if (stripos($os, 'darwin') !== FALSE) {
      $binaryOs = 'macos';
    }
    elseif (stripos($os, 'linux') !== FALSE) {
      $binaryOs = 'linux';
    }
    elseif (stripos($os, 'windows') !== FALSE || stripos($os, 'win') !== FALSE) {
      $binaryOs = 'windows';
    }
    else {
      throw new \Exception("Unsupported operating system: $os");
    }

    if ($arch === 'x86_64' || $arch === 'amd64') {
      $binaryArch = 'x64';
    }
    elseif ($arch === 'aarch64' || $arch === 'arm64') {
      $binaryArch = 'arm64';
    }
    elseif ($arch === 'armv7l') {
      $binaryArch = 'armv7';
    }
    else {
      throw new \Exception("Unsupported architecture: $arch");
    }

    $filename = "tailwindcss-{$binaryOs}-{$binaryArch}";
    if ($binaryOs === 'windows') {
      $filename .= '.exe';
    }

    return $filename;
  }

  /**
   * Downloads a URL to a local file using cURL.
   *
   * @throws \Exception
   *   If the download fails.
   */
  protected function downloadWithCurl(string $url, string $destination): void {
    $fp = fopen($destination, 'wb');
    if ($fp === FALSE) {
      throw new \Exception("Cannot open destination file for writing: $destination");
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_exec($ch);

    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($error || $httpCode >= 400) {
      @unlink($destination);
      throw new \Exception(
        "Failed to download binary from $url" .
        ($error ? ": $error" : " (HTTP $httpCode)")
      );
    }
  }

  /**
   * Downloads a URL to a local file using file_get_contents().
   *
   * @throws \Exception
   *   If the download fails.
   */
  protected function downloadWithFileGetContents(string $url, string $destination): void {
    $context = stream_context_create([
      'http' => [
        'follow_location' => 1,
        'timeout' => 120,
      ],
      'ssl' => [
        'verify_peer' => TRUE,
      ],
    ]);

    $content = file_get_contents($url, FALSE, $context);
    if ($content === FALSE) {
      throw new \Exception("Failed to download binary from $url");
    }

    if (file_put_contents($destination, $content) === FALSE) {
      throw new \Exception("Failed to write binary to $destination");
    }
  }

}
