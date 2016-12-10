<?php

namespace App\Helpers\JobConfig\Tasks;

use App\Helpers\JobConfig\SandboxConfig;
use Symfony\Component\Yaml\Yaml;


/**
 * Abstract base class for internal and external tasks which stores all shared info.
 */
class Task {

  /** Config key which represents task identification */
  const TASK_ID_KEY = "task-id";
  /** Key representing priority of task */
  const PRIORITY_KEY = "priority";
  /** Config key which represents if task has fatal failure bit set */
  const FATAL_FAILURE_KEY = "fatal-failure";
  /** Key representing dependencies collection */
  const DEPENDENCIES = "dependencies";
  /** Key representing command map */
  const CMD_KEY = "cmd";
  /** Command binary key */
  const CMD_BIN_KEY = "bin";
  /** Command arguments key */
  const CMD_ARGS_KEY = "args";
  /** Test identification key */
  const TEST_ID_KEY = "test-id";
  /** Task type key */
  const TYPE_KEY = "type";
  /** Sandbox config key */
  const SANDBOX_KEY = "sandbox";

  /** @var string Task ID */
  private $id = "";
  /** @var integer Priority of task, higher number means higher priority */
  private $priority = 0;
  /** @var boolean If true execution of whole job will be stopped on failure */
  private $fatalFailure = false;
  /** @var array Collection of dependencies */
  private $dependencies = [];
  /** @var string Binary which will be executed in this task */
  private $commandBinary = "";
  /** @var array Arguments for execution command */
  private $commandArguments = [];
  /** @var string Type of the task */
  private $type = NULL;
  /** @var string ID of the test to which this task corresponds */
  private $testId = NULL;
  /** @var SandboxConfig */
  private $sandboxConfig = NULL;
  /** @var array Additional data */
  private $data = [];

  /**
   * ID of the task itself.
   * @return string
   */
  public function getId(): string {
    return $this->id;
  }

  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * Priority of this task.
   * @return int
   */
  public function getPriority(): int {
    return $this->priority;
  }

  public function setPriority($priority) {
    $this->priority = $priority;
    return $this;
  }

  /**
   * Fatal failure bit.
   * @return bool
   */
  public function getFatalFailure(): bool {
    return $this->fatalFailure;
  }

  public function setFatalFailure($fatalFailure) {
    $this->fatalFailure = $fatalFailure;
    return $this;
  }

  /**
   * Gets array of dependencies.
   * @return array
   */
  public function getDependencies(): array {
    return $this->dependencies;
  }

  public function setDependencies(array $dependencies) {
    $this->dependencies = $dependencies;
    return $this;
  }

  /**
   * Returns command binary.
   * @return string
   */
  public function getCommandBinary(): string {
    return $this->commandBinary;
  }

  public function setCommandBinary($binary) {
    $this->commandBinary = $binary;
    return $this;
  }

  /**
   * Gets command arguments.
   * @return array
   */
  public function getCommandArguments(): array {
    return $this->commandArguments;
  }

  public function setCommandArguments(array $args) {
    $this->commandArguments = $args;
    return $this;
  }

  /**
   * Type of the task
   * @return string|NULL
   */
  public function getType() {
    return $this->type;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  /**
   * ID of the test this task belongs to (if any).
   * @return string|NULL
   */
  public function getTestId() {
    return $this->testId;
  }

  public function setTestId($testId) {
    $this->testId = $testId;
    return $this;
  }

  public function getSandboxConfig() {
    return $this->sandboxConfig;
  }

  public function setSandboxConfig(SandboxConfig $config) {
    $this->sandboxConfig = $config;
    return $this;
  }

  public function getAdditionalData() {
    return $this->data;
  }

  public function setAdditionalData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Check if task has type initiation or not.
   * @return bool
   */
  public function isInitiationTask(): bool {
    return $this->type === InitiationTaskType::TASK_TYPE;
  }

  /**
   * Check if task is of type execution or not.
   * @return bool
   */
  public function isExecutionTask(): bool {
    return $this->type === ExecutionTaskType::TASK_TYPE;
  }

  /**
   * Checks if task has type evaluation or not.
   * @return bool
   */
  public function isEvaluationTask(): bool {
    return $this->type === EvaluationTaskType::TASK_TYPE;
  }

  public function isSandboxedTask(): bool {
    return !($this->sandboxConfig === NULL);
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::TASK_ID_KEY] = $this->id;
    $data[self::PRIORITY_KEY] = $this->priority;
    $data[self::FATAL_FAILURE_KEY] = $this->fatalFailure;
    $data[self::CMD_KEY] = [];
    $data[self::CMD_KEY][self::CMD_BIN_KEY] = $this->commandBinary;

    if (!empty($this->dependencies)) { $data[self::DEPENDENCIES] = $this->dependencies; }
    if (!empty($this->commandArguments)) { $data[self::CMD_KEY][self::CMD_ARGS_KEY] = $this->commandArguments; }
    if ($this->testId) { $data[self::TEST_ID_KEY] = $this->testId; }
    if ($this->type) { $data[self::TYPE_KEY] = $this->type; }
    if ($this->sandboxConfig) { $data[self::SANDBOX_KEY] = $this->sandboxConfig->toArray(); }
    return $data;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }
}