<?php
namespace App\Security\Policies;

use App\Model\Entity\Assignment;
use App\Security\Identity;

class AssignmentPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return Assignment::class;
  }

  public function isPublic(Identity $identity, Assignment $assignment) {
    return $assignment->isPublic();
  }

  public function isAssignee(Identity $identity, Assignment $assignment) {
    $user = $identity->getUserData();

    if ($user === NULL) {
      return FALSE;
    }

    return $assignment->getGroup()->isMemberOf($user);
  }

  public function isSupervisor(Identity $identity, Assignment $assignment) {
    $group = $assignment->getGroup();
    $user = $identity->getUserData();

    if ($user === NULL) {
      return FALSE;
    }

    return $group->isSupervisorOf($user) || $group->isAdminOf($user);
  }

  public function isNotExpired(Identity $identity, Assignment $assignment) {
    $group = $assignment->getGroup();
    $user = $identity->getUserData();

    if ($user === NULL) {
      return FALSE;
    }

    return $group->getInstance()->hasValidLicence() && !$assignment->isAfterDeadline();
  }
}