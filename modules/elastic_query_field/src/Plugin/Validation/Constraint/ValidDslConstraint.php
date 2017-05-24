<?php

namespace Drupal\elastic_query_field\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if an entity field has a unique value.
 *
 * @Constraint(
 *   id = "ValidDsl",
 *   label = @Translation("Valid Dsl constraint", context = "Validation"),
 * )
 */
class ValidDslConstraint extends Constraint {

  public $message = 'A @entity_type with @field_name %value already exists.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy() {
    return '\Drupal\elastic_query_field\Plugin\Validation\Constraint\ValidDslConstraintValidator';
  }

}
