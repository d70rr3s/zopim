<?php

namespace Drupal\zopim\Form;

use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function drupal_get_path;
use function file_get_contents;
use function filter_fallback_format;
use function str_replace;
use function strtoupper;
use function ucfirst;

/**
 * Provides the path admin overview form.
 */
class SettingsForm extends ConfigFormBase {

  const INTEGRATION_ZOPIM = 'zopim';

  const INTEGRATION_ZENDESK = 'zendesk';

  const INTEGRATION_EMBED = 'embed';

  const SNIPPET_BASE_PATH = 'public://zopim';

  /**
   * JS collection service.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface
   */
  private $jsCollection;

  /**
   * Cache-tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private $invalidator;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, AssetCollectionOptimizerInterface $jsCollection, CacheTagsInvalidatorInterface $invalidator) {
    parent::__construct($config_factory);
    $this->jsCollection = $jsCollection;
    $this->invalidator = $invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('asset.js.collection_optimizer'),
      $container->get('cache_tags.invalidator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'zopim_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('zopim.settings');

    $form['integration'] = [
      '#type' => 'select',
      '#title' => $this->t('Integration'),
      '#description' => $this->t('The integration method to be use to attach the live chat script.'),
      '#default_value' => $config->get('integration'),
      '#options' => [
        self::INTEGRATION_ZOPIM => $this->t(ucfirst(self::INTEGRATION_ZOPIM)),
        self::INTEGRATION_ZENDESK => $this->t(ucfirst(self::INTEGRATION_ZENDESK)),
        self::INTEGRATION_EMBED => $this->t(ucfirst(self::INTEGRATION_EMBED)),
      ],
      '#required' => TRUE,
    ];
    $form['zopim_account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Zopim account number'),
      '#description' => $this->t('The account number is unique to the websites domain and can be found in the script given to you by the Zopim dashboard settings.<br/>Go to <a href=":zopim_url">Zopim site</a>, login, click the settings tab and look at the code you are asked to paste into your site.<br/>The part of the code you need is:<br/>@code<br/>Where the x\'s represent your account number.', [
        ':zopim_url' => 'https://dashboard.zopim.com/#widget/getting_started',
        '@code' => '<code>$.src="//v2.zopim.com/?xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";</code>',
      ]),
      '#default_value' => $config->get('zopim_account'),
      '#size' => 40,
      '#maxlength' => 40,
      '#states' => [
        'visible' => [
          ':input[name*="integration"]' => ['value' => self::INTEGRATION_ZOPIM],
        ],
        'required' => [
          ':input[name*="integration"]' => ['value' => self::INTEGRATION_ZOPIM],
        ],
      ],
    ];
    $form['zendesk_hostname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Zendesk hostname'),
      '#description' => $this->t('The hostname of your Zendesk account, e.g. <code>example.zendesk.com</code>'),
      '#default_value' => $config->get('zendesk_hostname'),
      '#size' => 40,
      '#maxlength' => 40,
      '#states' => [
        'visible' => [
          ':input[name*="integration"]' => ['value' => self::INTEGRATION_ZENDESK],
        ],
        'required' => [
          ':input[name*="integration"]' => ['value' => self::INTEGRATION_ZENDESK],
        ],
      ],
    ];
    $embed = $config->get('embed');
    $form['embed'] = [
      '#title' => $this->t('Embed'),
      '#description' => $this->t('Instead of relying on the shipped scripts you can paste here the HTML/JS script provided to you by the service provider. <strong>Beware!</strong> This is not recommended as the provided script cannot be tested and also you may unintentionally introduce errors or security vulnerabilities.'),
      '#type' => 'text_format',
      '#default_value' => isset($embed['value']) ? $embed['value'] : NULL,
      '#format' => isset($embed['format']) ? $embed['format'] : filter_fallback_format(),
      '#states' => [
        'visible' => [
          ':input[name*="integration"]' => ['value' => self::INTEGRATION_EMBED],
        ],
        'required' => [
          ':input[name*="integration"]' => ['value' => self::INTEGRATION_EMBED],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('zopim.settings')
      ->set('integration', $values['integration'])
      ->set('zopim_account', $values['zopim_account'])
      ->set('zendesk_hostname', $values['zendesk_hostname'])
      ->set('embed', $values['embed'])
      ->save();

    parent::submitForm($form, $form_state);

    $this->createAssets();
  }

  /**
   * Prepares directory for and saves snippet files based on current settings.
   *
   * @return bool
   *   Whether the files were saved.
   */
  public function createAssets() {
    $result = TRUE;
    $directory = self::SNIPPET_BASE_PATH;

    if (!is_dir($directory) || !is_writable($directory)) {
      $result = file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
    }
    if ($result) {
      $result = $this->saveSnippets();
    }
    else {
      drupal_set_message($this->t('Failed to create or make writable the directory %directory, possibly due to a permissions problem. Make the directory writable.', ['%directory' => $directory]), 'error');
    }

    return $result;
  }

  /**
   * Saves JS snippet files based on current settings.
   *
   * @return bool
   *   Whether the files were saved.
   */
  private function saveSnippets() {
    $integration = $this->config('zopim.settings')->get('integration');

    // If integration is embed the config value is the actual script content.
    if ($integration === self::INTEGRATION_EMBED) {
      $snippet = $this->config('zopim.settings')->get('embed.value');
    }
    else {
      // Otherwise locate the proper snippet and replace the integration
      // placeholder with the proper value.
      $snippet = file_get_contents(drupal_get_path('module', 'zopim') . '/snippets/' . $integration . '.js');
      if ($integration === self::INTEGRATION_ZOPIM) {
        $value = $this->config('zopim.settings')->get('zopim_account');
      }
      else {
        $value = $this->config('zopim.settings')->get('zendesk_hostname');
      }

      $snippet = str_replace('[' . strtoupper($integration) . ']', $value, $snippet);
    }

    // Save the snippet file so can be attached to the block.
    $path = sprintf('%s/%s.js', self::SNIPPET_BASE_PATH, $integration);
    $path = file_unmanaged_save_data($snippet, $path, FILE_EXISTS_REPLACE);
    if ($path === FALSE) {
      drupal_set_message($this->t('An error occurred saving the snippet file. Please try again or contact the site administrator if it persists.'), 'error');
    }
    else {
      drupal_set_message($this->t('Created %file snippet file based on configuration.', [
          '%file' => $path,
        ]), 'status');
      // Clear all caches.
      $this->invalidator->invalidateTags(['zopim.settings']);
      $this->jsCollection->deleteAll();
      _drupal_flush_css_js();
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['zopim.settings'];
  }

}
