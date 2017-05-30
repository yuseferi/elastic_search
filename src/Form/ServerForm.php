<?php

namespace Drupal\elastic_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ServerForm.
 *
 * @package Drupal\elastic_search\Form
 */
class ServerForm extends ConfigFormBase {

  private $configuration;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'elastic_search.server',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elastic_search_server_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->configuration = $this->config('elastic_search.server');
    $form['scheme'] = [
      '#type'          => 'select',
      '#title'         => $this->t('HTTP protocol'),
      '#description'   => $this->t('The HTTP protocol to use for sending queries.'),
      '#default_value' => $this->configuration->get('scheme'),
      '#options'       => [
        'http'  => 'http',
        'https' => 'https',
      ],
    ];

    $form['host'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Elastic host'),
      '#description'   => $this->t('The host name or IP of your Elastic server, e.g. <code>localhost</code> or <code>www.example.com</code>. WITHOUT http(s)'),
      '#default_value' => $this->configuration->get('host'),
      '#required'      => TRUE,
    ];

    $form['port'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Elastic port'),
      '#description'   => $this->t('Http default is 9200. For https default is usually 9243'),
      '#default_value' => $this->configuration->get('port'),
      '#required'      => TRUE,
    ];

    $form['kibana'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Kibana host'),
      '#description'   => $this->t('The host name or IP of your Kibana server, e.g. <code>localhost</code> or <code>www.example.com</code>.'),
      '#default_value' => $this->configuration->get('kibana'),
    ];

    $form['index_prefix'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Index prefix'),
      '#description'   => $this->t('Anything entered in this field will be directly prefixed to the index names. The prefix is added dynamically, therefore if you change the prefix you should be sure to delete your existing indices first'),
      '#default_value' => $this->configuration->get('index_prefix'),
    ];

    $form['auth'] = [
      '#tree'        => TRUE,
      '#type'        => 'details',
      '#title'       => $this->t('Elastic authentication'),
      '#description' => $this->t('If your Elastic server is protected, enter the login data here.'),
      '#collapsible' => TRUE,
      '#collapsed'   => TRUE,
    ];

    $form['auth']['username'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Username'),
      '#default_value' => $this->configuration->get('auth.username'),
    ];
    $form['auth']['password'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Password'),
      '#description' => $this->t('If this field is left blank and the username is filled out, the current password will not be changed.'),
    ];

    $form['advanced'] = [
      '#tree'        => TRUE,
      '#type'        => 'details',
      '#title'       => $this->t('Advanced Configuration'),
      '#collapsible' => TRUE,
      '#collapsed'   => TRUE,
    ];

    $form['advanced']['pause'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Pause duration'),
      '#default_value' => $this->configuration->get('advanced.pause'),
      '#description'   => $this->t('If this is set then at the end of any batch operation that contacts the remote server the function will sleep for this time. This may help relieve memory pressure on constrained elastic resources'),
    ];

    $form['advanced']['developer'] = [
      '#tree'        => TRUE,
      '#type'        => 'details',
      '#title'       => $this->t('Developer Settings'),
      '#collapsible' => TRUE,
      '#collapsed'   => TRUE,
    ];

    $form['advanced']['developer']['active'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Developer/Debug mode'),
      '#description'   => $this->t('Set developer/debug mode to true, this injects a logger into the elastic search library, should not be used in prod due to performance concerns'),
      '#default_value' => $this->configuration->get('advanced.developer.active'),
    ];

    $form['advanced']['developer']['logging_channel'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('logging channel'),
      '#description'   => $this->t('The name of the logging channel to use for developer output'),
      '#default_value' => $this->configuration->get('advanced.developer.logging_channel'),
    ];

    $form['advanced']['validate'] = [
      '#tree'        => TRUE,
      '#type'        => 'details',
      '#title'       => $this->t('Connection Validation'),
      '#collapsible' => TRUE,
      '#collapsed'   => TRUE,
    ];

    $form['advanced']['validate']['active'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Validate connection'),
      '#description'   => $this->t('If this is true ElasticConnectionFactory will try to connect to the index before returning the connection'),
      '#default_value' => $this->configuration->get('advanced.validate.active'),
    ];

    $form['advanced']['validate']['die_hard'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Hard exceptions'),
      '#description'   => $this->t('If this is true ElasticConnectionFactory will not gracefully fail if a connection cannot be validated'),
      '#default_value' => $this->configuration->get('advanced.validate.die_hard'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = &$form_state->getValues();
    if (isset($values['port']) &&
        (!is_numeric($values['port']) || $values['port'] < 0 ||
         $values['port'] > 65535)
    ) {
      $form_state->setError($form['port'],
                            $this->t('The port has to be an integer between 0 and 65535.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Config\ConfigValueException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->config('elastic_search.server');

    //If neither the username or pass are blank, or both are blank, reset
    if (($form_state->getValue(['auth', 'password']) !== '' &&
         $form_state->getValue(['auth', 'user']) !== '') ||
        ($form_state->getValue(['auth', 'password']) === '' &&
         $form_state->getValue(['auth', 'user']) === '')
    ) {
      //TODO - hash pass
      $config->set('auth.password', $form_state->getValue(['auth', 'password']))
             ->set('auth.username',
                   $form_state->getValue(['auth', 'username']));
    }

    $config->set('scheme', $form_state->getValue('scheme'))
           ->set('host', $form_state->getValue('host'))
           ->set('port', $form_state->getValue('port'))
           ->set('kibana', $form_state->getValue('kibana'))
           ->set('index_prefix', $form_state->getValue('index_prefix'))
           ->set('advanced.developer.active',
                 $form_state->getValue(['advanced', 'developer', 'active']))
           ->set('advanced.developer.logging_channel',
                 $form_state->getValue([
                                         'advanced',
                                         'developer',
                                         'logging_channel',
                                       ]))
           ->set('advanced.validate.active',
                 $form_state->getValue(['advanced', 'validate', 'active']))
           ->set('advanced.validate.die_hard',
                 $form_state->getValue(['advanced', 'validate', 'die_hard']))
           ->set('advanced.pause', $form_state->getValue(['advanced', 'pause']))
           ->save();
  }

}
