<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Iqual\IcmsTestSuite\ExistingSite\IcmsBundleContractTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract of the product bundle: node type, listing paragraph, GraphQL exposure.
 *
 * Skipped where icms_bundle_product_logic is not installed.
 */
#[Group('icms')]
#[Group('icms_bundle_product')]
class IcmsBundleProductTest extends IcmsBundleContractTestBase {

  protected const ICMS_BUNDLE = 'product';

}
