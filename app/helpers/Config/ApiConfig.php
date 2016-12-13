<?php
namespace App\Helpers;
use Nette;
use Nette\Utils\Arrays;

/**
 * Holder of configuration API item, which should describe this server.
 */
class ApiConfig
{
  use Nette\SmartObject;

  /**
   * Address of API server as visible from outside world.
   * @var string
   */
  protected $address;

  /**
   * Some basic name which should be used for identification of service
   * @var string
   */
  protected $name;

  /**
   * Basic description of this server.
   * @var string
   */
  protected $description;

  /**
   * Constructs configuration object from given array.
   * @param array $config
   */
  public function __construct(array $config) {
    $this->address = Arrays::get($config, ["address"]);
    $this->name = Arrays::get($config, ["name"]);
    $this->description = Arrays::get($config, ["description"]);
  }

  public function getAddress() {
      return $this->address;
  }

  public function getName() {
      return $this->name;
  }

  public function getDescription() {
      return $this->description;
  }
}
