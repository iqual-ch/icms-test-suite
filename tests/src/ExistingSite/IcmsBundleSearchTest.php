<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Iqual\IcmsTestSuite\ExistingSite\IcmsBundleExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract of the search bundle: the GraphQL search query the frontend uses.
 *
 * The bundle enables the `search_api_query` schema extension; the frontend's
 * search page and facets call `searchApiQuery` on it. Skipped where
 * graphql_search_api_query is not installed.
 */
#[Group('icms')]
#[Group('icms_bundle_search')]
#[Group('icms_search')]
class IcmsBundleSearchTest extends IcmsBundleExistingSiteBase {

  protected const ICMS_BUNDLE = 'search';

  /**
   * The search extension is enabled and answers a query on a content index.
   */
  public function testSearchApiQueryResolves(): void {
    $extensions = $this->getGraphqlServer()->get('schema_configuration')['core_composable']['extensions'] ?? [];
    $this->assertNotEmpty($extensions['search_api_query'] ?? NULL, 'The search_api_query schema extension is enabled on the GraphQL server.');

    $indexes = array_filter($this->getSearchIndexes(), static fn ($index) => $index->isValidDatasource('entity:node'));
    $this->assertNotEmpty($indexes, 'An enabled search_api index with a node datasource exists.');

    foreach ($indexes as $index) {
      $data = $this->graphqlQuery(<<<'GQL'
query search($indexId: String!) {
  searchApiQuery(indexId: $indexId, keys: "", limit: 1) {
    count
    facets { id }
    results { __typename }
  }
}
GQL, ['indexId' => $index->id()]);
      $this->assertIsInt($data['searchApiQuery']['count'] ?? NULL, "searchApiQuery on '{$index->id()}' returns a result count.");
      $this->assertIsArray($data['searchApiQuery']['results'] ?? NULL, "searchApiQuery on '{$index->id()}' returns a results list.");
    }
  }

}
