<?php

namespace App\V1Module\Presenters;

use App\Helpers\MonitorConfig;
use App\Model\Repository\Exercises;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Model\Repository\ReferenceSolutionEvaluations;
use App\Model\Repository\UploadedFiles;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionEvaluation;

use App\Helpers\JobConfig;
use App\Helpers\SubmissionHelper;

/**
 * Endpoints for manipulation of reference solutions of exercises
 */
class ReferenceExerciseSolutionsPresenter extends BasePresenter {

  /**
   * @var Exercises
   * @inject
   */
  public $exercises;

  /**
   * @var UploadedFiles
   * @inject
   */
  public $files;

  /**
   * @var ReferenceExerciseSolutions
   * @inject
   */
  public $referenceSolutions;

  /**
   * @var ReferenceSolutionEvaluations
   * @inject
   */
  public $referenceEvaluations;

  /**
   * @var SubmissionHelper
   * @inject
   */
  public $submissionHelper;

  /**
   * @var MonitorConfig
   * @inject
   */
  public $monitorConfig;

  /**
   * Get reference solutions for an exercise
   * @GET
   */
  public function actionExercise($id) {
    // @todo check that this user can access this information
    $exercise = $this->findExerciseOrThrow($id);
    $this->sendSuccessResponse($exercise->referenceSolutions->getValues());
  }

  public function actionCreateReferenceSolution() {
    $exercise = $this->exercises->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    // @todo validate user's access

    $req = $this->getHttpRequest();
    $files = $this->files->findAllById($req->getPost("files"));
    $note = $req->getPost("note");
    $solution = new ReferenceExerciseSolution($exercise, $user, $note, $files);
    $this->referenceSolutions->persist($solution);

    // evaluate the solution right now
    // hwGroup post param is preserved from current endpoint call
    $this->actionEvaluate($exercise->getId(), $solution->getId());
  }

  /**
   * Evaluate reference solutions to an exercise for a hardware group
   * @POST
   * @Param(type="post", name="hwGroup", description="Identififer of a hardware group")
   */
  public function actionEvaluate(string $exerciseId, string $id) {
    $referenceSolution = $this->referenceSolutions->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if ($referenceSolution->getExercise()->getId() !== $exerciseId) {
      throw new SubmissionFailedException("The reference solution '$id' does not belong to exercise '$exerciseId'");
    }

    // @todo validate that user can do this action

    // create the entity and generate the ID
    $hwGroup = $this->getHttpRequest()->getPost("hwGroup");
    $evaluation = new ReferenceSolutionEvaluation($referenceSolution, $hwGroup);
    $this->referenceEvaluations->persist($evaluation);

    // configure the job and start evaluation
    $jobConfig = JobConfig\Storage::getJobConfig($referenceSolution->getReferenceSolution()->getSolution()->getSolutionRuntimeConfig()->getJobConfigFilePath());
    $jobConfig->setJobId(ReferenceSolutionEvaluation::JOB_TYPE, $evaluation->getId());
    $files = $referenceSolution->getFiles()->getValues();
    $resultsUrl = $this->submissionHelper->initiateEvaluation($jobConfig, $files, $hwGroup);

    if($resultsUrl !== NULL) {
      $evaluation->setResultsUrl($resultsUrl);
      $this->referenceEvaluations->flush();
      $this->sendSuccessResponse([
        "evaluation" => $evaluation,
        "webSocketChannel" => [
          "id" => $jobConfig->getJobId(),
          "monitorUrl" => $this->monitorConfig->getAddress(),
          "expectedTasksCount" => $jobConfig->getTasksCount()
        ],
      ]);
    } else {
      throw new SubmissionFailedException;
    }
  }
}
