<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\paragraphs\Entity\ParagraphsType;
use Iqual\IcmsTestSuite\ExistingSite\IcmsGraphQlExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the blökkli paragraph library and fragments contract.
 *
 * Reusable blocks are `from_library` paragraphs wrapping a library item;
 * fragments are `blokkli_fragment` paragraphs naming a frontend component.
 * The frontend's `paragraphFromLibrary` and `paragraphBlokkliFragment`
 * fragments read `fieldReusableParagraph { paragraphs }` and
 * `fieldBlokkliFragmentName` — a schema change on either side leaves the
 * editor placing blocks that render nothing.
 *
 * Host bundle, paragraph field and promotable paragraph type are discovered
 * from the site's configuration; the library must be usable on at least one
 * blökkli host, not on every field a project may have added.
 */
#[Group('icms')]
#[Group('icms_blokkli')]
class ParagraphLibraryTest extends IcmsGraphQlExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser(permissions: ['access content']);
  }

  /**
   * The library is configured: promotable types, host field and permissions.
   */
  public function testLibraryIsConfigured(): void {
    $this->assertNotEmpty($this->getPromotableParagraphTypes(), 'At least one paragraph type can be promoted to the library.');
    $this->assertNotNull($this->getLibraryHostField(), 'At least one blökkli host paragraph field allows from_library and blokkli_fragment paragraphs.');

    $editors = array_filter($this->getEditorRoles(), static fn ($role) =>
      $role->hasPermission('create paragraph library item') && $role->hasPermission('edit paragraph library item')
    );
    $this->assertNotEmpty($editors, 'At least one editor role can create and edit library items.');
  }

  /**
   * Library items carry a translation per language on multilingual sites.
   */
  public function testLibraryItemsAreTranslatable(): void {
    if (!\Drupal::moduleHandler()->moduleExists('content_translation') || count($this->getSiteLangcodes()) < 2) {
      $this->markTestSkipped('The site is monolingual.');
    }
    $settings = ContentLanguageSettings::loadByEntityTypeBundle('paragraphs_library_item', 'paragraphs_library_item');
    $this->assertTrue((bool) $settings->getThirdPartySetting('content_translation', 'enabled', FALSE), 'Library items are translatable.');
  }

  /**
   * A reusable block and a fragment resolve through GraphQL as the frontend expects.
   */
  public function testReusableBlockAndFragmentResolve(): void {
    $field = $this->getLibraryHostField();
    if (!$field) {
      $this->markTestSkipped('No blökkli host paragraph field allows from_library and blokkli_fragment paragraphs.');
    }
    $types = $this->getPromotableParagraphTypes();
    $this->assertNotEmpty($types);

    $block = $this->createIcmsParagraph(reset($types));
    $item = \Drupal::entityTypeManager()->getStorage('paragraphs_library_item')->create([
      'label' => $this->getRandomGenerator()->sentences(2),
      'paragraphs' => $block,
    ]);
    $item->save();
    $this->markEntityForCleanup($item);

    $reusable = $this->createIcmsParagraph('from_library', ['field_reusable_paragraph' => $item]);
    // The name is resolved to a component by the frontend; Drupal accepts any
    // string, so the contract asserted here is that it survives the schema.
    $fragment = $this->createIcmsParagraph('blokkli_fragment', ['field_blokkli_fragment_name' => 'test_fragment']);
    $node = $this->createIcmsNode([
      'type' => $field->getTargetBundle(),
      $field->getName() => [$reusable, $fragment],
    ]);

    $type = $this->toGraphqlName($field->getTargetBundle(), TRUE);
    $fieldName = $this->toGraphqlName($field->getName());
    $query = <<<GQL
      query (\$uuid: String!) {
        entityByUuid(entityType: NODE, uuid: \$uuid) {
          ... on Node$type {
            paragraphs: $fieldName {
              entityBundle
              ... on ParagraphFromLibrary {
                fieldReusableParagraph {
                  uuid
                  label
                  paragraphs {
                    uuid
                    entityBundle
                  }
                }
              }
              ... on ParagraphBlokkliFragment {
                fieldBlokkliFragmentName
              }
            }
          }
        }
      }
      GQL;

    $result = $this->getQueryResult($query, ['uuid' => $node->uuid()]);
    $this->assertSame([], $result->errors, 'GraphQL errors: ' . json_encode($result->errors));
    $this->assertEquals([
      [
        'entityBundle' => 'from_library',
        'fieldReusableParagraph' => [
          'uuid' => $item->uuid(),
          'label' => $item->label(),
          'paragraphs' => [
            'uuid' => $block->uuid(),
            'entityBundle' => $block->bundle(),
          ],
        ],
      ],
      [
        'entityBundle' => 'blokkli_fragment',
        'fieldBlokkliFragmentName' => 'test_fragment',
      ],
    ], $result->data['entityByUuid']['paragraphs'] ?? NULL);
  }

  /**
   * Returns the paragraph types that can be promoted to the library.
   *
   * @return string[]
   *   The paragraph type ids.
   */
  protected function getPromotableParagraphTypes(): array {
    return array_keys(array_filter(ParagraphsType::loadMultiple(), static fn (ParagraphsType $type) =>
      (bool) $type->getThirdPartySetting('paragraphs_library', 'allow_library_conversion', FALSE)
    ));
  }

  /**
   * Returns the first blökkli host paragraph field allowing both wrapper bundles.
   */
  protected function getLibraryHostField(): ?FieldDefinitionInterface {
    foreach ($this->getBlokkliHostBundles()['node'] ?? [] as $bundle) {
      foreach ($this->getParagraphFields('node', $bundle) as $field) {
        $allowed = $field->getSetting('handler_settings')['target_bundles'] ?? [];
        if (isset($allowed['from_library'], $allowed['blokkli_fragment'])) {
          return $field;
        }
      }
    }
    return NULL;
  }

  /**
   * Converts a machine name to its graphql_core_schema name.
   *
   * `field_icms_paragraphs` → `fieldIcmsParagraphs`, `icms_page` → `IcmsPage`.
   */
  protected function toGraphqlName(string $machine_name, bool $pascal = FALSE): string {
    $name = str_replace('_', '', ucwords($machine_name, '_'));
    return $pascal ? $name : lcfirst($name);
  }

}
