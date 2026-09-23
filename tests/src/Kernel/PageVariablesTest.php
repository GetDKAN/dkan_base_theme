<?php

namespace Drupal\Tests\dkan_base_theme\Kernel;

use Drupal\Tests\dkan_catalog_ui\Kernel\CatalogUiKernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * The header search form follows the catalog_search_path setting.
 */
#[Group('dkan_base_theme')]
class PageVariablesTest extends CatalogUiKernelTestBase {

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

  public function testHeaderSearchFollowsSetting(): void {
    $variables = [];
    dkan_base_theme_preprocess_page($variables);
    $html = (string) $this->container->get('renderer')->renderInIsolation($variables['dbt_search']);
    $this->assertStringContainsString('action="/search"', $html);
    $this->assertStringEndsWith('/logo.svg', $variables['dbt_logo']);
    $this->assertStringContainsString('name="fulltext"', $html);
    $this->assertStringContainsString('role="search"', $html);
    // Icon-only submit with a hidden label.
    $this->assertStringContainsString('dcu-icon--search', $html);
    $this->assertStringContainsString('<span class="visually-hidden">Search</span></button>', $html);

    $this->config('dkan_base_theme.settings')->set('catalog_search_path', 'catalog/find')->save();
    $variables = [];
    dkan_base_theme_preprocess_page($variables);
    $html = (string) $this->container->get('renderer')->renderInIsolation($variables['dbt_search']);
    $this->assertStringContainsString('action="/catalog/find"', $html);

    $this->config('dkan_base_theme.settings')->set('catalog_search_path', '')->save();
    $variables = [];
    dkan_base_theme_preprocess_page($variables);
    $this->assertNull($variables['dbt_search']);
  }

}
