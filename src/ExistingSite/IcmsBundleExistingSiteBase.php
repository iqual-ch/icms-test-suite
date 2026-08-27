<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\ExistingSite;

/**
 * Base class for tests of a single ICMS bundle.
 *
 * Subclasses set `ICMS_BUNDLE` and carry `#[Group('icms_bundle_<name>')]`; the
 * test is skipped wherever the bundle is not installed.
 */
abstract class IcmsBundleExistingSiteBase extends IcmsExistingSiteBase {

  /**
   * The bundle name without prefix, e.g. `news`.
   */
  protected const ICMS_BUNDLE = '';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->requireIcmsBundle(static::ICMS_BUNDLE);
  }

}
