<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Iqual\IcmsTestSuite\ExistingSite\IcmsBundleContractTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract of the location bundle: node type, listing paragraph, GraphQL exposure.
 *
 * Skipped where icms_bundle_location_logic is not installed.
 */
#[Group('icms')]
#[Group('icms_bundle_location')]
class IcmsBundleLocationTest extends IcmsBundleContractTestBase {

  protected const ICMS_BUNDLE = 'location';

  /**
   * {@inheritdoc}
   *
   * A location requires an address and coordinates.
   */
  protected function getNodeValues(): array {
    return [
      'field_icms_address' => [
        'country_code' => 'CH',
        'address_line1' => 'Bahnhofstrasse 1',
        'postal_code' => '8001',
        'locality' => 'Zürich',
      ],
      'field_icms_coordinates' => ['lat' => 47.3769, 'lng' => 8.5417],
    ];
  }

}
