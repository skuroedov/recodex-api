<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Repository\Groups;
use App\Model\Repository\Users;
use App\Model\Repository\Instances;
use App\Model\Repository\GroupMemberships;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IExercisePermissions;
use App\Security\ACL\IGroupPermissions;

/**
 * Endpoints for group manipulation
 * @LoggedIn
 */
class GroupsPresenter extends BasePresenter {

  /**
   * @var Groups
   * @inject
   */
  public $groups;

  /**
   * @var Instances
   * @inject
   */
  public $instances;

  /**
   * @var Users
   * @inject
   */
  public $users;

  /**
   * @var GroupMemberships
   * @inject
   */
  public $groupMemberships;

  /**
   * @var IGroupPermissions
   * @inject
   */
  public $groupAcl;

  /**
   * @var IExercisePermissions
   * @inject
   */
  public $exerciseAcl;

  /**
   * @var IAssignmentPermissions
   * @inject
   */
  public $assignmentAcl;

  /**
   * Get a list of all groups
   * @GET
   */
  public function actionDefault() {
    if (!$this->groupAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $groups = $this->groups->findAll();
    $this->sendSuccessResponse($groups);
  }

  /**
   * Create a new group
   * @POST
   * @Param(type="post", name="name", validation="string:2..", description="Name of the group")
   * @Param(type="post", name="description", required=FALSE, description="Description of the group")
   * @Param(type="post", name="instanceId", validation="string:36", description="An identifier of the instance where the group should be created")
   * @Param(type="post", name="externalId", required=FALSE, description="An informative, human readable indentifier of the group")
   * @Param(type="post", name="parentGroupId", validation="string:36", required=FALSE, description="Identifier of the parent group (if none is given, a top-level group is created)")
   * @Param(type="post", name="publicStats", validation="bool", required=FALSE, description="Should students be able to see each other's results?")
   * @Param(type="post", name="isPublic", validation="bool", required=FALSE, description="Should the group be visible to all student?")
   */
  public function actionAddGroup() {
    $req = $this->getRequest();
    $instanceId = $req->getPost("instanceId");
    $parentGroupId = $req->getPost("parentGroupId");
    $user = $this->getCurrentUser();

    /** @var Instance $instance */
    $instance = $this->instances->findOrThrow($instanceId);

    $parentGroup = !$parentGroupId ? $instance->getRootGroup() : $this->groups->findOrThrow($parentGroupId);

    if (!$this->groupAcl->canAddSubgroup($parentGroup)) {
      throw new ForbiddenRequestException("You are not allowed to add subgroups to this group");
    }

    $name = $req->getPost("name");
    $externalId = $req->getPost("externalId") === NULL ? "" : $req->getPost("externalId");
    $description = $req->getPost("description") === NULL ? "" : $req->getPost("description");
    $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);

    if (!$this->groups->nameIsFree($name, $instance->getId(), $parentGroup !== NULL ? $parentGroup->getId() : NULL)) {
      throw new ForbiddenRequestException("There is already a group of this name, please choose a different one.");
    }

    $group = new Group($name, $externalId, $description, $instance, $user, $parentGroup, $publicStats, $isPublic);
    $this->groups->persist($group);
    $this->groups->flush();
    $this->sendSuccessResponse($group);
  }

  /**
   * Validate group creation data
   * @POST
   * @Param(name="name", type="post", description="Name of the group")
   * @Param(name="instanceId", type="post", description="Identifier of the instance where the group belongs")
   * @Param(name="parentGroupId", type="post", required=FALSE, description="Identifier of the parent group")
   */
  public function actionValidateAddGroupData() {
    $req = $this->getRequest();
    $name = $req->getPost("name");
    $instanceId = $req->getPost("instanceId");
    $parentGroupId = $req->getPost("parentGroupId");

    if ($parentGroupId === NULL) {
      $instance = $this->instances->get($instanceId);
      $parentGroupId = $instance->getRootGroup() !== NULL ? $instance->getRootGroup()->getId() : NULL;
    }

    $parentGroup = $this->groups->findOrThrow($parentGroupId);

    if (!$this->groupAcl->canAddSubgroup($parentGroup)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse([
      "groupNameIsFree" => $this->groups->nameIsFree($name, $instanceId, $parentGroupId)
    ]);
  }

  /**
   * Update group info
   * @POST
   * @Param(type="post", name="name", validation="string:2..", description="Name of the group")
   * @Param(type="post", name="description", required=FALSE, description="Description of the group")
   * @Param(type="post", name="externalId", required=FALSE, description="An informative, human readable indentifier of the group")
   * @Param(type="post", name="publicStats", validation="bool", required=FALSE, description="Should students be able to see each other's results?")
   * @Param(type="post", name="isPublic", validation="bool", required=FALSE, description="Should the group be visible to all student?")
   * @Param(type="post", name="threshold", validation="numericint", required=FALSE, description="A minimum percentage of points needed to pass the course")
   * @param string $id An identifier of the updated group
   * @throws ForbiddenRequestException
   */
  public function actionUpdateGroup(string $id) {
    $req = $this->getRequest();
    $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);

    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canUpdate($group)) {
      throw new ForbiddenRequestException();
    }

    $group->setExternalId($req->getPost("externalId"));
    $group->setName($req->getPost("name"));
    $group->setDescription($req->getPost("description"));
    $group->setPublicStats($publicStats);
    $group->setIsPublic($isPublic);
    $treshold = $req->getPost("threshold") !== NULL ? $req->getPost("threshold") / 100 : $group->getThreshold();
    $group->setThreshold($treshold);

    $this->groups->persist($group);
    $this->sendSuccessResponse($group);
  }

  /**
   * Delete a group
   * @DELETE
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionRemoveGroup(string $id) {
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canRemove($group)) {
      throw new ForbiddenRequestException();
    }

    if ($group->getChildGroups()->count() !== 0) {
      throw new ForbiddenRequestException("There are subgroups of group '$id'. Please remove them first.");
    } else if ($group->getInstance() !== NULL && $group->getInstance()->getRootGroup() === $group) {
      throw new ForbiddenRequestException("Group '$id' is the root group of instance '{$group->getInstance()->getId()}' and root groups cannot be deleted.");
    }

    $this->groups->remove($group);
    $this->groups->flush();

    $this->sendSuccessResponse("OK");
  }

  /**
   * Get details of a group
   * @GET
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    $group = $this->groups->findOrThrow($id);
    if (!$this->groupAcl->canViewDetail($group)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * Get public data about group.
   * @GET
   * @param string $id
   * @throws ForbiddenRequestException
   */
  public function actionPublicDetail(string $id) {
    $group = $this->groups->findOrThrow($id);
    if (!$this->groupAcl->canViewPublicDetail($group)) {
      throw new ForbiddenRequestException();
    }

    $groupData = $group->getPublicData($this->groupAcl->canViewDetail($group));
    $this->sendSuccessResponse($groupData);
  }

  /**
   * Get a list of subgroups of a group
   * @GET
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionSubgroups(string $id) {
    /** @var Group $group */
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canViewSubgroups($group)) {
      throw new ForbiddenRequestException();
    }

    $subgroups = array_values(
      array_filter(
        $group->getAllSubgroups(),
        function (Group $subgroup) {
          return $this->groupAcl->canViewDetail($subgroup);
        }
      )
    );

    $this->sendSuccessResponse($subgroups);
  }

  /**
   * Get a list of members of a group
   * @GET
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionMembers(string $id) {
    $group = $this->groups->findOrThrow($id);

    if (!($this->groupAcl->canViewStudents($group) && $this->groupAcl->canViewSupervisors($group))) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse([
      "supervisors" => $group->getSupervisors()->getValues(),
      "students" => $group->getStudents()->getValues()
    ]);
  }

  /**
   * Get a list of supervisors in a group
   * @GET
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionSupervisors(string $id) {
    $group = $this->groups->findOrThrow($id);
    if (!$this->groupAcl->canViewSupervisors($group)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($group->getSupervisors()->getValues());
  }

  /**
   * Get a list of students in a group
   * @GET
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionStudents(string $id) {
    $group = $this->groups->findOrThrow($id);
    if (!$this->groupAcl->canViewStudents($group)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($group->getStudents()->getValues());
  }

  /**
   * Get all exercise assignments for a group
   * @GET
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionAssignments(string $id) {
    /** @var Group $group */
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canViewAssignments($group)) {
      throw new ForbiddenRequestException();
    }

    $assignments = $group->getAssignments();
    $this->sendSuccessResponse(array_values(array_filter($assignments->getValues(), function (Assignment $assignment) {
      return $this->assignmentAcl->canViewDetail($assignment);
    })));
  }

  /**
   * Get all exercises for a group
   * @GET
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionExercises(string $id) {
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canViewExercises($group)) {
      throw new ForbiddenRequestException();
    }

    $exercises = array();
    while ($group !== null) {
      $groupExercises = $group->getExercises()->filter(function (Exercise $exercise) {
        return $this->exerciseAcl->canViewDetail($exercise);
      })->toArray();

      $exercises = array_merge($groupExercises, $exercises);
      $group = $group->getParentGroup();
    }

    $this->sendSuccessResponse($exercises);
  }

  /**
   * Get statistics of a group. If the user does not have the rights to view all of these, try to at least
   * return their statistics.
   * @GET
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionStats(string $id) {
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canViewStats($group)) {
      $user = $this->getCurrentUser();
      if ($this->groupAcl->canViewStudentStats($group, $user) && $group->isStudentOf($user)) {
        $this->sendSuccessResponse([$group->getStudentsStats($user)]);
      }

      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse(
      array_map(
        function ($student) use ($group) {
          return $group->getStudentsStats($student);
        },
        $group->getStudents()->getValues()
      )
    );
  }

  /**
   * Get statistics of a single student in a group
   * @GET
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the student
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function actionStudentsStats(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canViewStudentStats($group, $user)) {
      throw new ForbiddenRequestException();
    }

    if ($group->isStudentOf($user) === FALSE) {
      throw new BadRequestException("User $userId is not student of $id");
    }

    $this->sendSuccessResponse($group->getStudentsStats($user));
  }

  /**
   * Add a student to a group
   * @POST
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the student
   * @throws ForbiddenRequestException
   */
  public function actionAddStudent(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canAddStudent($group, $user)) {
      throw new ForbiddenRequestException();
    }

    // make sure that the user is not already member of the group
    if ($group->isStudentOf($user) === FALSE) {
      $user->makeStudentOf($group);
      $this->groups->flush();
    }

    // join the group
    $this->sendSuccessResponse($group);
  }

  /**
   * Remove a student from a group
   * @DELETE
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the student
   * @throws ForbiddenRequestException
   */
  public function actionRemoveStudent(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canRemoveStudent($group, $user)) {
      throw new ForbiddenRequestException();
    }

    // make sure that the user is student of the group
    if ($group->isStudentOf($user) === TRUE) {
      $membership = $user->findMembershipAsStudent($group);
      if ($membership) {
        $this->groups->remove($membership);
        $this->groups->flush();
      }
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * Add a supervisor to a group
   * @POST
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the supervisor
   * @throws ForbiddenRequestException
   */
  public function actionAddSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canAddSupervisor($group, $user)) {
      throw new ForbiddenRequestException();
    }

    // make sure that the user is not already supervisor of the group
    if ($group->isSupervisorOf($user) === FALSE) {
      if ($user->getRole() === "student") {
        $user->setRole("supervisor");
      }
      $user->makeSupervisorOf($group);
      $this->users->flush();
      $this->groups->flush();
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * Remove a supervisor from a group
   * @DELETE
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the supervisor
   * @throws ForbiddenRequestException
   */
  public function actionRemoveSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canRemoveSupervisor($group, $user)) {
      throw new ForbiddenRequestException();
    }

    // make sure that the user is really supervisor of the group
    if ($group->isSupervisorOf($user) === TRUE) {
      $membership = $user->findMembershipAsSupervisor($group); // should be always there
      $this->groupMemberships->remove($membership);
      $this->groupMemberships->flush();

      // if user is not supervisor in any other group, lets downgrade his/hers privileges
      if (empty($user->findGroupMembershipsAsSupervisor())
        && $user->getRole() === "supervisor") {
        $user->setRole("student");
        $this->users->flush();
      }
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * Get identifiers of administrators of a group
   * @GET
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionAdmin($id) {
    $group = $this->groups->findOrThrow($id);
    if (!$this->groupAcl->canViewAdmin($group)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($group->getAdminsIds());
  }

  /**
   * Make a user an administrator of a group
   * @POST
   * @Param(type="post", name="userId", description="Identifier of a user to be made administrator")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionMakeAdmin(string $id) {
    $userId = $this->getRequest()->getPost("userId");
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canSetAdmin($group)) {
      throw new ForbiddenRequestException();
    }

    // change admin of the group even if user is superadmin
    $group->makeAdmin($user);
    $this->groups->flush();
    $this->sendSuccessResponse($group);
  }

}
