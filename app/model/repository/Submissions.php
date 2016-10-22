<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Submission;
use App\Model\Entity\Assignment;

class Submissions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Submission::CLASS);
  }

  public function findSubmissions(Assignment $assignment, string $userId) {
    return $this->findBy([
      "user" => $userId,
      "exerciseAssignment" => $assignment
    ], [
      "submittedAt" => "DESC"
    ]);
  }

}
