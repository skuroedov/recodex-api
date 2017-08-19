<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Pipeline\Ports\UndefinedPort;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents data source, mainly files.
 */
class DataInBox extends Box
{
  /** Type key */
  public static $DATA_IN_TYPE = "data-in";
  public static $DATA_IN_PORT_KEY = "in-data";
  public static $DEFAULT_NAME = "Input Data";

  private static $initialized = false;
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   */
  public static function init() {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultInputPorts = array();
      self::$defaultOutputPorts = array(
        new UndefinedPort((new PortMeta)->setName(self::$DATA_IN_PORT_KEY)->setVariable(""))
      );
    }
  }


  /**
   * If data for this box is remote, fill this with the right variable reference.
   * @var Variable
   */
  private $remoteVariable = null;


  /**
   * DataInBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$DATA_IN_TYPE;
  }

  /**
   * Get default input ports for this box.
   * @return array
   */
  public function getDefaultInputPorts(): array {
    self::init();
    return self::$defaultInputPorts;
  }

  /**
   * Get default output ports for this box.
   * @return array
   */
  public function getDefaultOutputPorts(): array {
    self::init();
    return self::$defaultOutputPorts;
  }

  /**
   * Get default name of this box.
   * @return string
   */
  public function getDefaultName(): string {
    return self::$DEFAULT_NAME;
  }


  /**
   * Get remote variable.
   * @return Variable
   */
  public function getRemoteVariable(): Variable {
    return $this->remoteVariable;
  }

  /**
   * Set remote variable corresponding to this box.
   * @param Variable $variable
   */
  public function setRemoteVariable(Variable $variable) {
    $this->remoteVariable = $variable;
  }


  /**
   * Compile box into set of low-level tasks.
   * @return Task[]
   */
  public function compile(): array {
    if (!$this->remoteVariable) {
      // variable is local one, this means that it was either created during
      // job execution or it was brought here with solution archive
      return [];
    }

    $task = new Task();
    $task->setCommandBinary("fetch");
    $task->setCommandArguments([
      $this->remoteVariable->getValue(),
      $this->getOutputPort(self::$DATA_IN_PORT_KEY)->getVariableValue()->getValue()
    ]);
    return [$task];
  }

}
