<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\Core\Database\Connection;
use Drupal\system\Entity\Menu;
use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Smoke-tests the GraphQL contract the Nuxt frontend bootstraps from.
 *
 * Every page render starts with the frontend's `initData` query (menus,
 * global config, translations — `@iqual/nuxt-icms/queries/query.initData.graphql`).
 * This executes an initData-shaped query for every site language and asserts
 * the response shape is intact: HTTP 200, no GraphQL errors, menus resolve to
 * link trees, the global config page and the texts loader resolve.
 *
 * Catches regressions from graphql_core_schema/extension updates that
 * silently change or drop parts of the generated schema (renamed fields,
 * removed extensions, broken menu enums) before they take the frontend down.
 * Content is not asserted: menus may legitimately be empty (projects with
 * per-brand menus) and config values are editorial.
 */
#[Group('icms')]
#[Group('icms_graphql')]
class GraphQlInitDataContractTest extends IcmsExistingSiteBase {

  /**
   * Menus the frontend's initData query loads, by response alias.
   *
   * @var string[]
   */
  protected const MENUS = [
    'metaMenu' => 'meta',
    'mainMenu' => 'main',
    'footerMenu' => 'footer',
    'footerMetaMenu' => 'footer-meta',
  ];

  /**
   * Key and context of the text the query reads from the texts loader.
   */
  protected const SMOKE_TEXT_KEY = 'homepage_link';
  protected const SMOKE_TEXT_CONTEXT = 'error_page';

  /**
   * The smoke text's database rows before the query; NULL without texts.
   *
   * @var array[]|null
   */
  protected ?array $smokeTextRows = NULL;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->smokeTextRows === NULL) {
      parent::tearDown();
      return;
    }

    // getText() creates a missing text with its default (drupal/texts), in
    // the database of the site under test. Remove one the query created; an
    // existing text is left alone.
    if (!$this->smokeTextRows) {
      $text = \Drupal::entityTypeManager()->getStorage('texts')->loadByKey(static::SMOKE_TEXT_KEY, static::SMOKE_TEXT_CONTEXT);
      if ($text) {
        $this->markEntityForCleanup($text);
      }
    }
    // The kernel is shut down after the cleanup, so keep the connection.
    $database = \Drupal::database();
    parent::tearDown();

    $this->assertSame($this->smokeTextRows, $this->loadSmokeTextRows($database), 'The texts query leaves the texts as it found them.');
  }

  /**
   * The initData response shape is intact for every language.
   */
  public function testInitDataShapeIsIntactForEveryLanguage(): void {
    $menus = array_filter(static::MENUS, static fn (string $id) => Menu::load($id) !== NULL);
    $this->assertNotEmpty($menus, 'At least one of the ICMS menus exists.');
    $globalConfig = \Drupal::moduleHandler()->moduleExists('translatable_config_pages')
      && \Drupal::entityTypeManager()->getStorage('translatable_config_pages_type')->load('global') !== NULL;
    $extensions = $this->getGraphqlServer()->get('schema_configuration')['core_composable']['extensions'] ?? [];
    $texts = !empty($extensions['texts']);
    if ($texts) {
      $this->smokeTextRows = $this->loadSmokeTextRows(\Drupal::database());
    }

    foreach ($this->getSiteLangcodes() as $langcode) {
      $data = $this->graphqlQuery($this->buildInitDataQuery($menus, $globalConfig, $texts), [], $langcode);

      foreach (array_keys($menus) as $alias) {
        $this->assertIsArray($data[$alias] ?? NULL, "[$langcode] Menu '$alias' resolves.");
        $this->assertIsArray($data[$alias]['links'] ?? NULL, "[$langcode] Menu '$alias' has a links list.");
        foreach ($data[$alias]['links'] as $delta => $item) {
          $this->assertNotEmpty($item['link']['label'] ?? NULL, "[$langcode] $alias link #$delta has a label.");
          $this->assertArrayHasKey('path', $item['link']['url'] ?? [], "[$langcode] $alias link #$delta has a url path.");
        }
      }

      if ($globalConfig) {
        // The config page entity only exists once an editor saved it (a fresh
        // install has none), so the field resolving without errors is the
        // contract, not a non-null value.
        $this->assertArrayHasKey('globalConfig', $data, "[$langcode] The global config page field resolves.");
      }

      if ($texts) {
        // getText() returns at least the default, so a non-empty string
        // proves the texts extension resolves.
        $this->assertNotEmpty($data['translations']['smokeText'] ?? NULL, "[$langcode] textsLoader.getText() resolves.");
      }
    }
  }

  /**
   * Builds the initData-shaped contract query.
   *
   * @param string[] $menus
   *   Menu ids by response alias.
   * @param bool $globalConfig
   *   Whether to query the global config page.
   * @param bool $texts
   *   Whether to query the texts loader.
   */
  protected function buildInitDataQuery(array $menus, bool $globalConfig, bool $texts): string {
    $fields = [];
    foreach ($menus as $alias => $id) {
      // MenuName enum values are the menu ids, upper-cased with `-` → `_`.
      $enum = strtoupper(str_replace('-', '_', $id));
      $fields[] = "  $alias: menuByName(name: $enum) { links { ...menuLinkTree subtree { ...menuLinkTree } } }";
    }
    if ($globalConfig) {
      $fields[] = '  globalConfig { __typename }';
    }
    if ($texts) {
      $fields[] = sprintf('  translations: textsLoader { smokeText: getText(key: "%s", context: "%s", default: "Homepage") }', static::SMOKE_TEXT_KEY, static::SMOKE_TEXT_CONTEXT);
    }
    $fields = implode("\n", $fields);

    return <<<GQL
query initDataContract {
$fields
}

fragment menuLinkTree on MenuLinkTreeElement {
  link {
    label
    url { path }
  }
}
GQL;
  }

  /**
   * Loads the smoke text's rows in every language, straight from the table.
   *
   * @return array[]
   *   Rows with the id, language, translation and plural flag.
   */
  protected function loadSmokeTextRows(Connection $database): array {
    return $database->select('texts_field_data', 't')
      ->fields('t', ['id', 'langcode', 'translation', 'plural'])
      ->condition('t.key', static::SMOKE_TEXT_KEY)
      ->condition('t.context', static::SMOKE_TEXT_CONTEXT)
      ->orderBy('t.id')
      ->orderBy('t.langcode')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

}
