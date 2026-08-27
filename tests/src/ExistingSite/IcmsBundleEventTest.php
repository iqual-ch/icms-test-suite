<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Iqual\IcmsTestSuite\ExistingSite\IcmsBundleContractTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract of the event bundle: node type, listing paragraph, GraphQL exposure.
 *
 * Skipped where icms_bundle_event_logic is not installed.
 */
#[Group('icms')]
#[Group('icms_bundle_event')]
class IcmsBundleEventTest extends IcmsBundleContractTestBase {

  protected const ICMS_BUNDLE = 'event';

  /**
   * {@inheritdoc}
   *
   * An event requires at least one occurrence paragraph with a date.
   */
  protected function getNodeValues(): array {
    $start = strtotime('tomorrow 10:00');
    $occurrence = $this->createIcmsParagraph('icms_event_occurrence', [
      'field_icms_date' => [
        'value' => $start,
        'end_value' => $start + 3600,
        'duration' => 60,
      ],
    ]);
    return ['field_icms_event_occurrence' => [$occurrence]];
  }

}
