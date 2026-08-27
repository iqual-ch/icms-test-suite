<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Traits;

use Drupal\Core\Site\Settings;

/**
 * Lets a project skip suite tests it deliberately deviates from.
 *
 * The suite asserts the ICMS contract as shipped. A project that intentionally
 * customizes part of that contract documents the deviation in its settings
 * (settings.local.php is the file both DDEV and CI load) instead of turning
 * the suite red:
 *
 * @code
 * $settings['icms_test_suite']['skip'] = [
 *   // Whole groups (`#[Group]` attributes of the suite's tests).
 *   'groups' => [
 *     'icms_search' => 'Search is provided by Algolia, not search_api (PROJ-12).',
 *     'icms_bundle_news' => 'The news bundle is heavily customized (PROJ-34).',
 *   ],
 *   // Individual test classes, or single methods as `Class::method`.
 *   'tests' => [
 *     'Iqual\IcmsTestSuite\Tests\ExistingSite\NuxtCacheHeadersTest' => 'No Varnish in front of this site.',
 *     'Iqual\IcmsTestSuite\Tests\ExistingSite\GraphQlSmokeTest::testEntityCanBeQueried' => 'PROJ-56',
 *   ],
 * ];
 * @endcode
 *
 * Every entry needs a reason — it is printed with the skip, so the CI output
 * documents why a part of the contract is not verified on this project.
 */
trait IcmsSkipTrait {

  /**
   * Skips the current test if the project's settings opt out of it.
   *
   * Call this after the Drupal kernel has booted (i.e. after parent::setUp()).
   */
  protected function skipIfExcludedBySettings(): void {
    $skip = Settings::get('icms_test_suite', [])['skip'] ?? [];
    $reason = $this->getSettingsSkipReason($skip);
    if ($reason !== NULL) {
      $this->markTestSkipped("Skipped by \$settings['icms_test_suite']: $reason");
    }
  }

  /**
   * Returns the configured skip reason for the current test, if any.
   *
   * @param array $skip
   *   The `skip` section of `$settings['icms_test_suite']`.
   *
   * @return string|null
   *   The reason, or NULL if the test is not excluded.
   */
  protected function getSettingsSkipReason(array $skip): ?string {
    $tests = $skip['tests'] ?? [];
    foreach ([static::class . '::' . $this->name(), static::class] as $key) {
      if (isset($tests[$key])) {
        return $this->formatSkipReason($tests[$key]);
      }
    }

    $groups = $skip['groups'] ?? [];
    foreach ($this->groups() as $group) {
      if (isset($groups[$group])) {
        return $this->formatSkipReason($groups[$group]) . " (group '$group')";
      }
    }

    return NULL;
  }

  /**
   * Accepts both `key => reason` and bare `key` list entries.
   */
  private function formatSkipReason(mixed $reason): string {
    return is_string($reason) && $reason !== '' ? $reason : 'no reason given';
  }

}
