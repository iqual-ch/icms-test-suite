<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_blokkli\Form\AddParagraphForm;
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
 * Since editors can create a page in a language other than the site default,
 * a paragraph added through the blökkli add form must take the language of
 * that page: the form serializes the new paragraph into the "add" mutation,
 * so a paragraph created without an explicit langcode would end up in the
 * site default language instead. The same guarantee holds for the other add
 * mutations (media drops, library items, pasted text), which do not go
 * through a form.
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

  /**
   * The blökkli add form creates paragraphs in the host's source language.
   *
   * The paragraph the form builds and the paragraph replayed from the stored
   * "add" mutation must both carry the langcode of a host whose source
   * language is not the site default.
   */
  public function testAddFormCreatesParagraphInHostSourceLanguage(): void {
    $langcodes = $this->getSiteLangcodes();
    if (count($langcodes) < 2) {
      $this->markTestSkipped('The site has a single language.');
    }
    if (!\Drupal::moduleHandler()->moduleExists('content_translation')
      || !\Drupal::service('content_translation.manager')->isEnabled('node', $this->hostBundle)) {
      $this->markTestSkipped("The host bundle '{$this->hostBundle}' is not translatable.");
    }
    $roles = $this->getEditorRoles();
    if (!$roles) {
      $this->markTestSkipped('No editor role (non-admin role with "create paragraphs blokkli edit state") exists on this site.');
    }
    $default = $this->getDefaultLangcode();
    $langcode = current(array_diff($langcodes, [$default]));

    // The add form checks update access on the edit state and its host, so it
    // runs as an editor owning both.
    $editor = $this->createUser();
    $editor->addRole(reset($roles)->id());
    $editor->save();
    $host = $this->createIcmsNode([
      'type' => $this->hostBundle,
      'langcode' => $langcode,
      'uid' => $editor->id(),
    ]);
    if (!$host->access('update', $editor)) {
      $this->markTestSkipped("The editor role '" . reset($roles)->id() . "' may not edit '{$this->hostBundle}' content.");
    }

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $switcher */
    $switcher = \Drupal::service('account_switcher');
    $switcher->switchTo($editor);
    try {
      /** @var \Drupal\paragraphs_blokkli\ParagraphsBlokkliManager $manager */
      $manager = \Drupal::service('paragraphs_blokkli.manager');
      $state = $manager->getParagraphsEditState($host);
      $this->markEntityForCleanup($state);

      $form = AddParagraphForm::create(\Drupal::getContainer());
      $form->buildForm([], new FormState(), 'node', $host->uuid(), $this->paragraphType, 'node', $host->uuid(), $this->hostField);
      $paragraph = $form->getParagraph();
      $this->assertSame($langcode, $paragraph->language()->getId(), 'The add form creates the paragraph in the language of the host, not the site default.');

      // Submitting the form stores the serialized paragraph as the values of
      // an "add" mutation; replaying it must yield a paragraph in that language.
      $mutation = \Drupal::service('plugin.manager.paragraph_mutation')->createInstance('add', [
        'type' => $paragraph->bundle(),
        'hostType' => 'node',
        'hostUuid' => $host->uuid(),
        'hostFieldName' => $this->hostField,
        'afterUuid' => NULL,
        'values' => $paragraph->toArray(),
      ]);
      $state->addMutation($mutation);
      $manager->saveState($state);

      $fields = $state->getMutatedState()->getFields();
      $paragraphs = $fields ? reset($fields)->getParagraphs() : [];
      $this->assertCount(1, $paragraphs, 'Replaying the edit state yields the added paragraph.');
      $added = reset($paragraphs);
      assert($added instanceof ParagraphInterface);
      $this->assertSame($langcode, $added->getUntranslated()->language()->getId(), 'The added paragraph has the language of the host as its source language.');
      $this->assertFalse($added->hasTranslation($default), 'The added paragraph has no translation in the site default language.');
    }
    finally {
      $switcher->switchBack();
    }
  }

}
