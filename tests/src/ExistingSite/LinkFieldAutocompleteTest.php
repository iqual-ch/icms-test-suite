<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\RoleInterface;
use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that link fields can find content of every content type.
 *
 * Link fields use the linkit widget since 1.7 (update 9049). Its profile must
 * match every content type, like core's link widget did, and unpublished
 * content for editors who bypass node access (ICMS-841).
 */
#[Group('icms')]
#[Group('icms_editorial')]
class LinkFieldAutocompleteTest extends IcmsExistingSiteBase {

  /**
   * The link field autocomplete finds a node of every content type.
   *
   * Update 9067 keeps a bundle list a project changed, so only those types
   * are expected then. Pages only was the 1.7 default.
   */
  public function testLinkFieldAutocompleteFindsEveryContentType(): void {
    $profile = \Drupal::entityTypeManager()->getStorage('linkit_profile')->load('content');
    $matcher = $profile?->getMatcherByEntityType('node');
    if (!$matcher || !$this->linkFieldsUseProfile('content')) {
      $this->markTestSkipped('No link field uses the linkit profile "content" with a node matcher on this site.');
    }
    $roles = $this->getEditorRoles();
    if (!$roles) {
      $this->markTestSkipped('No editor role (non-admin role with "create paragraphs blokkli edit state") exists on this site.');
    }
    $role = reset($roles);
    assert($role instanceof RoleInterface);

    $bundles = $matcher->getConfiguration()['settings']['bundles'] ?? [];
    $types = $bundles && $bundles !== ['icms_page' => 'icms_page'] ? array_keys($bundles) : array_keys(NodeType::loadMultiple());
    // A shared token finds all test nodes with one lookup.
    $token = $this->getRandomGenerator()->name(12);
    $expected = [];
    foreach ($types as $type) {
      $expected[] = $this->createLinkTarget($type, "$token $type", TRUE)->label();
    }
    // Linkit hides unpublished nodes wherever node grants are implemented.
    if ($role->hasPermission('bypass node access') && !\Drupal::moduleHandler()->hasImplementations('node_grants')) {
      $expected[] = $this->createLinkTarget(in_array('icms_page', $types, TRUE) ? 'icms_page' : reset($types), "$token unpublished", FALSE)->label();
    }

    $editor = $this->createUser();
    $editor->addRole($role->id());
    $editor->save();
    $this->setCurrentUser($editor);

    $suggestions = \Drupal::service('linkit.suggestion_manager')->getSuggestions($profile, $token)->getSuggestions();
    $labels = array_map(static fn ($suggestion) => $suggestion->getLabel(), $suggestions);
    foreach ($expected as $label) {
      $this->assertContains($label, $labels, "The link field autocomplete must find '$label' for role '{$role->id()}'.");
    }
  }

  /**
   * Whether a link field widget uses the linkit profile.
   */
  protected function linkFieldsUseProfile(string $profile_id): bool {
    foreach (\Drupal::entityTypeManager()->getStorage('entity_form_display')->loadMultiple() as $display) {
      foreach ($display->getComponents() as $component) {
        if (($component['type'] ?? '') === 'linkit' && ($component['settings']['linkit_profile'] ?? '') === $profile_id) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Creates a node to link to, flagged for clean-up.
   *
   * Product fields a bundle requires (e.g. event occurrences) don't matter
   * for finding the node and stay empty.
   */
  protected function createLinkTarget(string $type, string $title, bool $published): Node {
    $values = ['type' => $type, 'title' => $title, 'status' => $published];
    $values += $this->getIcmsContentDefaults('node', $type);
    if ($this->isIcmsBundleModerated('node', $type)) {
      $values['moderation_state'] = $published ? 'published' : 'draft';
    }
    $node = Node::create($values);
    $this->fillRequiredFields($node);
    $this->skipUnlessValid($node, TRUE);
    $node->save();
    $this->markEntityForCleanup($node);
    return $node;
  }

}
