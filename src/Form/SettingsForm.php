<?php

/**
 * @file
 * Contains \Drupal\zopim\Form\SettingsForm.
 */

namespace Drupal\zopim\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the path admin overview form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'zopim_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['zopim.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('zopim.settings');

    $form['account_number'] = [
      '#type' => 'textfield',
      '#title' => t('Zopim Chat account number'),
      '#default_value' => $config->get('account_number'),
      '#description' => t('The account number is unique to the websites domain and can be found in the script given to you by the Zopim dashboard settings.<br/>Go to <a href=":zopim_url">Zopim Chat Site</a>, login, click the settings tab and look at the code you are asked to paste into your site.<br/>The part of the code you need is:<br/>@code<br/>Where the x\'s represent your account number.', array(':zopim_url' => 'https://dashboard.zopim.com/#widget/getting_started', '@code' => '<code>$.src="//v2.zopim.com/?xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";</code>')),
      '#size' => 40,
      '#maxlength' => 40,
      '#required' => TRUE,
    ];
    $form['visibility'] = [
      '#type' => 'radios',
      '#title' => t('Show Zopim Chat widget on specific pages'),
      '#options' => [
        ZOPIM_BLACKLIST_MODE => t('Hide for the listed pages'),
        ZOPIM_WHITELIST_MODE => t('Show for the listed pages'),
      ],
      '#default_value' => $config->get('visibility'),
    ];
    $form['pages'] = [
      '#type' => 'textarea',
      '#title' => '<span class="visually-hidden">' . t('Pages') . '</span>',
      '#default_value' => _zopim_array_to_string($config->get('pages')),
      '#description' => t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %user for the current user's page and %user-wildcard for every user page. %front is the front page.", array('%user' => '/user', '%user-wildcard' => '/user/*', '%front' => '<front>')),
    ];
    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => t('Hide Zopim Chat widget for specific roles'),
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', user_role_names()),
      '#default_value' => $config->get('roles'),
      '#description' => t('Hide Zopim Chat widget only for the selected role(s). If none of the roles are selected, all roles will have the widget. Otherwise, any roles selected here will NOT have the widget.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('zopim.settings')
      ->set('account_number', $values['account_number'])
      ->set('visibility', $values['visibility'])
      ->set('pages', _zopim_string_to_array($values['pages']))
      ->set('roles', array_filter($values['roles']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}