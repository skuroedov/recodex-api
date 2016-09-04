<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class GroupMembership implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  const STATUS_REQUESTED = "requested";
  const STATUS_ACTIVE = "active";
  const STATUS_REJECTED = "rejected";

  const TYPE_STUDENT = "student";
  const TYPE_SUPERVISOR = "supervisor";

  public function __construct(Group $group, User $user, string $type, string $status) {
    $this->group = $group;
    $this->user = $user;
    $this->setType($type);
    $this->setStatus($status);
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="User", inversedBy="memberships")
   */
  protected $user;

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="memberships")
   */
  protected $group;

  /**
   * @ORM\Column(type="string")
   */
  protected $status;

  public function setStatus(string $status) {
    if ($this->status === $status) {
      return; // nothing changes
    }

    switch ($status) {
      case self::STATUS_REQUESTED:
        $this->requestedAt = new \DateTime;
        break;
      case self::STATUS_ACTIVE:
        $this->joinedAt = new \DateTime;
        break;
      case self::STATUS_REJECTED:
        $this->rejectedAt = new \DateTime;
        break;
      default:
        throw new InvalidMembershipException("Unsupported membership status '$status'");
    }
    $this->status = $status;
  }

  /**
   * @ORM\Column(type="string")
   */
  protected $type;

  public function setType(string $type) {
    if ($this->type === $type) {
      return; // nothing changes
    }

    switch ($type) {
      case self::TYPE_STUDENT:
        $this->studentSince = new \DateTime;
        break;
      case self::TYPE_SUPERVISOR:
        $this->supervisorSince = new \DateTime;
        break;
      default:
        throw new InvalidMembershipException("Unsuported membership type '$type'");
    }
    $this->type = $type;
  }

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $requestedAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $joinedAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $rejectedAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $studentSince;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $supervisorSince;

  public function jsonSerialize() {
    $instance = $this->getInstance();
    return [
      "id" => $this->id,
      "userId" => $this->user->getId(),
      "groupId" => $this->group->getId(),
      "status" => $this->status,
      "requestedAt" => $this->requestedAt,
      "joinedAt" => $this->joinedAt,
      "rejectedAt" => $this->requestedAt,
      "studentSince" => $this->studentSince,
      "supervisorSince" => $this->supervisorSince,
    ];
  }

}