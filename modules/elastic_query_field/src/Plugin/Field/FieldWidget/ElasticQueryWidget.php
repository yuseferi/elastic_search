<?php

namespace Drupal\elastic_query_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'elastic_query_widget' widget.
 *
 * @FieldWidget(
 *   id = "elastic_query_widget",
 *   label = @Translation("Elastic query widget"),
 *   field_types = {
 *     "elastic_query_field"
 *   }
 * )
 */
class ElasticQueryWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
             'size'        => 60,
             'placeholder' => '',
             'validate'    => FALSE,
           ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['size'] = [
      '#type'          => 'number',
      '#title'         => t('Size of textfield'),
      '#default_value' => $this->getSetting('size'),
      '#required'      => TRUE,
      '#min'           => 1,
    ];
    $elements['placeholder'] = [
      '#type'          => 'textarea',
      '#title'         => t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description'   => t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    $elements['validate'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Validation'),
      '#default_value' => $this->getSetting('validate'),
      '#description'   => t('If set to true then the query will be sent to the configured server at save time and will be validated. Invalid dsl will cause the node validation to fail'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = t('Textfield size: @size',
                   ['@size' => $this->getSetting('size')]);
    if (!empty($this->getSetting('placeholder'))) {
      $summary[] = t('Placeholder: @placeholder',
                     ['@placeholder' => $this->getSetting('placeholder')]);
    }

    $summary[] = t('Validate: @validate',
                   ['@validate' => $this->getSetting('validate') ? 'True' : 'False']);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items,
                              $delta,
                              array $element,
                              array &$form,
                              FormStateInterface $form_state) {
    $element['value'] = $element + [
        '#type'          => 'textarea',
        '#default_value' => $items[$delta]->value ?? NULL,
        '#size'          => $this->getSetting('size'),
        '#placeholder'   => $this->getSetting('placeholder'),
        '#suffix'        => '<div id="editor"/>',
        '#attributes'    => [
          'data-editor'       => ['json'],
          'data-editor-theme' => ['monokai'],
        ],
        '#attached'      => [
          'library' => [
            'elastic_search/ace_json',
          ],
        ],
      ];

    return $element;
  }

  /**
   * @param array                                $form
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *
   * @throws \Exception
   */
  public static function validateQuery(array &$form, FormStateInterface $formState) {

    //TODO - should be a real constraint
    $connection = \Drupal::getContainer()->get('elastic_search.connection_factory')->getElasticConnection();
    $result = $connection->indices()->validateQuery($formState->getValue('value'));

  }

}
