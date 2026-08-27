<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\Utility;
use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Smoke tests for the Search API content indexes.
 *
 * Search underpins every ICMS listing and facet component (through
 * graphql_search_api_query) and has broken repeatedly on updates: a disabled
 * index or server, a broken datasource, or a processor failing during
 * indexing all silently empty the frontend listings. This catches those at
 * the Search API level — wiring, indexing a single item and finding it again
 * — without any HTTP round-trip and without a site-wide reindex.
 *
 * Every enabled index whose node datasource covers `icms_page` is tested;
 * the backend is not asserted (database, Elasticsearch, … are all valid).
 */
#[Group('icms')]
#[Group('icms_search')]
class SearchIndexTest extends IcmsExistingSiteBase {

  /**
   * Indexes and their servers are enabled and wired up.
   */
  public function testIndexesAndServersAreEnabled(): void {
    foreach ($this->getPageIndexes() as $index) {
      $server = $index->getServerInstance();
      $this->assertNotNull($server, "The index '{$index->id()}' is attached to a server.");
      $this->assertTrue($server->status(), "The server '{$server->id()}' of index '{$index->id()}' is enabled.");
      $this->assertTrue($server->hasValidBackend(), "The server '{$server->id()}' has a valid backend.");
    }
  }

  /**
   * A new published page can be indexed and found again.
   *
   * Indexes exactly one item (never a site-wide reindex) and asserts a
   * fulltext query for its unique title token returns it — exercising
   * tracking, all processors (rendered_item included) and the query path the
   * GraphQL search uses.
   */
  public function testNewPageIsIndexedAndFindable(): void {
    $token = 'searchsmoke' . bin2hex(random_bytes(6));
    $node = $this->createIcmsNode(['title' => 'Search index smoke ' . $token]);

    foreach ($this->getPageIndexes() as $index) {
      $itemId = Utility::createCombinedId('entity:node', $node->id() . ':' . $node->language()->getId());
      $items = $index->loadItemsMultiple([$itemId]);
      $this->assertArrayHasKey($itemId, $items, "The page loads through the node datasource of index '{$index->id()}'.");
      $this->assertContains($itemId, $index->indexSpecificItems($items), "The page is indexed into '{$index->id()}'.");

      $results = $index->query()->keys($token)->execute();
      $this->assertContains($itemId, array_keys($results->getResultItems()), "The page is found in '{$index->id()}' by its unique title token.");
    }
  }

  /**
   * Returns the enabled indexes whose node datasource covers `icms_page`.
   *
   * Skips the test if search_api or such an index is missing.
   *
   * @return \Drupal\search_api\IndexInterface[]
   *   The indexes, keyed by id.
   */
  protected function getPageIndexes(): array {
    $indexes = array_filter($this->getSearchIndexes(), static function (IndexInterface $index) {
      if (!$index->isValidDatasource('entity:node')) {
        return FALSE;
      }
      $bundles = $index->getDatasource('entity:node')->getConfiguration()['bundles'] ?? ['default' => TRUE, 'selected' => []];
      $selected = in_array('icms_page', $bundles['selected'] ?? [], TRUE);
      return !empty($bundles['default']) ? !$selected : $selected;
    });
    if (!$indexes) {
      $this->markTestSkipped('No enabled search_api index with a node datasource covering icms_page exists.');
    }
    return $indexes;
  }

}
