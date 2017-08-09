<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Validation\ExerciseConfigValidator;
use App\Helpers\ExerciseConfig\Validation\ExerciseLimitsValidator;
use App\Helpers\ExerciseConfig\Validation\PipelineValidator;
use App\Model\Entity\Exercise;


/**
 * Validator which should be used for whole exercise configuration machinery.
 * Rather than for internal validation of structures themselves, this helper
 * is considered only for validation of cross-structures data and references.
 * In here identifications of pipelines can be included or variables and
 * proper types of two joined ports. Also proper environments and hwgroups
 * can be checked here.
 */
class Validator {

  /**
   * @var Loader
   */
  private $loader;

  /**
   * @var ExerciseConfigValidator
   */
  private $exerciseConfigValidator;

  /**
   * @var PipelineValidator
   */
  private $pipelineValidator;

  /**
   * @var ExerciseLimitsValidator
   */
  private $exerciseLimitsValidator;

  /**
   * Validator constructor.
   * @param Loader $loader
   * @param ExerciseConfigValidator $exerciseConfigValidator
   * @param PipelineValidator $pipelineValidator
   * @param ExerciseLimitsValidator $exerciseLimitsValidator
   */
  public function __construct(Loader $loader, ExerciseConfigValidator $exerciseConfigValidator,
      PipelineValidator $pipelineValidator, ExerciseLimitsValidator $exerciseLimitsValidator) {
    $this->loader = $loader;
    $this->exerciseConfigValidator = $exerciseConfigValidator;
    $this->pipelineValidator = $pipelineValidator;
    $this->exerciseLimitsValidator = $exerciseLimitsValidator;
  }


  /**
   * Validate pipeline, all input ports have to have specified either variable
   * reference or textual value. Variable reference has to be used only
   * two times, one should point to input port and second one to output port.
   * Exception is if variable reference is specified only in output port, then
   * this variable does not have to be used in any input port.
   * @param Pipeline $pipeline
   * @throws ExerciseConfigException
   */
  public function validatePipeline(Pipeline $pipeline) {
    $this->pipelineValidator->validate($pipeline);
  }

  /**
   * Validation of exercise configuration against environment configurations,
   * that means mainly runtime environment identification. Another checks are
   * made against pipeline, again identification of pipeline is checked,
   * but in here also variables and if pipeline requires them is checked.
   * @param Exercise $exercise
   * @param ExerciseConfig $config
   * @throws ExerciseConfigException
   */
  public function validateExerciseConfig(Exercise $exercise, ExerciseConfig $config) {
    $variablesTables = array();
    foreach ($exercise->getExerciseEnvironmentConfigs() as $environmentConfig) {
      $varTable = $this->loader->loadVariablesTable($environmentConfig->getParsedVariablesTable());
      $variablesTables[$environmentConfig->getRuntimeEnvironment()->getId()] = $varTable;
    }

    $this->exerciseConfigValidator->validate($config, $variablesTables);
  }

  /**
   * Validation of exercise limits, limits are defined for boxes which comes
   * from pipelines, identification of pipelines is taken from
   * exercise configuration, after that box identifications are checked if
   * existing.
   * @param Exercise $exercise
   * @param ExerciseLimits $limits
   * @param string $environmentId
   * @throws ExerciseConfigException
   */
  public function validateExerciseLimits(Exercise $exercise, ExerciseLimits $limits, string $environmentId) {
    $exerciseConfig = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    $this->exerciseLimitsValidator->validate($limits, $exerciseConfig, $environmentId);
  }

}