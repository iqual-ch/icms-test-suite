<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Iqual\IcmsTestSuite\ExistingSite\IcmsBundleContractTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract of the people bundle: node type, listing paragraph, GraphQL exposure.
 *
 * Skipped where icms_bundle_people_logic is not installed.
 */
#[Group('icms')]
#[Group('icms_bundle_people')]
class IcmsBundlePeopleTest extends IcmsBundleContractTestBase {

  protected const ICMS_BUNDLE = 'people';

}
