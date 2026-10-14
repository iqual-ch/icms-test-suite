<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Smoke test for the editorial Drupal backend.
 *
 * A cheap "is the admin backend still alive" canary after an update: loads
 * the pages an editor uses daily and asserts HTTP 200. Because DTT fails the
 * test on any PHP error logged to watchdog, a plain page load catches broken
 * admin views, form alters, toolbar integrations and missing plugin/service
 * regressions that only surface at runtime.
 *
 * The editor role is discovered (a non-admin role allowed to create blökkli
 * edit states); only the routes that role may access are visited, so a
 * project with narrower editor permissions stays green.
 */
#[Group('icms')]
#[Group('icms_editorial')]
class EditorialBackendTest extends IcmsExistingSiteBase {

  /**
   * The key editorial backend routes respond with HTTP 200 for an editor.
   *
   * One pass over a route list on purpose: HTTP round-trips are the expensive
   * part of existing-site tests.
   */
  public function testEditorialBackendRoutesRespond(): void {
    [$editor, $role, $node] = $this->loginEditorWithNode();

    $routes = array_filter([
      '/admin/content' => $role->hasPermission('access content overview'),
      '/admin/content/media' => \Drupal::moduleHandler()->moduleExists('media') && $role->hasPermission('access media overview'),
      '/admin/structure/taxonomy' => $role->hasPermission('access taxonomy overview'),
      '/node/' . $node->id() . '/edit' => $node->access('update', $editor),
    ]);
    $this->assertNotEmpty($routes, "The editor role '{$role->id()}' can access none of the editorial routes.");

    foreach (array_keys($routes) as $path) {
      $this->drupalGet($path);
      $this->assertSame(200, $this->getSession()->getStatusCode(), "The editorial backend route '$path' must respond with HTTP 200 for role '{$role->id()}'.");
    }
  }

  /**
   * The "Edit page content" operation sends the editor to the blökkli editor.
   *
   * Content listings append ?destination to their operation links, which core
   * applies to any redirect. The route must open the editor anyway — also on
   * a repeated request, which the Dynamic Page Cache would otherwise answer
   * without running the controller.
   */
  public function testBlokkliOperationOpensTheEditor(): void {
    [$editor, $role, $node] = $this->loginEditorWithNode();
    if (!\Drupal::hasService('icms_core_logic.blokkli_editor')) {
      $this->markTestSkipped('The blökkli editor route (icms_core_logic) is not available on this site.');
    }
    if (!\Drupal::service('icms_core_logic.blokkli_editor')->access($node, $editor)->isAllowed()) {
      $this->markTestSkipped("The editor role '{$role->id()}' may not edit {$node->bundle()} nodes with blökkli.");
    }

    $url = Url::fromRoute('icms_core_logic.blokkli_edit', ['node' => $node->id()], ['query' => ['destination' => '/admin/content']]);
    // The redirect body carries a <meta http-equiv="refresh">, which both the
    // test browser and drupalGet() follow on their own.
    $client = $this->getSession()->getDriver()->getClient();
    $client->followRedirects(FALSE);
    $client->followMetaRefresh(FALSE);
    $this->maximumMetaRefreshCount = 0;
    try {
      foreach (['first', 'repeated'] as $attempt) {
        $this->drupalGet($url);
        $this->assertSame(302, $this->getSession()->getStatusCode(), "The $attempt request of the blökkli operation must redirect.");
        $location = (string) $this->getSession()->getResponseHeader('Location');
        $this->assertStringContainsString('blokkliEditing=' . $node->uuid(), $location, "The $attempt request of the blökkli operation must open the editor instead of following ?destination.");
      }
    }
    finally {
      $client->followRedirects(TRUE);
      $client->followMetaRefresh(TRUE);
      $this->maximumMetaRefreshCount = NULL;
    }
  }

  /**
   * Drupal forms opened from the blökkli editor show no backend chrome.
   *
   * The editor embeds the page settings and the translation form in iframes
   * (?paragraphsBlokkli=true). Navigation, toolbar and Gin's secondary toolbar
   * have no place in them — the frame is the form.
   */
  public function testBlokkliFormFramesHaveNoChrome(): void {
    [$editor, $role, $node] = $this->loginEditorWithNode();
    if (!$node->access('update', $editor)) {
      $this->markTestSkipped("The editor role '{$role->id()}' may not edit {$node->bundle()} nodes.");
    }

    $urls = [$node->toUrl('edit-form')];
    // Creating a translation, as blökkli's language switcher does.
    $source = $node->language()->getId();
    $targets = array_diff(array_keys(\Drupal::languageManager()->getLanguages()), [$source]);
    if ($targets && \Drupal::hasService('content_translation.manager') && \Drupal::service('content_translation.manager')->isEnabled('node', $node->bundle())) {
      $add = Url::fromRoute('entity.node.content_translation_add', [
        'node' => $node->id(),
        'source' => $source,
        'target' => reset($targets),
      ]);
      if ($add->access($editor)) {
        $urls[] = $add;
      }
    }

    $chrome = [
      '#toolbar-administration' => "the toolbar",
      '#toolbar-administration-secondary' => "Gin's secondary toolbar",
      '.admin-toolbar' => "the navigation sidebar",
      '.top-bar' => "the navigation top bar",
    ];
    foreach ($urls as $url) {
      $url->setOption('query', ['paragraphsBlokkli' => 'true']);
      $this->drupalGet($url);
      $path = $url->toString();
      $this->assertSame(200, $this->getSession()->getStatusCode(), "The blökkli form frame '$path' must respond with HTTP 200 for role '{$role->id()}'.");
      $page = $this->getSession()->getPage();
      foreach ($chrome as $selector => $label) {
        $this->assertNull($page->find('css', $selector), "The blökkli form frame '$path' must not render $label ($selector).");
      }
    }
  }

  /**
   * Logs in as a user with the site's editor role, owning a blökkli page.
   *
   * @return array{\Drupal\user\UserInterface, \Drupal\user\RoleInterface, \Drupal\node\NodeInterface}
   *   The editor, their role and the node.
   */
  protected function loginEditorWithNode(): array {
    $roles = $this->getEditorRoles();
    if (!$roles) {
      $this->markTestSkipped('No editor role (non-admin role with "create paragraphs blokkli edit state") exists on this site.');
    }
    $role = reset($roles);
    assert($role instanceof RoleInterface);

    $editor = $this->createUser();
    assert($editor instanceof UserInterface);
    $editor->addRole($role->id());
    $editor->save();
    $this->drupalLogin($editor);

    $hosts = $this->getBlokkliHostBundles()['node'] ?? [];
    $bundle = in_array('icms_page', $hosts, TRUE) ? 'icms_page' : (reset($hosts) ?: 'icms_page');
    $node = $this->createIcmsNode(['type' => $bundle, 'uid' => $editor->id()]);
    assert($node instanceof NodeInterface);
    return [$editor, $role, $node];
  }

}
