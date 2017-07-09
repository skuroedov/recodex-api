<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;


/**
 * Box which represents execution of custom compiled program in ELF format.
 */
class ElfExecutionBox extends Box
{
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   */
  public static function init() {
    if (!self::$defaultInputPorts || !self::$defaultOutputPorts) {
      self::$defaultInputPorts = array(
        (new Port)->setName("binary_file")->setVariable("")
      );
      self::$defaultOutputPorts = array(
        (new Port)->setName("output_file")->setVariable("")
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

}
