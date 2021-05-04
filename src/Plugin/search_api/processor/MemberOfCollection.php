<?php

namespace Drupal\cca_search\Plugin\search_api\processor;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Exclude non-collection nodes referenced by field_member_of.
 *
 * @SearchApiProcessor(
 *   id = "member_of_collection",
 *   label = @Translation("Member of collection"),
 *   description = @Translation("Only index field_member_of values when referenced node is collection"),
 *   stages = {
 *     "alter_items" = 0,
 *   },
 * )
 */
class MemberOfCollection extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'node') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      // Get node being indexed.
      $object = $item->getOriginalObject();
      $node = $object->getValue();
      if ($node->hasField('field_member_of') && !$node->field_member_of->isEmpty()) {
        $parents = $object->get('field_member_of')->getValue();
        $node_storage = \Drupal::entityTypeManager()->getStorage('node');
        $taxonomy_term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

        if (isset($parents)) {
          // Why does referencedEntities() return empty when $parent has target_ids?
          // $entities = $node->get('field_member_of')->referencedEntities();
          $collections = [];
          foreach ($parents as $delta => $parent) {
            // Load parent node.
            $parent_node = $node_storage->load($parent['target_id']);
            // Is parent a Collection?
            if (is_object($parent_node)) {
              $model_tid = $parent_node->get('field_model')
                ->first()
                ->getValue()['target_id'];
              $model_term = $taxonomy_term_storage->load($model_tid);
              if (is_object($model_term)) {
                $model_term_uri = $model_term->get('field_external_uri')->first()->getUrl()->getUri();
                if ($model_term_uri == 'http://purl.org/dc/dcmitype/Collection') {
                  $collections[] = $parent_node->id();
                }
              }
            }
          }
          // Modify node with allowed member_of values, perhaps [].
          $node->set('field_member_of', $collections);
          // Replace original object with modified $node value.
          $object->setValue($node);
          $items[$item_id]->setOriginalObject($object);
        }
      } // /hasField
    }
  }

}
