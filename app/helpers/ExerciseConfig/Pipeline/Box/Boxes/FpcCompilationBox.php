<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents fpc compilation unit.
 */
class FpcCompilationBox extends Box
{
  /** Type key */
  public static $FPC_TYPE = "fpc";
  public static $FPC_BINARY = "/usr/bin/fpc";
  public static $SOURCE_FILE_PORT_KEY = "source-file";
  public static $BINARY_FILE_PORT_KEY = "binary-file";
  public static $DEFAULT_NAME = "FreePascal Compilation";

  private static $initialized = false;
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   */
  public static function init() {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultInputPorts = array(
        new Port((new PortMeta)->setName(self::$SOURCE_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable(""))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$BINARY_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable(""))
      );
    }
  }

  /**
   * JudgeNormalBox constructor.
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
    return self::$FPC_TYPE;
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
   * Compile box into set of low-level tasks.
   * @return Task[]
   */
  public function compile(): array {
    $task = new Task();
    $task->setType(TaskType::$INITIATION);
    $task->setFatalFailure(true);
    $task->setCommandBinary(self::$FPC_BINARY);
    $task->setCommandArguments(
      [
        $this->getInputPort(self::$SOURCE_FILE_PORT_KEY)->getVariableValue()
          ->getPrefixedValue(ConfigParams::$EVAL_DIR),
        "-o" . $this->getOutputPort(self::$BINARY_FILE_PORT_KEY)->getVariableValue()
          ->getPrefixedValue(ConfigParams::$EVAL_DIR)
      ]
    );
    $task->setSandboxConfig((new SandboxConfig)
      ->setName(LinuxSandbox::$ISOLATE)->setOutput(true));
    return [$task];
  }

}