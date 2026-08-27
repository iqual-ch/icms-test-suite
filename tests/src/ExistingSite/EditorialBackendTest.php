<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\user\RoleInterface;
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
   * One test method on purpose: HTTP round-trips are the expensive part of
   * existing-site tests, so this is a single pass over a route list.
   */
  public function testEditorialBackendRoutesRespond(): void {
    $roles = $this->getEditorRoles();
    if (!$roles) {
      $this->markTestSkipped('No editor role (non-admin role with "create paragraphs blokkli edit state") exists on this site.');
    }
    $role = reset($roles);
    assert($role instanceof RoleInterface);

    $editor = $this->createUser();
    $editor->addRole($role->id());
    $editor->save();
    $this->drupalLogin($editor);

    $hosts = $this->getBlokkliHostBundles()['node'] ?? [];
    $bundle = in_array('icms_page', $hosts, TRUE) ? 'icms_page' : (reset($hosts) ?: 'icms_page');
    $node = $this->createIcmsNode(['type' => $bundle, 'uid' => $editor->id()]);

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

}
