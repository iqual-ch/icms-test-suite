<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\ExistingSite;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\graphql\GraphQL\Execution\ExecutionResult;
use Drupal\Tests\graphql\Traits\QueryResultAssertionTrait;
use GraphQL\Server\OperationParams;

/**
 * Base class for tests executing GraphQL in-process against the server.
 *
 * Unlike the HTTP helpers, in-process execution exposes the result's cache
 * metadata (tags, contexts, max-age), which is what the caching contract
 * between Drupal and the frontend is built on.
 */
abstract class IcmsGraphQlExistingSiteBase extends IcmsExistingSiteBase {

  use QueryResultAssertionTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->server = $this->getGraphqlServer();
  }

  /**
   * Reads a query document from the suite's `tests/queries` directory.
   */
  protected function getQueryFromFile(string $file): string {
    $path = dirname(__DIR__, 2) . '/tests/queries/' . $file;
    $query = file_get_contents($path);
    assert($query !== FALSE, "Query file $path is readable.");
    return $query;
  }

  /**
   * Executes a GraphQL operation in-process.
   */
  protected function getQueryResult(string $query, array $variables = []): ExecutionResult {
    $context = new RenderContext();
    $params = OperationParams::create([
      'query' => $query,
      'variables' => $variables,
    ]);

    return $this->getRenderer()->executeInRenderContext(
      $context,
      fn () => $this->server->executeOperation($params),
    );
  }

  /**
   * Asserts the cache metadata of a query result.
   *
   * Copied from QueryResultAssertionTrait, visibility changed to protected.
   *
   * @see \Drupal\Tests\graphql\Traits\QueryResultAssertionTrait::assertResultMetadata()
   */
  protected function assertResultMetadata(ExecutionResult $result, CacheableMetadata $expected): void {
    $this->assertEquals($expected->getCacheMaxAge(), $result->getCacheMaxAge(), 'Unexpected cache max age.');

    $missingContexts = array_diff($expected->getCacheContexts(), $result->getCacheContexts());
    $this->assertEmpty($missingContexts, 'Missing cache contexts: ' . implode(', ', $missingContexts));

    $unexpectedContexts = array_diff($result->getCacheContexts(), $expected->getCacheContexts());
    $this->assertEmpty($unexpectedContexts, 'Unexpected cache contexts: ' . implode(', ', $unexpectedContexts));

    $missingTags = array_diff($expected->getCacheTags(), $result->getCacheTags());
    $this->assertEmpty($missingTags, 'Missing cache tags: ' . implode(', ', $missingTags));

    $unexpectedTags = array_diff($result->getCacheTags(), $expected->getCacheTags());
    $this->assertEmpty($unexpectedTags, 'Unexpected cache tags: ' . implode(', ', $unexpectedTags));
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultCacheTags() {
    return Cache::mergeTags(['graphql_response'], $this->server->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultCacheContexts() {
    return [
      'user.permissions',
      'languages:language_interface',
    ];
  }

}
