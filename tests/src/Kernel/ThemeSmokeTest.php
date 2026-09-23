<?php

namespace Drupal\Tests\dkan_base_theme\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\dkan_catalog_ui\Hook\EntityHooks;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\dkan_catalog_ui\Kernel\CatalogUiKernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Catalog renders under the theme attach the catalog library; others do not.
 */
#[Group('dkan_base_theme')]
class ThemeSmokeTest extends CatalogUiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'search_api',
    'search_api_db',
    'facets',
    'dkan_metastore_search',
  ];

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    static::$modules = array_values(array_unique(array_merge(parent::$modules, static::$modules)));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    $this->installConfig(['search_api', 'dkan_metastore_search', 'dkan_catalog_ui']);
    \Drupal::moduleHandler()->loadInclude('dkan_catalog_ui', 'install');
    dkan_catalog_ui_ensure_format_index_field();
    EntityViewDisplay::load('node.data.default')
      ->setComponent(EntityHooks::FIELD_HEADER, ['weight' => 0])
      ->setComponent(EntityHooks::FIELD_OVERVIEW, ['weight' => 20])
      ->save();

    $this->container->get('theme_installer')->install(['dkan_base_theme']);
    $this->config('system.theme')->set('default', 'dkan_base_theme')->save();
    $this->container->get('theme.manager')->resetActiveTheme();
  }

  /**
   * Header DOM: branding, toggle, panel (primary then account), search.
   */
  public function testHeader(): void {
    $menu = fn (string $id, string $label, string $href) => [
      '#markup' => '<nav aria-labelledby="' . $id . '"><h2 id="' . $id . '">' . $label . '</h2><ul class="menu"><li class="menu-item"><a href="' . $href . '">' . $label . '</a></li></ul></nav>',
    ];
    // Regions are top-level keys of the page render element.
    $build = [
      '#theme' => 'page',
      'header' => ['#markup' => '<a class="site-logo" href="/">Home</a>'],
      'primary_menu' => $menu('main', 'Datasets', '/search'),
      'secondary_menu' => $menu('account', 'Log in', '/user/login'),
      'content' => ['#markup' => 'Body'],
      'footer_menu' => $menu('footer', 'About', '/node/1'),
      'footer' => ['#markup' => '<p class="footer-text">Footer region</p>'],
    ];
    $html = (string) $this->container->get('renderer')->renderRoot($build);

    $order = [
      'class="site-logo"',
      'data-dbt-menu-toggle',
      'id="dbt-header-panel"',
      'class="dbt-header__primary"',
      'class="dbt-header__secondary"',
      'class="dbt-header__search"',
    ];
    $last = -1;
    foreach ($order as $needle) {
      $position = strpos($html, $needle);
      $this->assertNotFalse($position, $needle);
      $this->assertGreaterThan($last, $position, $needle);
      $last = $position;
    }
    $this->assertStringContainsString('aria-controls="dbt-header-panel"', $html);
    $this->assertStringContainsString('dcu-icon--menu', $html);
    $this->assertStringContainsString('dcu-icon--close', $html);
    $this->assertStringNotContainsString('dbt-header__primary"><div class="dbt-container"', $html);
    // The three menu blocks keep their own landmarks; the template adds none.
    $this->assertSame(3, substr_count($html, '<nav '));
    // Footer: wordmark linking home, menu, Powered by DKAN, then the region.
    $this->assertStringContainsString('<a class="dbt-footer__brand" href="/" rel="home">', $html);
    $this->assertStringContainsString('Powered by <a href="https://getdkan.org">DKAN</a>', $html);
    $last = -1;
    $footer = [
      'class="dbt-footer__brand"',
      'class="dbt-footer__menu"',
      'aria-labelledby="footer"',
      'dbt-footer__powered',
      'class="dbt-footer__content"',
      'Footer region',
    ];
    foreach ($footer as $needle) {
      $position = strpos($html, $needle);
      $this->assertNotFalse($position, $needle);
      $this->assertGreaterThan($last, $position, $needle);
      $last = $position;
    }
  }

  public function testLibraryAttachment(): void {
    $this->createDataset('smoke-test');
    Index::load('dkan')->indexItems();
    $renderer = $this->container->get('renderer');
    $viewBuilder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    $storage = $this->container->get('entity_type.manager')->getStorage('node');

    $view = Views::getView('dkan_catalog_ui_search');
    $view->setDisplay('page_1');
    $view->setExposedInput(['fulltext' => '']);
    $view->execute();
    $build = $view->render('page_1');
    $html = (string) $renderer->renderRoot($build);
    $this->assertStringContainsString('dcu-card', $html);
    $this->assertContains('dkan_base_theme/catalog', $build['#attached']['library']);

    $dataset = $storage->loadByProperties(['uuid' => 'smoke-test']);
    $build = $viewBuilder->view(reset($dataset), 'full');
    $html = (string) $renderer->renderRoot($build);
    $this->assertStringContainsString('dcu-dataset', $html);
    $this->assertContains('dkan_base_theme/catalog', $build['#attached']['library']);

    $distribution = $storage->loadByProperties(['field_data_type' => 'distribution']);
    $build = $viewBuilder->view(reset($distribution), 'full');
    $renderer->renderRoot($build);
    $this->assertNotContains('dkan_base_theme/catalog', $build['#attached']['library'] ?? []);

    $build = $viewBuilder->view(reset($dataset), 'search_result');
    $renderer->renderRoot($build);
    $this->assertNotContains('dkan_base_theme/catalog', $build['#attached']['library'] ?? []);
  }

}
