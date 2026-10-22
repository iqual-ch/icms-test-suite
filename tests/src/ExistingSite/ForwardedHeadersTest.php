<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\Core\Site\Settings;
use Drupal\icms_core_logic\BackendDomain;
use Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that clients cannot forge the host Drupal generates URLs for.
 *
 * Drupal only trusts X-Forwarded-Host/-Proto on requests from the Nuxt
 * frontend, which carry the shared GraphQL token (access_graphql.token, see
 * settings.php). A forged X-Forwarded-Host, -Port or -Proto must have no
 * effect on the backend domain, the frontend domain, or the internal URL
 * Nuxt talks to Drupal on, and a client-sent Forwarded header must not break
 * the request (ICMS-795). The forged host is a backend domain: Drupal derives
 * the frontend, the session cookie domain and the allowed frame ancestors
 * from it.
 */
#[Group('icms')]
class ForwardedHeadersTest extends IcmsExistingSiteBase {

  private const FORGED_HOST = BackendDomain::SUBDOMAIN . '.forged-host.invalid';

  private const FORGED_DOMAIN = 'forged-host.invalid';

  private const FORGED_PORT = '8443';

  /**
   * Forged forwarded headers do not end up in Drupal's redirects.
   */
  public function testForgedForwardedHeadersAreIgnored(): void {
    foreach ($this->getTargetUrls() as $label => $url) {
      $response = $this->request($url . '/user', [
        'X-Forwarded-Host' => self::FORGED_HOST,
        'X-Forwarded-Port' => self::FORGED_PORT,
        'X-Forwarded-Proto' => 'http',
      ]);
      $location = $response->getHeaderLine('Location');
      $this->assertLessThan(500, $response->getStatusCode(), "$label: request with forged forwarded headers succeeds");
      $this->assertStringNotContainsString(self::FORGED_DOMAIN, $location, "$label: forged X-Forwarded-Host is ignored");
      $this->assertStringNotContainsString(self::FORGED_DOMAIN, $response->getHeaderLine('Content-Security-Policy'), "$label: forged X-Forwarded-Host does not become a frame ancestor");
      $this->assertStringNotContainsString(self::FORGED_DOMAIN, implode("\n", $response->getHeader('Set-Cookie')), "$label: forged X-Forwarded-Host does not scope the session cookie");
      $this->assertStringNotContainsString(':' . self::FORGED_PORT, $location, "$label: forged X-Forwarded-Port is ignored");
      if (str_starts_with($url, 'https://')) {
        $this->assertStringNotContainsString('http://', $location, "$label: forged X-Forwarded-Proto is ignored");
      }

      $response = $this->request($url . '/user', ['Forwarded' => 'host=' . self::FORGED_HOST . ';proto=http']);
      $this->assertLessThan(500, $response->getStatusCode(), "$label: a client-sent Forwarded header does not cause an error");
      $this->assertStringNotContainsString(self::FORGED_DOMAIN, $response->getHeaderLine('Location'), "$label: Forwarded header is ignored");
    }
  }

  /**
   * Requests from Nuxt (with the GraphQL token) get URLs on the frontend host.
   */
  public function testFrontendRequestsUseTheForwardedHost(): void {
    $internal = getenv('NUXT_BACKEND_URL');
    $token = (string) Settings::get('access_graphql.token');
    $frontend_host = (string) parse_url($this->getFrontendUrl(), PHP_URL_HOST);
    if (!$internal || !$token || !$frontend_host) {
      $this->markTestSkipped('NUXT_BACKEND_URL, DRUPAL_GRAPHQL_TOKEN and a backend base URL are needed to act as the frontend.');
    }

    $response = $this->request(rtrim($internal, '/') . '/user', [
      'X-Drupal-Graphql-Token' => $token,
      'X-Forwarded-Host' => $frontend_host,
      'X-Forwarded-Proto' => 'https',
    ]);
    $this->assertStringStartsWith("https://$frontend_host/", $response->getHeaderLine('Location'), 'Drupal generates URLs for the forwarded frontend host');

    // A wrong token is not enough.
    $response = $this->request(rtrim($internal, '/') . '/user', [
      'X-Drupal-Graphql-Token' => $token . '-wrong',
      'X-Forwarded-Host' => self::FORGED_HOST,
    ]);
    $this->assertStringNotContainsString(self::FORGED_DOMAIN, $response->getHeaderLine('Location'), 'A wrong GraphQL token is not trusted');
  }

  /**
   * Returns the URLs a client or the frontend reaches Drupal on, by label.
   *
   * @return array<string, string>
   *   The base URLs without trailing slash.
   */
  private function getTargetUrls(): array {
    $urls = [
      'backend domain' => $this->baseUrl,
      'frontend domain' => $this->getFrontendUrl(),
      'internal frontend-to-Drupal URL' => getenv('NUXT_BACKEND_URL') ?: '',
    ];
    return array_map(fn (string $url) => rtrim($url, '/'), array_unique(array_filter($urls)));
  }

  /**
   * Returns the frontend URL of the backend domain the tests run against.
   */
  private function getFrontendUrl(): string {
    return (string) \Drupal::service('icms_core_logic.backend_domain')->getFrontendUrl(Request::create($this->baseUrl));
  }

  /**
   * Sends a GET request without following redirects.
   */
  private function request(string $url, array $headers): ResponseInterface {
    return \Drupal::httpClient()->get($url, [
      'headers' => $headers,
      'allow_redirects' => FALSE,
      'http_errors' => FALSE,
      'verify' => FALSE,
    ]);
  }

}
