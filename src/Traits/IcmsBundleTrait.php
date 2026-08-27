<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Traits;

/**
 * Guards bundle tests so they only run where the bundle is installed.
 *
 * A bundle is "installed" when its companion `icms_bundle_<name>_logic` module
 * is enabled — that module is part of every bundle recipe and is auto-enabled
 * on existing sites by icms_core_logic, so it is the reliable marker for both
 * fresh and upgraded installations.
 */
trait IcmsBundleTrait {

  /**
   * Marker modules for bundles that ship no `_logic` module of their own.
   *
   * Recipe-only bundles leave no trace once applied; the module their recipe
   * installs is the next best indicator.
   */
  protected const ICMS_BUNDLE_MARKERS = [
    'search' => 'graphql_search_api_query',
  ];

  /**
   * Whether an ICMS bundle is installed on this site.
   *
   * @param string $bundle
   *   The bundle name without prefix, e.g. `news`.
   */
  protected function isIcmsBundleInstalled(string $bundle): bool {
    $module = static::ICMS_BUNDLE_MARKERS[$bundle] ?? "icms_bundle_{$bundle}_logic";
    return \Drupal::moduleHandler()->moduleExists($module);
  }

  /**
   * Skips the current test unless the given ICMS bundle is installed.
   *
   * @param string $bundle
   *   The bundle name without prefix, e.g. `news`.
   */
  protected function requireIcmsBundle(string $bundle): void {
    if (!$this->isIcmsBundleInstalled($bundle)) {
      $module = static::ICMS_BUNDLE_MARKERS[$bundle] ?? "icms_bundle_{$bundle}_logic";
      $this->markTestSkipped("The ICMS bundle '$bundle' is not installed ($module is not enabled).");
    }
  }

}
