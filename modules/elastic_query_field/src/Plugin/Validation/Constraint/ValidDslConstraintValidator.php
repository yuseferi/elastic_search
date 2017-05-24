<?php
/**
 * Created by PhpStorm.
 * User: twhiston
 * Date: 19.02.17
 * Time: 10:51
 */

namespace Drupal\elastic_query_field\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidDslConstraintValidator extends ConstraintValidator {

  /**
   * @inheritDoc
   */
  public function validate($value, Constraint $constraint) {
    // TODO: Implement validate() method.
    $a = 0;
  }

}