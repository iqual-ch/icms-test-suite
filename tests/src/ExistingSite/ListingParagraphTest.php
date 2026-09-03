<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract of the generic "Listing" layout paragraph.
 *
 * The listing paragraph is the one ICMS layout that spans content types: the
 * editor picks the types, the frontend filters the search index by them and
 * sorts on `title_sort`. The pieces live in three places — paragraph config,
 * GraphQL schema configuration and the Search API index — and an update that
 * loses any of them breaks listings silently (empty results, missing option,
 * unresolvable fragment). This pins the contract without HTTP round-trips.
 */
#[Group('icms')]
#[Group('icms_listing')]
class ListingParagraphTest extends IcmsExistingSiteBase {

  /**
   * The paragraph type exists with the fields the frontend fragment queries.
   */
  public function testParagraphTypeAndFieldsExist(): void {
    $this->assertNotNull(ParagraphsType::load('icms_layout_listing'), 'The icms_layout_listing paragraph type exists.');

    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'icms_layout_listing');
    foreach (['field_icms_content_types', 'field_icms_listing_type', 'field_icms_sorting', 'field_icms_topics', 'field_icms_title'] as $field_name) {
      $this->assertArrayHasKey($field_name, $definitions, "The listing paragraph has $field_name.");
    }
    $this->assertSame(-1, $definitions['field_icms_content_types']->getFieldStorageDefinition()->getCardinality(), 'Several content types can be listed at once.');
  }

  /**
   * Every content type can be picked, including project and bundle types.
   */
  public function testContentTypeOptionsCoverAllContentTypes(): void {
    $storage = FieldStorageConfig::loadByName('paragraph', 'field_icms_content_types');
    $this->assertNotNull($storage, 'The field_icms_content_types storage exists.');

    $options = options_allowed_values($storage);
    $this->assertEqualsCanonicalizing(array_keys(NodeType::loadMultiple()), array_keys($options), 'The content type options match the content types on the site.');
  }

  /**
   * The paragraph type and its fields are exposed in the GraphQL schema.
   */
  public function testGraphqlSchemaExposesTheListing(): void {
    $configuration = $this->getGraphqlServer()->get('schema_configuration')['core_composable'] ?? [];
    $this->assertNotEmpty($configuration['bundles']['paragraph']['icms_layout_listing']['enabled'] ?? FALSE, 'The icms_layout_listing bundle is enabled in the GraphQL schema.');
    foreach (['field_icms_content_types', 'field_icms_listing_type', 'field_icms_sorting', 'field_icms_topics'] as $field_name) {
      $this->assertSame($field_name, $configuration['fields']['paragraph'][$field_name] ?? NULL, "$field_name is exposed on paragraphs in the GraphQL schema.");
    }
  }

  /**
   * The content search index carries the fields the listing filters and sorts on.
   *
   * The frontend queries the `content_search` index by id, filters on `type`
   * and sorts on `weight` and `title_sort`; a missing field turns into an
   * empty or unsorted listing without any error.
   */
  public function testContentSearchIndexProvidesListingFields(): void {
    $index = $this->getSearchIndexes()['content_search'] ?? NULL;
    if (!$index) {
      $this->markTestSkipped('No enabled content_search index exists.');
    }
    $fields = $index->getFields();
    $this->assertArrayHasKey('type', $fields, 'content_search indexes the content type the listing filters on.');
    $this->assertArrayHasKey('weight', $fields, 'content_search indexes the weight the listing sorts on.');
    $this->assertArrayHasKey('title_sort', $fields, 'content_search indexes the sortable title.');
    $this->assertSame('string', $fields['title_sort']->getType(), 'title_sort is a string field so it can be sorted on.');
  }

}
