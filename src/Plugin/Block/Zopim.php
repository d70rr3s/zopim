<?php

namespace Drupal\zopim\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\StateInterface;
use Drupal\zopim\Form\SettingsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to attach Zopim live-chat.
 *
 * @Block(
 *   id = "zopim",
 *   admin_label = @Translation("Zopim live-chat"),
 *   category = @Translation("Services"),
 * )
 */
class Zopim extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $zopimSettings;

  /**
   * The states API service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, StateInterface $state, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->zopimSettings = $config_factory->get('zopim.settings');
    $this->state = $state;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $integration = $this->zopimSettings->get('integration');
    $uri = sprintf('%s/%s.js', SettingsForm::SNIPPET_BASE_PATH, $integration);
    $url = file_url_transform_relative(file_create_url($uri));
    $query_string = $this->state->get('system.css_js_query_string') ?: '0';
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#attributes' => ['src' => $url . '?' . $query_string],
      '#cache' => [
        'max-age' => Cache::PERMANENT,
        'contexts' => ['user.roles', 'languages'],
        'tags' => ['zopim.settings'],
      ],
    ];
    $html = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
      return $this->renderer->render($build);
    });


    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $html,
    ];
  }

}
