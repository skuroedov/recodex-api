<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidStateException;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use DateTime;
use Kdyby\Doctrine\Entities\MagicAccessors;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method DateTime getDeletedAt()
 */
class Assignment implements JsonSerializable
{
  use MagicAccessors;

  private function __construct(
    string $name,
    DateTime $firstDeadline,
    int $maxPointsBeforeFirstDeadline,
    Exercise $exercise,
    Group $group,
    bool $isPublic,
    int $submissionsCountLimit,
    bool $allowSecondDeadline,
    DateTime $secondDeadline = null,
    int $maxPointsBeforeSecondDeadline = 0,
    bool $canViewLimitRatios = FALSE,
    bool $isBonus = FALSE,
    $pointsPercentualThreshold = NULL
  ) {
    if ($secondDeadline == null) {
      $secondDeadline = $firstDeadline;
    }

    $this->name = $name;
    $this->exercise = $exercise;
    $this->group = $group;
    $this->firstDeadline = $firstDeadline;
    $this->maxPointsBeforeFirstDeadline = $maxPointsBeforeFirstDeadline;
    $this->allowSecondDeadline = $allowSecondDeadline;
    $this->secondDeadline = $secondDeadline;
    $this->maxPointsBeforeSecondDeadline = $maxPointsBeforeSecondDeadline;
    $this->submissions = new ArrayCollection;
    $this->isPublic = $isPublic;
    $this->solutionRuntimeConfigs = $exercise->getSolutionRuntimeConfigs();
    $this->submissionsCountLimit = $submissionsCountLimit;
    $this->scoreConfig = "";
    $this->localizedAssignments = $exercise->getLocalizedAssignments();
    $this->canViewLimitRatios = $canViewLimitRatios;
    $this->version = 1;
    $this->isBonus = $isBonus;
    $this->pointsPercentualThreshold = $pointsPercentualThreshold;
  }

  public static function assignToGroup(Exercise $exercise, Group $group, $isPublic = FALSE) {
    if ($exercise->getLocalizedAssignments()->count() == 0) {
      throw new InvalidStateException("There are no localized descriptions of exercise");
    }

    if ($exercise->getSolutionRuntimeConfigs()->count() == 0) {
      throw new InvalidStateException("There are no runtime configurations in exercise");
    }

    $assignment = new self(
      $exercise->getName(),
      new DateTime,
      0,
      $exercise,
      $group,
      $isPublic,
      50,
      FALSE
    );

    $group->addAssignment($assignment);

    return $assignment;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="integer")
   */
  protected $version;

  /**
   * Increment version number.
   */
  public function incrementVersion() {
    $this->version++;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPublic;

  public function isPublic() {
    return $this->isPublic;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isBonus;

  /**
   * @ORM\Column(type="float", nullable=true)
   */
  protected $pointsPercentualThreshold;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $deletedAt;

  /**
   * @ORM\Column(type="smallint")
   */
  protected $submissionsCountLimit;

  /**
   * @ORM\ManyToMany(targetEntity="SolutionRuntimeConfig")
   */
  protected $solutionRuntimeConfigs;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $scoreCalculator;

  /**
   * @ORM\Column(type="text", nullable=true)
   */
  protected $scoreConfig;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $firstDeadline;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $allowSecondDeadline;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $secondDeadline;

  public function isAfterDeadline() {
    if ($this->allowSecondDeadline) {
      return $this->secondDeadline < new \DateTime;
    } else {
      return $this->firstDeadline < new \DateTime;
    }
  }

  /**
   * @ORM\Column(type="smallint")
   */
  protected $maxPointsBeforeFirstDeadline;

  /**
   * @ORM\Column(type="smallint")
   */
  protected $maxPointsBeforeSecondDeadline;

  public function getMaxPoints(DateTime $time = NULL) {
    if ($time === NULL || $time < $this->firstDeadline) {
      return $this->maxPointsBeforeFirstDeadline;
    } else if ($this->allowSecondDeadline && $time < $this->secondDeadline) {
      return $this->maxPointsBeforeSecondDeadline;
    } else {
      return 0;
    }
  }


  /**
   * Assignment can be marked as bonus, then we do not want to add its points
   * to overall maximum points of group. This function will return 0 if
   * assignment is marked as bonus one, otherwise it will return result of
   * $this->getMaxPoints() function.
   * @return int
   */
  public function getGroupPoints(): int {
    if ($this->isBonus) {
      return 0;
    } else {
      return $this->getMaxPoints();
    }
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $canViewLimitRatios;

  /**
   * @ORM\ManyToMany(targetEntity="LocalizedAssignment", inversedBy="assignments")
   */
  protected $localizedAssignments;

  public function addLocalizedAssignment(LocalizedAssignment $assignment) {
    $this->localizedAssignments->add($assignment);
  }

  public function getLocalizedAssignmentByLocale($locale) {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
    $first = $this->getLocalizedAssignments()->matching($criteria)->first();
    return $first === FALSE ? NULL : $first;
  }

  /**
   * @ORM\ManyToOne(targetEntity="Exercise")
   */
  protected $exercise;

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="assignments")
   */
  protected $group;

  public function canReceiveSubmissions(User $user = NULL) {
    return $this->isPublic === TRUE &&
      $this->group->hasValidLicence() &&
      !$this->isAfterDeadline() &&
      ($user !== NULL && !$this->hasReachedSubmissionsCountLimit($user));
  }

  /**
   * Can a specific user access this assignment as student?
   */
  public function canAccessAsStudent(User $user) {
    return $this->isPublic === TRUE && $this->group->isStudentOf($user);
  }

  /**
   * Can a specific user access this assignment as supervisor?
   */
  public function canAccessAsSupervisor(User $user) {
    return $this->group->isAdminOf($user) || $this->group->isSupervisorOf($user);
  }

  /**
   * @ORM\OneToMany(targetEntity="Submission", mappedBy="assignment")
   * @ORM\OrderBy({ "submittedAt" = "DESC" })
   */
  protected $submissions;

  public function getValidSubmissions(User $user) {
    $fromThatUser = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user))
      ->andWhere(Criteria::expr()->neq("resultsUrl", NULL));
    $validSubmissions = function ($submission) {
      if (!$submission->hasEvaluation()) {
        // the submission is not evaluated yet - suppose it will be evaluated in the future (or marked as invalid)
        // -> otherwise the user would be able to submit many solutions before they are evaluated
        return TRUE;
      }

      // keep only solutions, which are marked as valid (both manual and automatic way)
      $evaluation = $submission->getEvaluation();
      return ($evaluation->isValid() === TRUE && $evaluation->getEvaluationFailed() === FALSE);
    };

    return $this->submissions
      ->matching($fromThatUser)
      ->filter($validSubmissions);
  }

  public function hasReachedSubmissionsCountLimit(User $user) {
    return count($this->getValidSubmissions($user)) >= $this->submissionsCountLimit;
  }

  public function getLastSolution(User $user) {
    $usersSolutions = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user));
    return $this->submissions->matching($usersSolutions)->first();
  }

  public function getBestSolution(User $user) {
    $usersSolutions = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user))
      ->andWhere(Criteria::expr()->neq("evaluation", NULL));

    return array_reduce(
      $this->submissions->matching($usersSolutions)->getValues(),
      function ($best, $submission) {
        if ($best === NULL) {
          return $submission;
        }

        return $submission->hasEvaluation() === FALSE || $best->getTotalPoints() > $submission->getTotalPoints()
          ? $best
          : $submission;
      },
      NULL
    );
  }

  /**
   * Get runtime configuration based on environment identification.
   * @param RuntimeEnvironment $environment
   * @return SolutionRuntimeConfig|NULL
   */
  public function getRuntimeConfigByEnvironment(RuntimeEnvironment $environment) {
    $first = $this->getSolutionRuntimeConfigs()->filter(
      function (SolutionRuntimeConfig $runtimeConfig) use ($environment) {
        return $runtimeConfig->getRuntimeEnvironment()->getId() === $environment->getId();
    })->first();
    return $first === FALSE ? NULL : $first;
  }

  public function getSolutionRuntimeConfigsIds() {
    return $this->solutionRuntimeConfigs->map(function($config) { return $config->getId(); })->getValues();
  }

  public function getRuntimeEnvironmentsIds() {
    return $this->solutionRuntimeConfigs->map(
      function(SolutionRuntimeConfig $config) {
        return $config->getRuntimeEnvironment()->getId();
      })->getValues();
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "version" => $this->version,
      "isPublic" => $this->isPublic,
      "localizedAssignments" => $this->localizedAssignments->getValues(),
      "groupId" => $this->group->getId(),
      "firstDeadline" => $this->firstDeadline->getTimestamp(),
      "secondDeadline" => $this->secondDeadline->getTimestamp(),
      "allowSecondDeadline" => $this->allowSecondDeadline,
      "maxPointsBeforeFirstDeadline" => $this->maxPointsBeforeFirstDeadline,
      "maxPointsBeforeSecondDeadline" => $this->maxPointsBeforeSecondDeadline,
      "scoreConfig" => $this->scoreConfig,
      "submissionsCountLimit" => $this->submissionsCountLimit,
      "canReceiveSubmissions" => FALSE, // the app must perform a special request to get the valid information
      "runtimeEnvironmentsIds" => $this->getRuntimeEnvironmentsIds(),
      "canViewLimitRatios" => $this->canViewLimitRatios,
      "isBonus" => $this->isBonus,
      "pointsPercentualThreshold" => $this->pointsPercentualThreshold
    ];
  }
}
