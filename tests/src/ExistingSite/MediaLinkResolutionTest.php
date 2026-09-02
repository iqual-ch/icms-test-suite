<?php

declare(strict_types=1);

namespace Iqual\IcmsTestSuite\Tests\ExistingSite;

use Drupal\Core\File\FileExists;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Iqual\IcmsTestSuite\ExistingSite\IcmsGraphQlExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that media document links resolve to the file URL over GraphQL.
 *
 * Button links (and every other component using the shared `linkUrl`
 * fragment) rely on links stored as `entity:media/N` resolving to
 * `EntityCanonicalUrl` with a `mediaFileUrl`. Links stored differently
 * (e.g. `internal:/media/N`) silently resolve to a URL type without an
 * entity, and the frontend falls back to the unrouted media path. This
 * locks in the backend half of that contract.
 */
#[Group('icms')]
class MediaLinkResolutionTest extends IcmsGraphQlExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // With standalone media URLs disabled, the media "canonical" route is the
    // edit form, so validating an entity:media/N link checks update access -
    // the same access an editor saving such a link has.
    $this->setUpCurrentUser(permissions: [
      'access content',
      'view media',
      'update any media',
    ]);
  }

  /**
   * A button link stored as entity:media/N resolves to the file URL.
   */
  public function testButtonMediaLinkResolvesToFileUrl(): void {
    $media = $this->createDocumentMedia();
    $file = $media->get('field_media_document')->entity;

    $button = $this->createIcmsParagraph('icms_button_element', [
      'field_icms_link_label' => 'Download',
      'field_icms_link' => [
        'uri' => 'entity:media/' . $media->id(),
      ],
    ]);

    // Give the paragraph its regular hosting chain (button element inside a
    // layout inside a page), since paragraph access is delegated to the
    // parent entity.
    $layout = $this->createIcmsParagraph('icms_layout_call_to_action', [
      'field_icms_buttons' => [$button],
    ]);
    $this->createIcmsNode(['field_icms_paragraphs' => [$layout]]);

    $result = $this->getQueryResult(
      $this->getQueryFromFile('query.get_icms_button_media_link_by_uuid.graphql'),
      ['uuid' => $button->uuid()],
    );

    $this->assertSame([], $result->errors, 'GraphQL errors: ' . json_encode($result->errors));

    $uri = $result->data['entityByUuid']['link']['uri'] ?? NULL;
    $this->assertNotNull($uri, 'The button link resolved to a URL.');
    $this->assertSame(
      $file->createFileUrl(),
      $uri['entity']['mediaFileUrl']['path'] ?? NULL,
      'The media link resolves to the document file URL.',
    );
  }

  /**
   * Creates a published document media with an attached file.
   */
  protected function createDocumentMedia(): MediaInterface {
    $file = \Drupal::service('file.repository')->writeData(
      'ICMS test document.',
      'public://icms-test-media-link.txt',
      FileExists::Rename,
    );
    $this->markEntityForCleanup($file);

    $media = Media::create([
      'bundle' => 'document',
      'name' => 'ICMS test document',
      'status' => TRUE,
      'field_media_document' => ['target_id' => $file->id()],
    ]);
    $media->save();
    $this->markEntityForCleanup($media);

    return $media;
  }

}
