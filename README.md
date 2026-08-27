# ICMS Test Suite

PHPUnit tests asserting the ICMS product contract — the GraphQL interface the Nuxt frontend
depends on, the caching contract, blökkli paragraphs, language handling, search, the editorial
backend and the installed bundles. The suite ships with every ICMS release (via
`iqual/icms_core_dev`) and runs in every client project's CI, catching regressions from routine
updates and from project customizations before deployment.

The tests run through [Drupal Test Traits](https://gitlab.com/weitzman/drupal-test-traits)
against the existing site and are discovered by the `phpunit.xml.dist` scaffolded by
`iqual/drupal-nuxt-platform` (`vendor/iqual/*-test-suite/tests/src/*`).

```bash
make drupal-test-db                                         # everything
make drupal-test-db PHPUNIT_FLAGS="--group=icms"            # core contract only
make drupal-test-db PHPUNIT_FLAGS="--group=icms_bundle_news" # one bundle
```

## Skipping tests in a customized project

The suite asserts the contract as shipped. Where a project deliberately deviates, document the
deviation in `settings.local.php` instead of patching the suite — the reason is printed with the
skip:

```php
$settings['icms_test_suite']['skip'] = [
  'groups' => ['icms_search' => 'Search is served by Algolia (PROJ-12).'],
  'tests' => [
    'Iqual\IcmsTestSuite\Tests\ExistingSite\NuxtCacheHeadersTest' => 'No Varnish in front of this site.',
    'Iqual\IcmsTestSuite\Tests\ExistingSite\GraphQlSmokeTest::testEntityCanBeQueried' => 'PROJ-56',
  ],
];
```

Bundle tests (`#[Group('icms_bundle_<name>')]`) skip themselves wherever the bundle's `_logic`
module is not enabled.

## Writing project-level tests

Project tests belong in the project's `drupal/tests/src/<TestType>/` (namespace
`Tests\<TestType>`), not in this package. They may extend the suite's base classes to reuse the
helpers:

- `Iqual\IcmsTestSuite\ExistingSite\IcmsExistingSiteBase` — DTT base with site discovery
  (languages, blökkli hosts, editor roles, search indexes), content creation with clean-up and
  the GraphQL HTTP helpers (`graphqlQuery()` posts to `/{lang}/graphql` with the
  `access_graphql.token` header, like Nuxt does).
- `Iqual\IcmsTestSuite\ExistingSite\IcmsGraphQlExistingSiteBase` — adds in-process execution
  against the GraphQL server with cache metadata assertions.

Development happens in the [ICMS monorepo](https://github.com/iqual-ch/blokkli-starterkit-sw-experiment)
(`drupal/packages/icms-test-suite/`, see `docs/testing.md` there); this repository is a
read-only distribution mirror.
