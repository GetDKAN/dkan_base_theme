<?php

namespace Drupal\Tests\dkan_base_theme\Unit;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * The catalog search path validator rejects external values.
 */
#[Group('dkan_base_theme')]
class SearchPathValidationTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    // The kernel registers the allowed protocols at boot; UrlHelper treats
    // a scheme it does not know as stripped, not external.
    UrlHelper::setAllowedProtocols(['http', 'https', 'mailto']);
    require_once __DIR__ . '/../../../dkan_base_theme.theme';
  }

  public function testValidation(): void {
    $cases = [
      '' => FALSE,
      '/search' => FALSE,
      'catalog/find' => FALSE,
      'https://data.example.gov/search' => TRUE,
      '//evil.example' => TRUE,
      'mailto:x@example.com' => TRUE,
    ];
    foreach ($cases as $value => $expectError) {
      $form_state = new FormState();
      $element = ['#value' => $value, '#parents' => ['catalog_search_path']];
      dkan_base_theme_validate_search_path($element, $form_state);
      $this->assertSame($expectError, (bool) $form_state->getErrors(), (string) $value);
    }
  }

}
