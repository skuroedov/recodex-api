<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents javac compilation unit.
 */
class JavacCompilationBox extends CompilationBox
{
  /** Type key */
  public static $JAVAC_TYPE = "javac";
  public static $JAVAC_BINARY = "/usr/bin/javac";
  public static $CLASS_FILES_PORT_KEY = "class-files";
  public static $DEFAULT_NAME = "Javac Compilation";

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
        new Port((new PortMeta)->setName(self::$ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
        new Port((new PortMeta)->setName(self::$SOURCE_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$CLASS_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
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
    return self::$JAVAC_TYPE;
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
   * @param CompilationParams $params
   * @return array
   */
  public function compile(CompilationParams $params): array {
    $task = $this->compileBaseTask($params);
    $task->setCommandBinary(self::$JAVAC_BINARY);

    $args = [];
    if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
      $args = $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue();
    }
    $task->setCommandArguments(
      array_merge(
        $args,
        $this->getInputPortValue(self::$SOURCE_FILES_PORT_KEY)
          ->getPrefixedValue(ConfigParams::$EVAL_DIR)
      )
    );

    return [$task];
  }

}
