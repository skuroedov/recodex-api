<?php

namespace App\V1Module;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use App\V1Module\Router\GetRoute;
use App\V1Module\Router\PostRoute;
use App\V1Module\Router\PreflightRoute;

class RouterFactory {

  use Nette\StaticClass;

  /**
    * @return Nette\Application\IRouter
    */
  public static function createRouter() {
    $router = new RouteList("V1");

    $prefix = "v1";
    $router[] = new Route($prefix, "Default:default");
    $router[] = new PreflightRoute($prefix, "Default:preflight");
    $router[] = new GetRoute("$prefix/login", "Login:default");

    self::createCommentsRoutes($router, "$prefix/comments");
    self::createExercisesRoutes($router, "$prefix/exercises");
    self::createExerciseAssignmentsRoutes($router, "$prefix/exercise-assignments");
    self::createGroupsRoutes($router, "$prefix/groups");
    self::createInstancesRoutes($router, "$prefix/instances");
    self::createSubmissionRoutes($router, "$prefix/submissions");
    self::createUploadedFilesRoutes($router, "$prefix/uploaded-files");
    self::createUsersRoutes($router, "$prefix/users");

    return $router;
  }

  private static function createCommentsRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix/<id>", "Comments:default");
    $router[] = new PostRoute("$prefix/<id>", "Comments:addComment");
    $router[] = new PostRoute("$prefix/<threadId>/comment/<commentId>/toggle", "Comments:togglePrivate");
  }

  private static function createExercisesRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Exercises:");
    $router[] = new GetRoute("$prefix/<id>", "Exercises:detail");
  }

  private static function createExerciseAssignmentsRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "ExerciseAssignments:");
    $router[] = new GetRoute("$prefix/<id>", "ExerciseAssignments:detail");
    $router[] = new GetRoute("$prefix/<id>/users/<userId>/submissions", "ExerciseAssignments:submissions");
    $router[] = new PostRoute("$prefix/<id>/submit", "ExerciseAssignments:submit");
  }

  private static function createGroupsRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Groups:");
    $router[] = new GetRoute("$prefix/<id>", "Groups:detail");
    $router[] = new GetRoute("$prefix/<id>/members", "Groups:members");
    $router[] = new GetRoute("$prefix/<id>/students", "Groups:students");
    $router[] = new GetRoute("$prefix/<id>/supervisors", "Groups:supervisors");
    $router[] = new GetRoute("$prefix/<id>/assignments", "Groups:assignments");
  }

  private static function createInstancesRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Instances:");
    $router[] = new GetRoute("$prefix/<id>", "Instances:detail");
    $router[] = new GetRoute("$prefix/<id>/groups", "Instances:groups");
  }

  private static function createSubmissionRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Submissions:");
    $router[] = new GetRoute("$prefix/<id>", "Submissions:evaluation");
  }

  private static function createUploadedFilesRoutes($router, $prefix) {
    $router[] = new PostRoute("$prefix", "UploadedFiles:upload");
    $router[] = new GetRoute("$prefix/<id>", "UploadedFiles:detail");
    $router[] = new GetRoute("$prefix/<id>/content", "UploadedFiles:content");
  }

  private static function createUsersRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Users:");
    $router[] = new PostRoute("$prefix", "Users:createAccount");
    $router[] = new GetRoute("$prefix/<id>", "Users:detail");
    $router[] = new GetRoute("$prefix/<id>/groups", "Users:groups");
    $router[] = new GetRoute("$prefix/<id>/exercises", "Users:exercises");
  }

}
