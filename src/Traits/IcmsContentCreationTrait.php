<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Creates content that is cleaned up after the test.
 *
 * Projects add required fields to ICMS bundles (e.g. Domain Access adds
 * `field_domain_access` to every node type). The suite cannot know their
 * values, so a project provides them in its settings, per entity type and
 * bundle (`*` applies to every bundle):
 *
 * @code
 * $settings['icms_test_suite']['content']['node']['*'] = [
 *   'field_domain_access' => ['wks'],
 * ];
 * @endcode
 *
 * Without them a test that needs content skips itself with the validation
 * errors instead of failing.
 *
 * Requires DTT's `markEntityForCleanup()` and `getRandomGenerator()`.
 */
trait IcmsContentCreationTrait {

  /**
   * Creates and saves a node, flagged for clean-up.
   *
   * Pages are moderated (editorial workflow): saving without a moderation
   * state leaves them as an unpublished draft, so `published` is the default
   * wherever the bundle is moderated.
   *
   * @param array $values
   *   Field values; `type` defaults to `icms_page`.
   */
  protected function createIcmsNode(array $values = []): NodeInterface {
    $values += ['type' => 'icms_page'];
    $values += $this->getIcmsContentDefaults('node', $values['type']);
    $values += [
      'title' => $this->getRandomGenerator()->sentences(3),
      'status' => TRUE,
    ];
    if ($this->isIcmsBundleModerated('node', $values['type'])) {
      $values += ['moderation_state' => 'published'];
    }
    $node = Node::create($values);
    $this->fillRequiredFields($node);
    $this->skipUnlessValid($node);
    $node->save();
    $this->markEntityForCleanup($node);
    return $node;
  }

  /**
   * Creates and saves a paragraph, flagged for clean-up.
   *
   * @param string $type
   *   The paragraph type, e.g. `icms_layout_text`.
   * @param array $values
   *   Additional field values.
   */
  protected function createIcmsParagraph(string $type, array $values = []): ParagraphInterface {
    $values += ['type' => $type];
    $values += $this->getIcmsContentDefaults('paragraph', $type);
    $paragraph = Paragraph::create($values);
    $this->fillRequiredFields($paragraph);
    $this->skipUnlessValid($paragraph);
    $paragraph->save();
    $this->markEntityForCleanup($paragraph);
    return $paragraph;
  }

  /**
   * Returns the project's default values for a bundle from the settings.
   */
  protected function getIcmsContentDefaults(string $entity_type_id, string $bundle): array {
    $content = Settings::get('icms_test_suite', [])['content'][$entity_type_id] ?? [];
    return ($content[$bundle] ?? []) + ($content['*'] ?? []);
  }

  /**
   * Whether a bundle is under content moderation.
   */
  protected function isIcmsBundleModerated(string $entity_type_id, string $bundle): bool {
    if (!\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      return FALSE;
    }
    return \Drupal::service('content_moderation.moderation_information')
      ->shouldModerateEntitiesOfBundle(\Drupal::entityTypeManager()->getDefinition($entity_type_id), $bundle);
  }

  /**
   * Fills empty required fields with generated values where that is safe.
   *
   * Bundles require fields the suite doesn't care about (e.g. `icms_news`
   * requires topics): required plain string/text fields get random text,
   * required taxonomy references get a fresh term in the first allowed
   * vocabulary (flagged for clean-up). Anything else is left to the project's
   * settings defaults.
   */
  protected function fillRequiredFields(ContentEntityInterface $entity): void {
    foreach ($entity->getFieldDefinitions() as $name => $definition) {
      if (!$definition->isRequired() || !$entity->get($name)->isEmpty() || $definition->isComputed()) {
        continue;
      }
      $type = $definition->getType();
      if (in_array($type, ['string', 'string_long', 'text', 'text_long', 'text_with_summary'], TRUE)) {
        $entity->set($name, $this->getRandomGenerator()->sentences(2));
      }
      elseif ($type === 'entity_reference' && $definition->getSetting('target_type') === 'taxonomy_term') {
        $vocabularies = array_keys($definition->getSetting('handler_settings')['target_bundles'] ?? []);
        if (!$vocabularies) {
          continue;
        }
        $term = Term::create([
          'vid' => reset($vocabularies),
          'name' => $this->getRandomGenerator()->word(12),
          'langcode' => $entity->language()->getId(),
        ]);
        $term->save();
        $this->markEntityForCleanup($term);
        $entity->set($name, $term);
      }
    }
  }

  /**
   * Skips the test when an entity built from the suite defaults is invalid.
   *
   * Typically a project-specific required field; the message names it and
   * the setting that provides its value.
   */
  protected function skipUnlessValid(ContentEntityInterface $entity): void {
    $messages = [];
    foreach ($entity->validate() as $violation) {
      // Moderation transitions are access-checked against the current user,
      // which is anonymous in the test process; saving is unaffected.
      if (str_starts_with($violation->getPropertyPath(), 'moderation_state')) {
        continue;
      }
      $messages[] = ($violation->getPropertyPath() ? $violation->getPropertyPath() . ': ' : '') . strip_tags((string) $violation->getMessage());
    }
    if (!$messages) {
      return;
    }
    $this->markTestSkipped(sprintf(
      "Cannot create a valid %s '%s' with the suite defaults (%s). Provide the project's values via \$settings['icms_test_suite']['content']['%s']['%s'].",
      $entity->getEntityTypeId(),
      $entity->bundle(),
      implode('; ', $messages),
      $entity->getEntityTypeId(),
      $entity->bundle(),
    ));
  }

}
