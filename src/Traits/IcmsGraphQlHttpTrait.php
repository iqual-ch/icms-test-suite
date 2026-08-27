<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Traits;

use Drupal\Core\Site\Settings;
use Psr\Http\Message\ResponseInterface;

/**
 * Executes GraphQL queries over HTTP the way the Nuxt frontend does.
 *
 * Nuxt posts to `/{lang}/graphql` with the shared `access_graphql.token`
 * header; going through HTTP exercises the whole stack — access_graphql,
 * language negotiation from the path prefix, the response headers of the
 * caching contract — which in-process execution of the server bypasses.
 *
 * Requires DTT's `$this->baseUrl` and the `IcmsSiteDiscoveryTrait`.
 */
trait IcmsGraphQlHttpTrait {

  /**
   * Posts a GraphQL operation and returns the raw HTTP response.
   *
   * @param string $query
   *   The GraphQL document.
   * @param array $variables
   *   Operation variables.
   * @param string|null $langcode
   *   The language to query in (path prefix); defaults to the site default.
   */
  protected function graphqlRequest(string $query, array $variables = [], ?string $langcode = NULL): ResponseInterface {
    return \Drupal::httpClient()->post($this->getGraphqlUrl($langcode), [
      'headers' => [
        'Content-Type' => 'application/json',
        'x-drupal-graphql-token' => (string) Settings::get('access_graphql.token'),
      ],
      'body' => json_encode(['query' => $query, 'variables' => $variables], JSON_THROW_ON_ERROR),
      'verify' => FALSE,
      'http_errors' => FALSE,
    ]);
  }

  /**
   * Posts a GraphQL operation and returns its decoded `data`.
   *
   * Fails the test on a non-200 response or GraphQL errors.
   */
  protected function graphqlQuery(string $query, array $variables = [], ?string $langcode = NULL): array {
    $response = $this->graphqlRequest($query, $variables, $langcode);
    $body = (string) $response->getBody();
    $this->assertSame(200, $response->getStatusCode(), "GraphQL endpoint returned HTTP {$response->getStatusCode()}: $body");
    $decoded = json_decode($body, TRUE);
    $this->assertIsArray($decoded, 'GraphQL endpoint returned a JSON body: ' . substr($body, 0, 500));
    $this->assertArrayNotHasKey('errors', $decoded, 'GraphQL errors: ' . json_encode($decoded['errors'] ?? NULL));
    $this->assertIsArray($decoded['data'] ?? NULL, 'GraphQL response carries data.');
    return $decoded['data'];
  }

  /**
   * Returns the absolute GraphQL endpoint URL for a language.
   */
  protected function getGraphqlUrl(?string $langcode = NULL): string {
    $langcode ??= $this->getDefaultLangcode();
    $prefix = $this->getLanguagePathPrefix($langcode);
    $endpoint = $this->getGraphqlServer()->get('endpoint') ?: '/graphql';
    return rtrim($this->baseUrl, '/') . ($prefix !== '' ? "/$prefix" : '') . $endpoint;
  }

}
