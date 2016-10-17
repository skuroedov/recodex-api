<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;


/**
 * Sandbox configuration holder contains mainly name of used sandbox and limits
 * for specific hardware groups. Limits can be also removed or set
 * to another ones.
 */
class SandboxConfig {
  /** Sandbox name key */
  const NAME_KEY = "name";
  /** Stdin config key */
  const STDIN_KEY = "stdin";
  /** Stdout config key */
  const STDOUT_KEY = "stdout";
  /** Stderr config key */
  const STDERR_KEY = "stderr";
  /** Limits collection key */
  const LIMITS_KEY = "limits";

  /** @var string Sandbox name */
  private $name;
  /** @var string Standard input redirection file */
  private $stdin = NULL;
  /** @var string Standard output redirection file */
  private $stdout = NULL;
  /** @var string Standard error redirection file */
  private $stderr = NULL;
  /** @var array List of limits */
  private $limits = [];
  /** @var array Additional data */
  private $data;

  /**
   * Creates sandbox config from given structured data.
   * @param array $data structured config
   * @throws JobConfigLoadingException In case of parsing error
   */
  public function __construct(array $data) {
    if (!isset($data[self::NAME_KEY])) {
      throw new JobConfigLoadingException("Sandbox section does not contain required field '" . self::NAME_KEY . "'");
    }
    $this->name = $data[self::NAME_KEY];
    unset($data[self::NAME_KEY]);

    if (!isset($data[self::LIMITS_KEY]) || !is_array($data[self::LIMITS_KEY])) {
      throw new JobConfigLoadingException("Sandbox section does not contain proper field '" . self::LIMITS_KEY . "'");
    }

    if (isset($data[self::STDIN_KEY])) {
      $this->stdin = $data[self::STDIN_KEY];
      unset($data[self::STDIN_KEY]);
    }

    if (isset($data[self::STDOUT_KEY])) {
      $this->stdout = $data[self::STDOUT_KEY];
      unset($data[self::STDOUT_KEY]);
    }

    if (isset($data[self::STDERR_KEY])) {
      $this->stderr = $data[self::STDERR_KEY];
      unset($data[self::STDERR_KEY]);
    }

    // *** CONSTRUCT ALL LIMITS

    foreach ($data[self::LIMITS_KEY] as $lim) {
      $limTyped = new Limits($lim);
      $this->limits[$limTyped->getId()] = $limTyped;
    }

    // *** LOAD ALL REMAINING INFO
    $this->data = $data;
  }

  /**
   * Get sandbox name.
   * @return string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Return standard input redirection file.
   * @return string|NULL
   */
  public function getStdin() {
    return $this->stdin;
  }

  /**
   * Return standard output redirection file.
   * @return string|NULL
   */
  public function getStdout() {
    return $this->stdout;
  }

  /**
   * Get standard error redirection file.
   * @return string|NULL
   */
  public function getStderr() {
    return $this->stderr;
  }

  /**
   * Gets limits as array.
   * @return Limits[]
   */
  public function getLimitsArray(): array {
    return $this->limits;
  }

  /**
   * Does the task config have limits for given hardware group?
   * @return bool
   */
  public function hasLimits(string $hardwareGroupId): bool {
    return isset($this->limits[$hardwareGroupId]);
  }

  /**
   * Get the configured limits for a specific hardware group.
   * @param string $hardwareGroupId Hardware group ID
   * @return Limits Limits for the specified hardware group
   * @throws JobConfigLoadingException
   */
  public function getLimits(string $hardwareGroupId): Limits {
    if (!isset($this->limits[$hardwareGroupId])) {
      throw new JobConfigLoadingException("Sandbox config does not define limits for hardware group '$hardwareGroupId'");
    }

    return $this->limits[$hardwareGroupId];
  }

  /**
   * Set limits for a specific hardware group
   * @param string $hardwareGroupId   Hardware group ID
   * @param Limits $limits            The limits
   * @return void
   */
  public function setLimits(Limits $limits) {
    $this->limits[$limits->getId()] = $limits;
  }

  /**
   * Set limits of a given HW group to undefined, which basically means
   * that there are no more limits anymore.
   * @param string $hardwareGroupId   Hardware group ID
   * @return void
   */
  public function removeLimits(string $hardwareGroupId) {
    $this->setLimits(new UndefinedLimits($hardwareGroupId));
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::NAME_KEY] = $this->name;
    if (!empty($this->stdin)) { $data[self::STDIN_KEY] = $this->stdin; }
    if (!empty($this->stdout)) { $data[self::STDOUT_KEY] = $this->stdout; }
    if (!empty($this->stderr)) { $data[self::STDERR_KEY] = $this->stderr; }

    $data[self::LIMITS_KEY] = [];
    foreach ($this->limits as $limit) {
      $data[self::LIMITS_KEY][] = $limit->toArray();
    }

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