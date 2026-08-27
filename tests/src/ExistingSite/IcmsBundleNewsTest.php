<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Iqual\IcmsTestSuite\ExistingSite\IcmsBundleContractTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract of the news bundle: node type, listing paragraph, GraphQL exposure.
 *
 * Skipped where icms_bundle_news_logic is not installed.
 */
#[Group('icms')]
#[Group('icms_bundle_news')]
class IcmsBundleNewsTest extends IcmsBundleContractTestBase {

  protected const ICMS_BUNDLE = 'news';

}
