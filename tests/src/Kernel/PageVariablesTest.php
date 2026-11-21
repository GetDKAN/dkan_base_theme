<?php

namespace Drupal\Tests\dkan_base_theme\Kernel;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\Group;

/**
 * The header search form follows the catalog_search_path setting.
 */
#[Group('dkan_base_theme')]
class PageVariablesTest extends ThemeKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['dkan_base_theme']);
    $this->config('system.theme')->set('default', 'dkan_base_theme')->save();
    $this->container->get('theme.manager')->resetActiveTheme();
    // Preprocess functions live in the .theme file.
    $this->container->get('theme.initialization')->initTheme('dkan_base_theme');
  }

  /**
   * Run the page preprocess for a setting value.
   *
   * @return array<string, mixed>
   *   The preprocessed variables.
   */
  protected function preprocess(?string $path): array {
    if ($path !== NULL) {
      $this->config('dkan_base_theme.settings')->set('catalog_search_path', $path)->save();
    }
    $variables = [];
    dkan_base_theme_preprocess_page($variables);
    return $variables;
  }

  public function testHeaderSearchFollowsSetting(): void {
    $variables = $this->preprocess(NULL);
    $html = (string) $this->container->get('renderer')->renderInIsolation($variables['dbt_search']);
    $this->assertStringContainsString('action="/search"', $html);
    $this->assertStringEndsWith('/logo.svg', $variables['dbt_logo']);
    $this->assertStringContainsString('name="fulltext"', $html);
    $this->assertStringContainsString('role="search"', $html);
    // Icon-only submit with a hidden label.
    $this->assertStringContainsString('dcu-icon--search', $html);
    $this->assertStringContainsString('<span class="visually-hidden">Search</span></button>', $html);
    $this->assertContains('config:dkan_base_theme.settings', $variables['#cache']['tags']);

    $variables = $this->preprocess('catalog/find');
    $html = (string) $this->container->get('renderer')->renderInIsolation($variables['dbt_search']);
    $this->assertStringContainsString('action="/catalog/find"', $html);

    $variables = $this->preprocess('');
    $this->assertNull($variables['dbt_search']);
    // The page still carries the setting tag so a later value shows up.
    $this->assertContains('config:dkan_base_theme.settings', $variables['#cache']['tags']);
  }

  /**
   * A subtheme inherits the base value until it saves its own.
   */
  public function testSubthemeSetting(): void {
    $this->container->get('theme_installer')->install(['dbt_sub']);
    $this->config('system.theme')->set('default', 'dbt_sub')->save();
    $this->container->get('theme.manager')->resetActiveTheme();
    $this->container->get('theme.initialization')->initTheme('dbt_sub');

    $variables = $this->preprocess(NULL);
    $html = (string) $this->container->get('renderer')->renderInIsolation($variables['dbt_search']);
    $this->assertStringContainsString('action="/search"', $html);
    $this->assertContains('config:dbt_sub.settings', $variables['#cache']['tags']);
    $this->assertContains('config:dkan_base_theme.settings', $variables['#cache']['tags']);
    $this->assertContains('config:dbt_sub.settings', $variables['dbt_search']['#cache']['tags']);

    // The settings form shows the inherited value and registers the submit
    // handler that invalidates the tag on a first save.
    $form = [];
    $form_state = new FormState();
    $form_state->addBuildInfo('args', ['dbt_sub']);
    dkan_base_theme_form_system_theme_settings_alter($form, $form_state);
    $this->assertSame('/search', $form['dkan_base_theme']['catalog_search_path']['#default_value']);
    $this->assertContains('dkan_base_theme_settings_submit', $form['#submit']);

    // Core does not invalidate the tag when the config object is created,
    // which is what the submit handler is for.
    $this->config('dbt_sub.settings')->set('catalog_search_path', '/find')->save();
    dkan_base_theme_settings_submit($form, $form_state);
    $variables = $this->preprocess(NULL);
    $html = (string) $this->container->get('renderer')->renderInIsolation($variables['dbt_search']);
    $this->assertStringContainsString('action="/find"', $html);

    // An empty subtheme value hides the form; the base value is not used.
    $this->config('dbt_sub.settings')->set('catalog_search_path', '')->save();
    $this->assertNull($this->preprocess(NULL)['dbt_search']);
    $this->assertSame('', dkan_base_theme_search_path('dbt_sub'));
    $this->assertSame('/search', dkan_base_theme_search_path('dkan_base_theme'));
  }

  /**
   * External values hide the form instead of throwing.
   */
  public function testExternalPathHidesForm(): void {
    foreach (['https://data.example.gov/search', '//evil.example', 'mailto:x@example.com'] as $path) {
      $variables = $this->preprocess($path);
      $this->assertNull($variables['dbt_search'], $path);
      $this->assertContains('config:dkan_base_theme.settings', $variables['#cache']['tags']);
    }
  }

  /**
   * The settings form rejects external values and accepts internal ones.
   */
  public function testSearchPathValidation(): void {
    $cases = [
      '' => FALSE,
      '/search' => FALSE,
      'catalog/find' => FALSE,
      'https://data.example.gov/search' => TRUE,
      '//evil.example' => TRUE,
    ];
    foreach ($cases as $value => $expectError) {
      $form_state = new FormState();
      $element = ['#value' => $value, '#parents' => ['catalog_search_path']];
      dkan_base_theme_validate_search_path($element, $form_state);
      $this->assertSame($expectError, (bool) $form_state->getErrors(), (string) $value);
    }
  }

}
