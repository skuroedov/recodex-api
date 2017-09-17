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
 * Box which represents execution of assembly in C# which will be executed with
 * mono.
 */
class MonoExecutionBox extends Box
{
  /** Type key */
  public static $MONO_EXEC_TYPE = "mono-exec";
  public static $MONO_BINARY = "/usr/bin/mono";
  public static $ASSEMBLY_FILE_PORT_KEY = "assembly";
  public static $BINARY_ARGS_PORT_KEY = "args";
  public static $INPUT_FILES_PORT_KEY = "input-files";
  public static $STDIN_FILE_PORT_KEY = "stdin";
  public static $OUTPUT_FILE_PORT_KEY = "output-file";
  public static $STDOUT_FILE_PORT_KEY = "stdout";
  public static $DEFAULT_NAME = "Mono Execution";

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
        new Port((new PortMeta)->setName(self::$BINARY_ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)->setVariable("")),
        new Port((new PortMeta)->setName(self::$STDIN_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable("")),
        new Port((new PortMeta)->setName(self::$INPUT_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)->setVariable("")),
        new Port((new PortMeta)->setName(self::$ASSEMBLY_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable(""))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$STDOUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable("")),
        new Port((new PortMeta)->setName(self::$OUTPUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable(""))
      );
    }
  }

  /**
   * ElfExecutionBox constructor.
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
    return self::$MONO_EXEC_TYPE;
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
    $task->setType(TaskType::$EXECUTION);
    $task->setCommandBinary(self::$MONO_BINARY);

    $args = [$this->getInputPort(self::$ASSEMBLY_FILE_PORT_KEY)->getVariableValue()->getPrefixedValue(ConfigParams::$EVAL_DIR)];
    if ($this->getInputPort(self::$BINARY_ARGS_PORT_KEY)->getVariableValue() !== null) {
      $args = array_merge($args, $this->getInputPort(self::$BINARY_ARGS_PORT_KEY)->getVariableValue()->getValue());
    }
    $task->setCommandArguments($args);

    $sandbox = (new SandboxConfig)->setName(LinuxSandbox::$ISOLATE);
    if ($this->getInputPort(self::$STDIN_FILE_PORT_KEY)->getVariableValue() !== null) {
      $sandbox->setStdin($this->getInputPort(self::$STDIN_FILE_PORT_KEY)->getVariableValue()->getPrefixedValue(ConfigParams::$EVAL_DIR));
    }
    if ($this->getOutputPort(self::$STDOUT_FILE_PORT_KEY)->getVariableValue() !== null) {
      $sandbox->setStdout($this->getOutputPort(self::$STDOUT_FILE_PORT_KEY)->getVariableValue()->getPrefixedValue(ConfigParams::$EVAL_DIR));
    }
    $task->setSandboxConfig($sandbox);

    return [$task];
  }

}