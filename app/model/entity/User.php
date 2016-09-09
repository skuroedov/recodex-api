<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use forxer\Gravatar\Gravatar;

/**
 * @ORM\Entity
 */
class User implements JsonSerializable
{
    use \Kdyby\Doctrine\Entities\MagicAccessors;

    public function __construct(
      string $email,
      string $firstName,
      string $lastName,
      string $degreesBeforeName,
      string $degreesAfterName,
      Role $role,
      Instance $instance
    ) {
        $this->instance = $instance;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->degreesBeforeName = $degreesBeforeName;
        $this->degreesAfterName = $degreesAfterName;
        $this->email = $email;
        $this->avatarUrl = Gravatar::image($email, 200, "retro", "g", "png", true, false);
        $this->isVerified = FALSE;
        $this->isAllowed = TRUE;
        $this->memberships = new ArrayCollection;
        $this->exercises = new ArrayCollection;
        $this->role = $role;
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
    protected $degreesBeforeName;

    /**
     * @ORM\Column(type="string")
     */
    protected $firstName;

    /**
     * @ORM\Column(type="string")
     */
    protected $lastName;

    public function getName() {
      return trim("{$this->degreesBeforeName} {$this->firstName} {$this->lastName} {$this->degreesAfterName}");
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $degreesAfterName;

    /**
     * @ORM\Column(type="string")
     */
    protected $email;

    /**
     * @ORM\Column(type="string")
     */
    protected $avatarUrl;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isVerified;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isAllowed;

    public function isAllowed() { return $this->isAllowed; }

    /**
     * @ORM\ManyToOne(targetEntity="Instance")
     */
    protected $instance;

    public function belongsTo(Instance $instance) {
      return $this->instance->getId() === $instance->getId();
    }

    /**
     * @ORM\OneToMany(targetEntity="GroupMembership", mappedBy="user", cascade={"all"})
     */
    protected $memberships;

    protected function findMembership(Group $group) {
      $filter = Criteria::create()->where(Criteria::expr()->eq("group", $group));
      $filtered = $this->memberships->matching($filter);
      if ($filtered->isEmpty()) {
        return NULL;
      }

      if ($filtered->count() > 1) {
        // @todo: handle this situation, when this user is double member of the same group
      }

      return $filtered->first();
    }

    protected function addMembership(Group $group, string $type) {
      $membership = new GroupMembership($group, $this, $type, GroupMembership::STATUS_ACTIVE);
      $this->memberships->add($membership);
      $group->addMembership($membership);
    }

    protected function makeMemberOf(Group $group, string $type) {
      $membership = $this->findMembership($group);
      if ($membership === NULL) {
        $this->addMembership($group, $type);
      } else {
        $membership->setType($type);
        $membership->setStatus(GroupMembership::STATUS_ACTIVE);
      }
    }

    protected function removeMemberOf(Group $group, $type = NULL) {
      $membership = $this->findMembership($group);
      if (!$membership || ($type === NULL && $membership->type !== NULL)) {
        // @todo: Handle the situation, when the user is not the member of this
        // group or is not member of the particular type
        return;
      }

      return $membership;
    }

    protected function getGroups(string $type) {
      $filter = Criteria::create()->where(Criteria::expr()->eq("type", $type));
      return $this->memberships->matching($filter)->map(
        function ($membership) {
          return $membership->getGroup();
        }
      );
    }

    public function getGroupsAsStudent() {
      return $this->getGroups(GroupMembership::TYPE_STUDENT);
    }

    public function makeStudentOf(Group $group) {
      $this->makeMemberOf($group, GroupMembership::TYPE_STUDENT);
    }

    public function removeStudentFrom(Group $group) {
      return $this->removeMemberOf($group, GroupMembership::TYPE_STUDENT);
    }

    public function getGroupsAsSupervisor() {
      return $this->getGroups(GroupMembership::TYPE_SUPERVISOR);
    }

    public function makeSupervisorOf(Group $group) {
      $this->makeMemberOf($group, GroupMembership::TYPE_SUPERVISOR);
    }

    public function removeSupervisorFrom(Group $group) {
      return $this->removeMemberOf($group, GroupMembership::TYPE_SUPERVISOR);
    }

    /**
     * @ORM\OneToMany(targetEntity="Exercise", mappedBy="author")
     */
    protected $exercises;

    /**
     * @ORM\ManyToOne(targetEntity="Role")
     */
    protected $role;

    public function jsonSerialize() {
      return [
        "id" => $this->id,
        "fullName" => $this->getName(),
        "name" => [
          "degreesBeforeName" => $this->degreesBeforeName,
          "firstName" => $this->firstName,
          "lastName" => $this->lastName,
          "degreesAfterName" => $this->degreesAfterName,
        ],
        "instanceId" => $this->instance->getId(), 
        "avatarUrl" => $this->avatarUrl,
        "isVerified" => $this->isVerified,
        "role" => $this->role,
        "groups" => [
          "studentOf" => $this->getGroupsAsStudent()->map(function ($group) { return $group->getId(); })->toArray(),
          "supervisorOf" => $this->getGroupsAsSupervisor()->map(function ($group) { return $group->getId(); })->toArray()
        ]
      ];
    }
}
