<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\views\Entity\View;
use Iqual\IcmsTestSuite\ExistingSite\IcmsBundleContractTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract of the event bundle: node type, listing paragraph, GraphQL exposure.
 *
 * Skipped where icms_bundle_event_logic is not installed.
 */
#[Group('icms')]
#[Group('icms_bundle_event')]
class IcmsBundleEventTest extends IcmsBundleContractTestBase {

  protected const ICMS_BUNDLE = 'event';

  /**
   * {@inheritdoc}
   *
   * An event requires at least one occurrence paragraph with a date.
   */
  protected function getNodeValues(): array {
    $start = strtotime('tomorrow 10:00');
    $occurrence = $this->createIcmsParagraph('icms_event_occurrence', [
      'field_icms_date' => [
        'value' => $start,
        'end_value' => $start + 3600,
        'duration' => 60,
      ],
    ]);
    return ['field_icms_event_occurrence' => [$occurrence]];
  }

  /**
   * The registrations view only depends on config that reaches config sync.
   *
   * `webform.webform.*` is ignored by config_ignore, so the event registration
   * webform never lands in the sync directory. A view declaring it as a config
   * dependency therefore fails `drush cim` on every environment that installs
   * the bundle through the import itself — the module that creates the webform
   * is installed by the very import that validates the view (ICMS-640).
   */
  public function testRegistrationsViewOnlyDependsOnSyncedConfig(): void {
    $view = View::load('event_registrations');
    if ($view === NULL) {
      $this->markTestSkipped('This site does not have the event_registrations view.');
    }

    $ignored = \Drupal::config('config_ignore.settings')->get('ignored_config_entities') ?: [];
    $unsynced = [];

    foreach ($view->getDependencies()['config'] ?? [] as $name) {
      foreach ($ignored as $pattern) {
        // A "~" prefix marks an exception, which keeps the config in sync.
        if (str_starts_with($pattern, '~')) {
          continue;
        }
        $matches = str_ends_with($pattern, '*')
          ? str_starts_with($name, rtrim($pattern, '*'))
          : $name === $pattern;
        if ($matches) {
          $unsynced[$name] = $name;
        }
      }
    }

    $this->assertSame([], array_values($unsynced), 'The event_registrations view depends on configuration that config_ignore keeps out of the config sync directory, which breaks config import.');
  }

  /**
   * The bundle ships the dedicated occurrence date formats.
   */
  public function testEventDateFormatsExist(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('date_format');
    $this->assertNotNull($storage->load('icms_event_date'));
    $this->assertNotNull($storage->load('icms_event_time'));
  }

  /**
   * The events listing index carries no language field (ICMS-744).
   *
   * Occurrence paragraphs are not translatable; a language field on the
   * index would partition the listing and calendar per request language.
   */
  public function testEventsListingIndexHasNoLanguageField(): void {
    $index = \Drupal::entityTypeManager()->getStorage('search_api_index')->load('events_listing');
    $this->assertNotNull($index);
    $this->assertNull($index->getField('langcode'), 'The events_listing index has no langcode field.');
  }

}
