<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Nested paragraphs created through the Drupal node form are saved.
 *
 * Editors can add a layout with a nested element (a Text with a Button) in
 * one go on `/node/{id}/edit` — a paragraphs widget inside a paragraphs
 * widget. The element must survive the first save of its new parent with its
 * values. Drives the form without JavaScript, so the widgets' add-more
 * buttons submit the whole form (their AJAX fallback).
 *
 * Host bundle and field names follow the starterkit (`icms_page`,
 * `field_icms_paragraphs`, `icms_layout_text`, `field_icms_buttons`); a
 * project without them skips via the settings-based skip mechanism.
 */
#[Group('icms')]
#[Group('icms_editorial')]
class NestedParagraphNodeFormTest extends IcmsExistingSiteBase {

  /**
   * A Text layout with a Button element added in the node form is saved.
   */
  public function testNestedParagraphIsSavedFromNodeForm(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    $node = $this->createIcmsNode(['type' => 'icms_page']);
    $this->drupalGet('/node/' . $node->id() . '/edit');
    $this->assertSame(200, $this->getSession()->getStatusCode());

    $page = $this->getSession()->getPage();

    // Add a Text layout.
    $add = $page->find('xpath', '//input[@type="submit" and contains(@name, "field_icms_paragraphs_icms_layout_text_add_more")]');
    $this->assertNotNull($add, 'The add-more button for the Text layout exists. Buttons: ' . $this->submitButtonNames());
    $add->press();

    // With a single allowed type the paragraphs widget pre-adds one Button to
    // a new Text; a project may have turned that off (`default_paragraph_type:
    // _none`), in which case the editor adds it.
    if ($this->nestedButtonCount() === 0) {
      $addNested = $page->find('xpath', '//input[@type="submit" and contains(@name, "field_icms_buttons_icms_button_element_add_more")]');
      $this->assertNotNull($addNested, 'The add-more button for the nested Button exists. Buttons: ' . $this->submitButtonNames());
      $addNested->press();
    }
    $this->assertSame(1, $this->nestedButtonCount(), 'The Text layout holds exactly one Button to fill in. Fields: ' . $this->fieldNames());

    $this->fillField('[field_icms_title][0][value]', 'Nested test title');
    $this->fillField('[field_icms_buttons][0][subform][field_icms_link_label][0][value]', 'Nested button label');
    $this->fillField('[field_icms_buttons][0][subform][field_icms_link][0][uri]', 'https://example.com');
    $page->pressButton('Save');

    $messages = array_map(static fn ($e) => trim($e->getText()), $page->findAll('css', '[data-drupal-messages] .messages, .messages'));
    $errors = $page->findAll('css', '.messages--error, [role="alert"]');
    $fieldErrors = array_map(static fn ($e) => trim($e->getText()), $page->findAll('css', '.form-item--error-message, .form-item__error-message'));
    $this->assertSame([], array_map(static fn ($e) => trim($e->getText()), $errors), 'Saving the node form produced no error messages. URL: ' . $this->getSession()->getCurrentUrl() . ' Messages: ' . implode(' | ', $messages) . ' Field errors: ' . implode(' | ', $fieldErrors));

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$node->id()]);
    $saved = $storage->loadRevision($storage->getLatestRevisionId($node->id()));
    $this->assertNotNull($saved);
    $this->assertTrue($saved->isDefaultRevision(), 'The saved revision is the default one (moderation state: ' . $saved->get('moderation_state')->value . ', status ' . (int) $saved->isPublished() . ').');
    $layouts = $saved->get('field_icms_paragraphs');
    $this->assertSame(1, $layouts->count(), 'The Text layout was saved.');
    $text = $layouts->entity;
    $this->assertSame('icms_layout_text', $text->bundle());
    $this->assertSame('Nested test title', $text->get('field_icms_title')->value);
    $buttons = $text->get('field_icms_buttons');
    $this->assertSame(1, $buttons->count(), 'The nested Button was saved with the Text.');
    $this->assertSame('Nested button label', $buttons->entity->get('field_icms_link_label')->value, 'The nested Button kept its values.');
    $this->assertSame('https://example.com', $buttons->entity->get('field_icms_link')->uri);
  }

  /**
   * Fills the first form field whose name ends with the given suffix.
   */
  protected function fillField(string $nameSuffix, string $value): void {
    $field = $this->getSession()->getPage()->find('xpath', '//input[contains(@name, "' . $nameSuffix . '")] | //textarea[contains(@name, "' . $nameSuffix . '")]');
    $this->assertNotNull($field, "A field named *$nameSuffix exists. Fields: " . $this->fieldNames());
    $field->setValue($value);
  }

  /**
   * Counts the Button subforms nested in the first Text layout.
   */
  protected function nestedButtonCount(): int {
    return count($this->getSession()->getPage()->findAll('xpath', '//input[contains(@name, "[field_icms_buttons][") and contains(@name, "[subform][field_icms_link_label][0][value]")]'));
  }

  /**
   * Lists the submit buttons on the current page, for assertion messages.
   */
  protected function submitButtonNames(): string {
    $names = array_map(static fn ($e) => $e->getAttribute('name'), $this->getSession()->getPage()->findAll('xpath', '//input[@type="submit"]'));
    return implode(', ', array_filter($names));
  }

  /**
   * Lists the paragraph-related input names on the current page.
   */
  protected function fieldNames(): string {
    $names = array_map(static fn ($e) => $e->getAttribute('name'), $this->getSession()->getPage()->findAll('xpath', '//input[contains(@name, "field_icms")] | //textarea[contains(@name, "field_icms")]'));
    return implode(', ', array_filter($names));
  }

}
