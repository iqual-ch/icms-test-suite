<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Traits;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql\Entity\ServerInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\IndexInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Discovers the site's ICMS configuration instead of hard-coding it.
 *
 * Client projects add languages, host bundles, roles and indexes — tests that
 * iterate over what the site actually has stay green on every project, while
 * tests that assume the starterkit's exact set break on the first
 * customization.
 */
trait IcmsSiteDiscoveryTrait {

  /**
   * Returns the configured (non-locked) langcodes, default language first.
   *
   * @return string[]
   *   The langcodes.
   */
  protected function getSiteLangcodes(): array {
    $languages = \Drupal::languageManager()->getLanguages();
    return array_keys($languages);
  }

  /**
   * Returns the default langcode.
   */
  protected function getDefaultLangcode(): string {
    return \Drupal::languageManager()->getDefaultLanguage()->getId();
  }

  /**
   * Returns the URL path prefix of a language (without slashes).
   *
   * Follows `language.negotiation` so a project that serves its default
   * language without prefix (or with a different one) still gets the right
   * URLs.
   */
  protected function getLanguagePathPrefix(string $langcode): string {
    $prefixes = \Drupal::config('language.negotiation')->get('url.prefixes') ?? [];
    return $prefixes[$langcode] ?? $langcode;
  }

  /**
   * Returns the entity bundles blökkli is enabled for.
   *
   * @return array<string, string[]>
   *   Bundles keyed by entity type id, e.g. `['node' => ['icms_page']]`.
   */
  protected function getBlokkliHostBundles(): array {
    $types = \Drupal::config('paragraphs_blokkli.settings')->get('enabled_types') ?? [];
    return array_filter(array_map(fn ($bundles) => array_values((array) $bundles), $types));
  }

  /**
   * Returns the paragraph reference fields of a blökkli host bundle.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Entity reference revisions fields targeting paragraphs, keyed by name.
   */
  protected function getParagraphFields(string $entity_type_id, string $bundle): array {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
    return array_filter($definitions, static fn (FieldDefinitionInterface $definition) =>
      $definition->getType() === 'entity_reference_revisions'
      && $definition->getSetting('target_type') === 'paragraph'
    );
  }

  /**
   * Returns the non-admin roles that can use the blökkli editor.
   *
   * The starterkit ships `content_editor`; projects rename it, add more or
   * split it — the permission to create an edit state is what defines an
   * editor role.
   *
   * @return \Drupal\user\RoleInterface[]
   *   The roles, keyed by id.
   */
  protected function getEditorRoles(): array {
    return array_filter(Role::loadMultiple(), static fn (RoleInterface $role) =>
      !$role->isAdmin()
      && $role->id() !== RoleInterface::ANONYMOUS_ID
      && $role->id() !== RoleInterface::AUTHENTICATED_ID
      && $role->hasPermission('create paragraphs blokkli edit state')
    );
  }

  /**
   * Returns the enabled search_api indexes.
   *
   * @return \Drupal\search_api\IndexInterface[]
   *   The indexes, keyed by id. Empty if search_api is not installed.
   */
  protected function getSearchIndexes(): array {
    if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
      return [];
    }
    return array_filter(Index::loadMultiple(), static fn (IndexInterface $index) => $index->status());
  }

  /**
   * Returns the GraphQL server the frontend talks to.
   */
  protected function getGraphqlServer(): ServerInterface {
    $servers = \Drupal::entityTypeManager()->getStorage('graphql_server')->loadMultiple();
    $server = $servers['graphql'] ?? reset($servers);
    assert($server instanceof ServerInterface, 'A GraphQL server is configured.');
    return $server;
  }

}
