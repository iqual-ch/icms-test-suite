<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\ExistingSite;

use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * The contract every content bundle shares.
 *
 * A content bundle (news, people, event, …) adds a node type `icms_<name>`
 * that is a blökkli host, a listing paragraph `icms_layout_<name>` and the
 * GraphQL types for both. Bundle test classes extend this, set `ICMS_BUNDLE`
 * and carry `#[Group('icms_bundle_<name>')]`; they add bundle-specific
 * assertions where the bundle has more contract than that.
 */
abstract class IcmsBundleContractTestBase extends IcmsBundleExistingSiteBase {

  /**
   * The bundle's node type.
   */
  protected function getNodeType(): string {
    return 'icms_' . static::ICMS_BUNDLE;
  }

  /**
   * The bundle's listing paragraph type.
   */
  protected function getListingParagraphType(): string {
    return 'icms_layout_' . static::ICMS_BUNDLE;
  }

  /**
   * The node type exists and is a blökkli host with a paragraph field.
   */
  public function testNodeTypeIsBlokkliHost(): void {
    $type = $this->getNodeType();
    $this->assertNotNull(NodeType::load($type), "The node type '$type' exists.");
    $this->assertContains($type, $this->getBlokkliHostBundles()['node'] ?? [], "blökkli is enabled for '$type'.");
    $this->assertNotEmpty($this->getParagraphFields('node', $type), "'$type' has a paragraph reference field.");
  }

  /**
   * The listing paragraph type exists.
   */
  public function testListingParagraphTypeExists(): void {
    $type = $this->getListingParagraphType();
    $this->assertNotNull(ParagraphsType::load($type), "The paragraph type '$type' exists.");
  }

  /**
   * A published node of the bundle is exposed through GraphQL like Nuxt sees it.
   */
  public function testNodeIsExposedThroughGraphql(): void {
    $node = $this->createIcmsNode(['type' => $this->getNodeType()]);
    $data = $this->graphqlQuery(<<<'GQL'
query nodeByUuid($uuid: String!) {
  entityByUuid(entityType: NODE, uuid: $uuid) {
    __typename
    uuid
    ... on Node { title }
  }
}
GQL, ['uuid' => $node->uuid()]);

    $expectedType = 'Node' . str_replace('_', '', ucwords($this->getNodeType(), '_'));
    $this->assertSame($expectedType, $data['entityByUuid']['__typename'] ?? NULL, "The node resolves to the GraphQL type '$expectedType'.");
    $this->assertSame($node->uuid(), $data['entityByUuid']['uuid'] ?? NULL);
    $this->assertSame($node->getTitle(), $data['entityByUuid']['title'] ?? NULL);
  }

}
