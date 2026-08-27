<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\ExistingSite;

use Iqual\IcmsTestSuite\Traits\IcmsBundleTrait;
use Iqual\IcmsTestSuite\Traits\IcmsContentCreationTrait;
use Iqual\IcmsTestSuite\Traits\IcmsGraphQlHttpTrait;
use Iqual\IcmsTestSuite\Traits\IcmsSiteDiscoveryTrait;
use Iqual\IcmsTestSuite\Traits\IcmsSkipTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base class for the suite's existing-site tests.
 *
 * Runs against the real site (SPOT database in CI, the local site in DDEV).
 * DTT fails the test on any PHP error logged to watchdog while it runs.
 * Project-level tests may extend this class as well to reuse the discovery
 * and GraphQL helpers.
 */
abstract class IcmsExistingSiteBase extends ExistingSiteBase {

  use IcmsBundleTrait;
  use IcmsContentCreationTrait;
  use IcmsGraphQlHttpTrait;
  use IcmsSiteDiscoveryTrait;
  use IcmsSkipTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->setUpBaseUrl();
    parent::setUp();
    $this->skipIfExcludedBySettings();
  }

  /**
   * Points the test browser at the site's public URL inside DDEV.
   *
   * The DDEV Chrome add-on (`make tool-chrome`) overrides DTT_BASE_URL with
   * `http://web` so the Selenium container can reach the site. Drupal builds
   * its redirects and session cookies for the public https URL, so over
   * `http://web` every redirect breaks and no login sticks. These tests don't
   * drive a browser, so they use the public URL whenever DDEV provides one.
   */
  protected function setUpBaseUrl(): void {
    $primary = getenv('DDEV_PRIMARY_URL');
    $configured = getenv('DTT_BASE_URL');
    if (getenv('IS_DDEV_PROJECT') === 'true' && $primary && (!$configured || parse_url($configured, PHP_URL_HOST) === 'web')) {
      $this->baseUrl = $primary;
    }
  }

}
