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

}
