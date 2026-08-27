<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\paragraphs\ParagraphInterface;
use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests language handling of paragraphs created through paragraphs_blokkli.
 *
 * Language handling on blökkli paragraphs is a historically critical
 * regression area: a language negotiation change once corrupted paragraph
 * translations in production because new paragraphs were created in the
 * request language instead of the host entity language. Paragraph
 * translations are only ever created through the dedicated blökkli translate
 * flow, so translating the host must not touch the original's paragraphs.
 *
 * Paragraphs created through the "add" mutation inheriting the host langcode
 * is not asserted yet: the ICMS product does not carry the paragraphs_blokkli
 * patch that guarantees it (only individual projects do), so the mutation
 * currently creates paragraphs in the request language on a plain install.
 * See the ICMS-737 backlog.
 *
 * Host bundle, paragraph field and paragraph type are discovered from the
 * site's blökkli configuration.
 */
#[Group('icms')]
#[Group('icms_blokkli')]
class BlokkliParagraphLanguageTest extends IcmsExistingSiteBase {

  /**
   * The blökkli host node bundle under test.
   */
  protected string $hostBundle;

  /**
   * The paragraph reference field blökkli mutates on the host.
   */
  protected string $hostField;

  /**
   * A paragraph type allowed on the host field.
   */
  protected string $paragraphType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $hosts = $this->getBlokkliHostBundles()['node'] ?? [];
    $this->assertNotEmpty($hosts, 'blökkli is enabled for at least one node bundle.');
    $this->hostBundle = in_array('icms_page', $hosts, TRUE) ? 'icms_page' : reset($hosts);

    $fields = $this->getParagraphFields('node', $this->hostBundle);
    $this->assertNotEmpty($fields, "The blökkli host bundle '{$this->hostBundle}' has a paragraph reference field.");
    $field = $fields['field_icms_paragraphs'] ?? reset($fields);
    $this->hostField = $field->getName();

    $allowed = array_keys($field->getSetting('handler_settings')['target_bundles'] ?? []);
    $this->paragraphType = in_array('icms_layout_text', $allowed, TRUE) || !$allowed ? 'icms_layout_text' : reset($allowed);
  }

  /**
   * Translating the host does not touch the original's paragraphs.
   *
   * Paragraph translations are only ever created through the dedicated
   * blökkli translate flow.
   */
  public function testTranslatingHostKeepsOriginalParagraphLanguage(): void {
    $langcodes = $this->getSiteLangcodes();
    if (count($langcodes) < 2) {
      $this->markTestSkipped('The site has a single language.');
    }
    if (!\Drupal::moduleHandler()->moduleExists('content_translation')
      || !\Drupal::service('content_translation.manager')->isEnabled('node', $this->hostBundle)) {
      $this->markTestSkipped("The host bundle '{$this->hostBundle}' is not translatable.");
    }
    [$original, $translation] = array_values($langcodes);

    $paragraph = $this->createIcmsParagraph($this->paragraphType, ['langcode' => $original]);
    $host = $this->createIcmsNode([
      'type' => $this->hostBundle,
      'langcode' => $original,
      $this->hostField => [$paragraph],
    ]);

    $host->addTranslation($translation, ['title' => $this->getRandomGenerator()->sentences(3)])->save();

    $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $storage->resetCache([$paragraph->id()]);
    $reloaded = $storage->load($paragraph->id());
    assert($reloaded instanceof ParagraphInterface);

    $this->assertSame($original, $reloaded->language()->getId(), 'Translating the host node does not change the langcode of its original paragraphs.');
    $this->assertFalse($reloaded->hasTranslation($translation), 'Translating the host node does not implicitly create paragraph translations.');
  }

}
