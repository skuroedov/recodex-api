<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\ExerciseConfig\Compilation\ExerciseConfigCompiler;
use App\Helpers\JobConfig\JobConfig;
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\RuntimeEnvironment;

/**
 * Compiler used for generating JobConfig structure from ExerciseConfig,
 * meaning, high-level format is compiled into low-level format which can be
 * executed on backend workers.
 */
class Compiler {

  /**
   * @var ExerciseConfigCompiler
   */
  private $exerciseConfigCompiler;

  /**
   * @var Loader
   */
  private $loader;

  /**
   * Compiler constructor.
   * @param ExerciseConfigCompiler $exerciseConfigCompiler
   * @param Loader $loader
   */
  public function __construct(ExerciseConfigCompiler $exerciseConfigCompiler,
      Loader $loader) {
    $this->exerciseConfigCompiler = $exerciseConfigCompiler;
    $this->loader = $loader;
  }

  /**
   * Generate job configuration from given exercise configuration.
   * @param Exercise|Assignment $exerciseAssignment
   * @param RuntimeEnvironment $runtimeEnvironment
   * @return JobConfig
   */
  public function compile($exerciseAssignment, RuntimeEnvironment $runtimeEnvironment): JobConfig {
    $exerciseConfig = $this->loader->loadExerciseConfig($exerciseAssignment->getExerciseConfig()->getParsedConfig());
    $environmentConfig = $exerciseAssignment->getExerciseEnvironmentConfigByEnvironment($runtimeEnvironment);
    $environmentConfigVariables = $this->loader->loadVariablesTable($environmentConfig->getParsedVariablesTable());

    return $this->exerciseConfigCompiler->compile($exerciseConfig, $environmentConfigVariables, $runtimeEnvironment->getId());
  }
}