<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\node\NodeInterface;
use Iqual\IcmsTestSuite\ExistingSite\IcmsGraphQlExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Smoke test for the GraphQL endpoint.
 *
 * A page created through the entity API must come back through the schema
 * with the base fields the frontend relies on, with the expected cache
 * metadata. This indirectly verifies that the schema still generates for the
 * `icms_page` bundle after core/contrib updates.
 */
#[Group('icms')]
class GraphQlSmokeTest extends IcmsGraphQlExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser(permissions: ['access content']);
  }

  /**
   * A page node can be queried by UUID.
   */
  public function testEntityCanBeQueried(): void {
    $node = $this->createIcmsNode();

    $metadata = $this->defaultCacheMetaData();
    $metadata->addCacheableDependency($node);
    $metadata->addCacheableDependency($this->server);
    $metadata->addCacheContexts([
      'static:language:' . $node->language()->getId(),
      // Added by the tmgmt module.
      'url.query_args:key',
    ]);

    $this->assertResults(
      $this->getQueryFromFile('query.get_icms_page_by_uuid.graphql'),
      ['uuid' => $node->uuid()],
      $this->getExpectedResults($node),
      $metadata
    );
  }

  /**
   * Returns the expected query result for a node.
   */
  protected function getExpectedResults(NodeInterface $node): array {
    return [
      'entityByUuid' => [
        'uuid' => $node->uuid(),
        'changed' => date(DATE_ATOM, (int) $node->getChangedTime()),
        'created' => date(DATE_ATOM, (int) $node->getCreatedTime()),
        'langcode' => $node->language()->getId(),
        'nid' => (int) $node->id(),
        'status' => $node->isPublished(),
        'title' => $node->getTitle(),
        'vid' => (int) $node->get('vid')->value,
      ],
    ];
  }

}
