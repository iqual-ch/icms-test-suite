<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract of the "Anchor" and "Anchor navigation" layout paragraphs.
 *
 * The anchor marks a scroll target inside the content, the anchor navigation
 * lists every anchor on the page. Neither is tied to a content type, so both
 * have to stay available on every node type that has a paragraphs field — a
 * bundle installed later must not end up without them. The frontend reads the
 * anchors from the block list, so a paragraph type or field missing from the
 * GraphQL schema leaves the navigation silently empty.
 */
#[Group('icms')]
#[Group('icms_anchor')]
class AnchorParagraphTest extends IcmsExistingSiteBase {

  /**
   * The bundles both paragraphs are registered under.
   */
  private const BUNDLES = [
    'icms_layout_anchor',
    'icms_layout_anchor_navigation',
  ];

  /**
   * Both paragraph types exist, and the anchor has its label and anchor field.
   */
  public function testParagraphTypesAndFieldsExist(): void {
    foreach (self::BUNDLES as $bundle) {
      $this->assertNotNull(ParagraphsType::load($bundle), "The $bundle paragraph type exists.");
    }

    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'icms_layout_anchor');
    $this->assertArrayHasKey('field_icms_title', $definitions, 'The anchor paragraph has field_icms_title, the label shown in the navigation.');
    $this->assertTrue($definitions['field_icms_title']->isRequired(), 'The label is required, the navigation has nothing to show without it.');
    $this->assertArrayHasKey('field_icms_anchor', $definitions, 'The anchor paragraph has field_icms_anchor, the optional URL fragment.');
    $this->assertFalse($definitions['field_icms_anchor']->isRequired(), 'The anchor is optional, the frontend generates it from the label.');
    $this->assertArrayNotHasKey('field_icms_link', $definitions, 'The anchor paragraph no longer carries a link field.');
  }

  /**
   * The anchor field is exposed in the GraphQL schema.
   */
  public function testGraphqlSchemaExposesTheAnchorField(): void {
    $configuration = $this->getGraphqlServer()->get('schema_configuration')['core_composable'] ?? [];

    $this->assertSame('field_icms_anchor', $configuration['fields']['paragraph']['field_icms_anchor'] ?? NULL, 'field_icms_anchor is enabled in the GraphQL schema.');
  }

  /**
   * Both paragraphs can be placed on every content type with a paragraphs field.
   */
  public function testAnchorsAreAvailableOnEveryContentType(): void {
    $checked = [];

    foreach (array_keys(NodeType::loadMultiple()) as $type) {
      $field = FieldConfig::loadByName('node', $type, 'field_icms_paragraphs');
      if (!$field) {
        continue;
      }

      $settings = $field->getSetting('handler_settings');
      foreach (self::BUNDLES as $bundle) {
        $this->assertArrayHasKey($bundle, $settings['target_bundles'] ?? [], "$bundle can be placed on $type.");
        $this->assertNotEmpty($settings['target_bundles_drag_drop'][$bundle]['enabled'] ?? FALSE, "$bundle is offered in the $type block library.");
      }
      $checked[] = $type;
    }

    $this->assertNotEmpty($checked, 'At least one content type has a paragraphs field.');
  }

  /**
   * Both paragraph types are exposed in the GraphQL schema.
   */
  public function testGraphqlSchemaExposesTheAnchors(): void {
    $configuration = $this->getGraphqlServer()->get('schema_configuration')['core_composable'] ?? [];

    foreach (self::BUNDLES as $bundle) {
      $this->assertNotEmpty($configuration['bundles']['paragraph'][$bundle]['enabled'] ?? FALSE, "The $bundle bundle is enabled in the GraphQL schema.");
    }
  }

}
