<?php

namespace Drupal\Tests\dkan_base_theme\Kernel;

use Drupal\Tests\dkan_catalog_ui\Kernel\CatalogUiKernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Installing the theme places the shipped blocks and copies none.
 */
#[Group('dkan_base_theme')]
class ThemeInstallTest extends CatalogUiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * Merge the base class modules (the theme depends on the module).
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    static::$modules = array_values(array_unique(array_merge(parent::$modules, static::$modules)));
  }

  public function testInstallPlacesShippedBlocks(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('block');
    $themeInstaller = $this->container->get('theme_installer');

    // A default theme with a block the block module would otherwise copy.
    $themeInstaller->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $storage->create([
      'id' => 'stark_tools',
      'theme' => 'stark',
      'region' => 'sidebar_first',
      'plugin' => 'system_menu_block:tools',
    ])->save();

    $themeInstaller->install(['dkan_base_theme']);

    $ids = array_keys($storage->loadByProperties(['theme' => 'dkan_base_theme']));
    sort($ids);
    $expected = [
      'account_menu', 'branding', 'breadcrumbs', 'content', 'footer_menu',
      'local_actions', 'local_tasks', 'main_menu', 'messages', 'page_title',
    ];
    $this->assertSame(array_map(fn ($id) => "dkan_base_theme_$id", $expected), $ids);
    $this->assertArrayNotHasKey('dkan_base_theme_tools', array_flip($ids));

    $regions = array_keys($this->container->get('theme_handler')->getTheme('dkan_base_theme')->info['regions']);
    foreach ($storage->loadByProperties(['theme' => 'dkan_base_theme']) as $block) {
      $this->assertContains($block->getRegion(), $regions, $block->id());
    }
  }

}
