<?php

namespace Drupal\Tests\dkan_base_theme\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\Tests\dkan_catalog_ui\Kernel\CatalogUiKernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * The classes the theme's CSS targets are present in rendered markup.
 *
 * Renders real blocks and theme hooks through the theme's templates. The
 * theme sets `base theme: false`, so without its templates Drupal falls back
 * to core module templates that lack these classes; this test fails then.
 */
#[Group('dkan_base_theme')]
class TemplateContractTest extends CatalogUiKernelTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'views',
    'search_api',
    'search_api_db',
    'facets',
    'dkan_metastore_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Installs the search view, whose route the main menu links to.
    $this->installSearchConfig();
    Role::load('anonymous')->grantPermission('access content')->save();
    $this->config('system.site')->set('name', 'Catalog')->set('slogan', 'Open data')->save();

    $this->container->get('theme_installer')->install(['dkan_base_theme']);
    $this->config('system.theme')->set('default', 'dkan_base_theme')->save();
    $this->container->get('theme.manager')->resetActiveTheme();
  }

  /**
   * Every case renders all of its required selectors.
   */
  public function testCssSelectorsMatch(): void {
    $missing = [];
    foreach ($this->cases() as $name => [$build, $selectors]) {
      $this->setRawContent((string) $this->container->get('renderer')->renderInIsolation($build));
      foreach ($selectors as $selector) {
        if (!$this->cssSelect($selector)) {
          $missing[] = "$name: $selector";
        }
      }
    }
    $this->assertSame([], $missing, 'Selectors with no match in the rendered markup.');
  }

  /**
   * Render arrays and the selectors each must produce.
   *
   * @return array<string, array{0: array<mixed>, 1: string[]}>
   *   Case name => [render array, CSS selectors].
   */
  protected function cases(): array {
    $this->container->get('pager.manager')->createPager(100, 10);
    $blocks = $this->container->get('entity_type.manager')->getViewBuilder('block');
    $node = Node::create(['type' => 'data', 'title' => 'Dataset', 'field_json_metadata' => '{}']);

    return [
      'main menu block' => [
        $blocks->view($this->block('system_menu_block:main', 'primary_menu')),
        ['nav.block.block-menu.menu--main', 'nav.block > ul.menu > li.menu-item > a'],
      ],
      'account menu block' => [
        $blocks->view($this->block('system_menu_block:account', 'secondary_menu')),
        ['nav.block.menu--account', 'ul.menu > li.menu-item > a .dcu-icon--user'],
      ],
      'branding block' => [
        $blocks->view($this->block('system_branding_block', 'header')),
        ['.block a.site-logo > img', '.site-name > a', '.site-slogan'],
      ],
      'menu state' => [
        [
          '#theme' => 'menu',
          '#menu_name' => 'main',
          '#items' => [
            'current' => [
              'title' => 'Datasets',
              'url' => Url::fromRoute('<front>'),
              'attributes' => new Attribute(),
              'below' => [],
              'in_active_trail' => TRUE,
              'is_expanded' => FALSE,
              'is_collapsed' => FALSE,
            ],
          ],
        ],
        ['ul.menu > li.menu-item.menu-item--active-trail > a'],
      ],
      'breadcrumb' => [
        [
          '#theme' => 'breadcrumb',
          '#links' => [
            Link::createFromRoute('Home', '<front>'),
            Link::fromTextAndUrl('Datasets', Url::fromUri('internal:/search')),
          ],
        ],
        ['nav.breadcrumb ol > li > a'],
      ],
      'region' => [
        ['#theme_wrappers' => ['region'], '#region' => 'header', 'child' => ['#markup' => 'Child']],
        ['div.region.region-header'],
      ],
      'local tasks' => [
        [
          '#theme' => 'menu_local_tasks',
          '#primary' => [
            [
              '#theme' => 'menu_local_task',
              '#link' => ['title' => 'View', 'url' => Url::fromRoute('<front>')],
              '#active' => TRUE,
            ],
            ['#theme' => 'menu_local_task', '#link' => ['title' => 'Edit', 'url' => Url::fromRoute('<front>')]],
          ],
          '#secondary' => [
            ['#theme' => 'menu_local_task', '#link' => ['title' => 'Sub', 'url' => Url::fromRoute('<front>')]],
          ],
        ],
        ['ul.tabs.primary > li.is-active > a', 'ul.tabs.secondary > li > a'],
      ],
      'status messages' => [
        [
          '#theme' => 'status_messages',
          '#message_list' => ['status' => ['Saved.', 'Also saved.'], 'error' => ['Failed.'], 'warning' => ['Careful.']],
          '#status_headings' => ['status' => 'Status', 'error' => 'Error', 'warning' => 'Warning'],
        ],
        [
          '.messages.messages--status .messages__list > .messages__item',
          '.messages.messages--error',
          '.messages.messages--warning',
        ],
      ],
      'pager' => [
        ['#type' => 'pager'],
        ['.pager__items > .pager__item.is-active', '.pager__items > .pager__item--ellipsis'],
      ],
      'page title' => [
        ['#type' => 'page_title', '#title' => 'Datasets'],
        ['h1.page-title'],
      ],
      'field' => [
        $node->get('field_json_metadata')->view(['label' => 'inline']),
        ['.field.field--label-inline > .field__label'],
      ],
      'form elements' => [
        [
          'text' => [
            '#type' => 'textfield',
            '#title' => 'Name',
            '#description' => $this->t('Help text.'),
            '#required' => TRUE,
            // The form builder sets this default; rendered alone, it is unset.
            '#description_display' => 'after',
          ],
          'check' => ['#type' => 'checkbox', '#title' => 'Agree'],
          'details' => [
            '#type' => 'details',
            '#title' => 'More',
            '#description' => $this->t('Details text.'),
            'child' => ['#markup' => 'Inside'],
          ],
          'fieldset' => ['#type' => 'fieldset', '#title' => 'Group', 'child' => ['#markup' => 'Inside']],
        ],
        [
          '.form-item.form-type-textfield > label.form-required',
          '.form-item.form-type-textfield .description',
          '.form-item.form-type-checkbox > label.option',
          'details .details-wrapper .details-description',
          'fieldset .fieldset-legend',
        ],
      ],
      'empty table' => [
        ['#type' => 'table', '#header' => ['Name'], '#rows' => [], '#empty' => 'Nothing yet.'],
        ['td.empty.message'],
      ],
    ];
  }

  /**
   * A block placed in the theme (saved: the view builder reloads it by id).
   */
  protected function block(string $plugin, string $region): Block {
    $block = Block::create([
      'id' => str_replace([':', '-'], '_', $plugin),
      'theme' => 'dkan_base_theme',
      'region' => $region,
      'plugin' => $plugin,
      'settings' => ['label_display' => FALSE],
    ]);
    $block->save();
    return $block;
  }

}
