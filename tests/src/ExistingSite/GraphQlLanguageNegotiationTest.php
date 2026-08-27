<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that GraphQL requests are served in the language of the URL prefix.
 *
 * The frontend has exactly one way to ask for a language: it posts to
 * `/{lang}/graphql`. Drupal negotiates the content language from that prefix
 * (`language-url` for the interface, content follows the interface). When an
 * update or a negotiation change breaks this, every language but the default
 * silently renders default-language content — the regression class behind
 * several ICMS incidents. This asserts, per language, that a translated node
 * comes back in the requested translation.
 */
#[Group('icms')]
#[Group('icms_graphql')]
class GraphQlLanguageNegotiationTest extends IcmsExistingSiteBase {

  /**
   * A node is returned in the translation matching the URL language prefix.
   */
  public function testNodeIsServedInTheRequestedLanguage(): void {
    $langcodes = $this->getSiteLangcodes();
    $default = $this->getDefaultLangcode();
    $translatable = \Drupal::moduleHandler()->moduleExists('content_translation')
      && \Drupal::service('content_translation.manager')->isEnabled('node', 'icms_page');

    $titles = [$default => 'ICMS language contract ' . $default . ' ' . $this->randomMachineName()];
    $node = $this->createIcmsNode(['langcode' => $default, 'title' => $titles[$default]]);
    if ($translatable) {
      foreach (array_diff($langcodes, [$default]) as $langcode) {
        $titles[$langcode] = 'ICMS language contract ' . $langcode . ' ' . $this->randomMachineName();
        $node->addTranslation($langcode, ['title' => $titles[$langcode]] + $this->getIcmsContentDefaults('node', 'icms_page'));
      }
      $node->save();
    }

    $query = <<<'GQL'
query nodeByUuid($uuid: String!) {
  entityByUuid(entityType: NODE, uuid: $uuid) {
    ... on Node { langcode title }
  }
}
GQL;

    foreach ($titles as $langcode => $title) {
      $data = $this->graphqlQuery($query, ['uuid' => $node->uuid()], $langcode);
      $this->assertSame($langcode, $data['entityByUuid']['langcode'] ?? NULL, "A request to /{$this->getLanguagePathPrefix($langcode)}/graphql is served in '$langcode'.");
      $this->assertSame($title, $data['entityByUuid']['title'] ?? NULL, "The '$langcode' translation is returned for the '$langcode' prefix.");
    }
  }

}
