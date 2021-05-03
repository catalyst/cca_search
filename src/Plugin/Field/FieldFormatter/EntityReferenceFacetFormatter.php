<?php

namespace Drupal\cca_search\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'entity reference facet' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_facet",
 *   label = @Translation("Facet"),
 *   description = @Translation("Link referenced entities to facet search results."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceFacetFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'facet' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $options = [];
    $facets = \Drupal::entityTypeManager()->getStorage('facets_facet')->loadMultiple();

    foreach ($facets as $facet) {
      $options[$facet->id()] = $facet->getName();
    }

    $elements['facet'] = [
      '#title' => t('Select the facet to use.'),
      '#type' => 'select',
      '#required' => FALSE,
      '#empty_value' => '',
      '#default_value' => $this->getSetting('facet'),
      '#options' => $options,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($this->getSetting('facet')) {
      $summary[] = t('Link to the facet: @facet', [
        '@facet' => $this->getSetting('facet')
      ]);
    }
    else {
      $summary[] = t('No facet selected, plain text label will be used.');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $elements = [];
    $output_as_facet = $this->getSetting('facet');
    $url_generator = \Drupal::service('facets.utility.url_generator');

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $label = $entity->label();

      if ($output_as_facet) {

        // Set second arg ($keep_active) to false to reset facets.
        $url = $url_generator->getUrl([$output_as_facet => [$label]], FALSE);
        $url->setRouteParameters([]);

        // Unset keys to search from all items.
        $url_options = $url->getOptions();
        unset($url_options['query']['keys']);
        $url->setOptions($url_options);

        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $url,
        ];

        if (!empty($items[$delta]->_attributes)) {
          if (isset($elements[$delta]['#options'])) {
            $elements[$delta]['#options'] += ['attributes' => []];
          }
          else {
            $elements[$delta]['#options'] = ['attributes' => []];
          }
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }
      }
      else {
        $elements[$delta] = ['#plain_text' => $label];
      }
      $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view label', NULL, TRUE);
  }

}
