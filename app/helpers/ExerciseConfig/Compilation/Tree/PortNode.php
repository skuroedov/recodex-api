<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\VariablesTable;


/**
 * Node representing Box in the compilation of exercise. It can hold additional
 * information regarding box which does not have to be stored there, this can
 * save memory during loading of pipelines and not compiling them.
 * This node contains children and parents indexed by corresponding port.
 * @note Structure used in exercise compilation.
 */
class PortNode {

  /**
   * Box connected to this node.
   * @var Box
   */
  private $box;

  /**
   * Pipeline variables from exercise configuration which will be used during compilation.
   * @note Needs to be first set before usage.
   * @var VariablesTable
   */
  protected $exerciseConfigVariables;

  /**
   * Variables from environment configuration which will be used during compilation.
   * @note Needs to be first set before usage.
   * @var VariablesTable
   */
  protected $environmentConfigVariables;

  /**
   * Variables from pipeline to which this box belong to, which will be used during compilation.
   * @note Needs to be first set before usage.
   * @var VariablesTable
   */
  protected $pipelineVariables;


  /**
   * Nodes which identify themselves as parent of this node, ndexed by port
   * name.
   * @var PortNode[]
   */
  private $parents = array();

  /**
   * Children nodes of this one.
   * @var PortNode[]
   */
  private $children = array();

  /**
   * Children nodes of this one, indexed by port name.
   * @var array
   */
  private $childrenByPort = array();

  /**
   * Is this node contained in created tree.
   * Flag regarding tree construction.
   * @var bool
   */
  private $isInTree = false;

  /**
   * Tree was visited during topological sorting.
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $visited = false;

  /**
   * Tree was finished and does not have to be processed again.
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $finished = false;

  /**
   * Identification of test to which this box belongs to.
   * @var string
   */
  private $testId = null;

  /**
   * Identification of pipeline to which this box belongs to.
   * @var string
   */
  private $pipelineId = null;


  /**
   * Node constructor.
   * @param Box $box
   * @param string $pipelineId
   * @param string|null $testId
   */
  public function __construct(Box $box, string $pipelineId = null, string $testId = null) {
    $this->box = $box;
    $this->pipelineId = $pipelineId;
    $this->testId = $testId;
  }

  /**
   * Get box associated with this node.
   * @return Box
   */
  public function getBox(): Box {
    return $this->box;
  }

  /**
   * Get pipeline variables from exercise configuration.
   * @note Needs to be first set before usage.
   * @return VariablesTable|null
   */
  public function getExerciseConfigVariables(): ?VariablesTable {
    return $this->exerciseConfigVariables;
  }

  /**
   * Set pipeline variables from exercise configuration.
   * @param VariablesTable $variablesTable
   * @return PortNode
   */
  public function setExerciseConfigVariables(VariablesTable $variablesTable): PortNode {
    $this->exerciseConfigVariables = $variablesTable;
    return $this;
  }

  /**
   * Get variables from environment configuration.
   * @note Needs to be first set before usage.
   * @return VariablesTable|null
   */
  public function getEnvironmentConfigVariables(): ?VariablesTable {
    return $this->environmentConfigVariables;
  }

  /**
   * Set variables from environment configuration.
   * @param VariablesTable $variablesTable
   * @return PortNode
   */
  public function setEnvironmentConfigVariables(VariablesTable $variablesTable): PortNode {
    $this->environmentConfigVariables = $variablesTable;
    return $this;
  }

  /**
   * Get variables from pipeline.
   * @note Needs to be first set before usage.
   * @return VariablesTable|null
   */
  public function getPipelineVariables(): ?VariablesTable {
    return $this->pipelineVariables;
  }

  /**
   * Set variables from pipeline.
   * @param VariablesTable $variablesTable
   * @return PortNode
   */
  public function setPipelineVariables(VariablesTable $variablesTable): PortNode {
    $this->pipelineVariables = $variablesTable;
    return $this;
  }


  /**
   * Get parents of this node.
   * @return PortNode[]
   */
  public function getParents(): array {
    return $this->parents;
  }

  /**
   * Find port of given parent.
   * @param PortNode $node
   * @return null|string
   */
  public function findParentPort(PortNode $node): ?string {
    $portName = array_search($node, $this->parents, true);
    return $portName ? $portName : null;
  }

  /**
   * Clear parents of this node.
   */
  public function clearParents() {
    $this->parents = array();
  }

  /**
   * Add parent of this node.
   * @param string $port
   * @param PortNode $parent
   */
  public function addParent(string $port, PortNode $parent) {
    $this->parents[$port] = $parent;
  }

  /**
   * Remove given parent from this node.
   * @param PortNode $parent
   */
  public function removeParent(PortNode $parent) {
    if(($key = array_search($parent, $this->parents)) !== false){
      unset($this->parents[$key]);
    }
  }

  /**
   * Get children of this node.
   * @return PortNode[]
   */
  public function getChildren(): array {
    return $this->children;
  }

  /**
   * Get children of this node indexed by port.
   * @return array
   */
  public function getChildrenByPort(): array {
    return $this->childrenByPort;
  }

  /**
   * Find port of given child.
   * @param PortNode $node
   * @return null|string
   */
  public function findChildPort(PortNode $node): ?string {
    foreach ($this->childrenByPort as $portName => $children) {
      if (array_search($node, $children, true) !== FALSE) {
        return $portName;
      }
    }
    return null;
  }

  /**
   * Clear children array.
   */
  public function clearChildren() {
    $this->children = array();
    $this->childrenByPort = array();
  }

  /**
   * Add child to this node with specified node.
   * @param string $port
   * @param PortNode $child
   */
  public function addChild(string $port, PortNode $child) {
    $this->children[] = $child;
    if (!array_key_exists($port, $this->childrenByPort)) {
      $this->childrenByPort[$port] = [];
    }
    $this->childrenByPort[$port][] = $child;
  }

  /**
   * Remove given child from children array.
   * @param PortNode $child
   */
  public function removeChild(PortNode $child) {
    if(($key = array_search($child, $this->children)) !== false){
      unset($this->children[$key]);
    }

    foreach ($this->childrenByPort as $port => $children) {
      if(($key = array_search($child, $children)) !== false){
        unset($this->childrenByPort[$port][$key]);
      }
    }
  }

  /**
   * Is this box in tree.
   * @return bool
   */
  public function isInTree(): bool {
    return $this->isInTree;
  }

  /**
   * Set is in tree flag.
   * @param bool $flag
   */
  public function setInTree(bool $flag) {
    $this->isInTree = $flag;
  }

  /**
   * Was this box visited in topological sort.
   * @return bool
   */
  public function isVisited(): bool {
    return $this->visited;
  }

  /**
   * Set visited flag.
   * @param bool $flag
   */
  public function setVisited(bool $flag) {
    $this->visited = $flag;
  }

  /**
   * Was this box finished in topological sort.
   * @return bool
   */
  public function isFinished(): bool {
    return $this->finished;
  }

  /**
   * Set finished flag.
   * @param bool $flag
   */
  public function setFinished(bool $flag) {
    $this->finished = $flag;
  }

  /**
   * Test identification for corresponding box.
   * @return string|null
   */
  public function getTestId(): ?string {
    return $this->testId;
  }

  /**
   * Pipeline identification for corresponding box.
   * @return string|null
   */
  public function getPipelineId(): ?string {
    return $this->pipelineId;
  }

}