<?php

namespace App\Helpers\Notifications;

use App\Helpers\EmailHelper;
use App\Model\Entity\Assignment;
use Latte;
use Nette\Utils\Arrays;

/**
 * Sending emails on assignment creation.
 */
class AssignmentEmailsSender {

  /** @var EmailHelper */
  private $emailHelper;
  /** @var string */
  private $sender;
  /** @var string */
  private $newAssignmentPrefix;
  /** @var string */
  private $assignmentDeadlinePrefix;

  /**
   * Constructor.
   * @param EmailHelper $emailHelper
   * @param array $params
   */
  public function __construct(EmailHelper $emailHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.cz");
    $this->newAssignmentPrefix = Arrays::get($params, ["emails", "newAssignmentPrefix"], "ReCodEx New Assignment Notification - ");
    $this->assignmentDeadlinePrefix = Arrays::get($params, ["emails", "assignmentDeadlinePrefix"], "ReCodEx Assignment Deadline Is Behind the Corner - ");
  }

  /**
   * Assignment was created, send emails to users who wanted to know this
   * situation.
   * @param Assignment $assignment
   * @return boolean
   */
  public function assignmentCreated(Assignment $assignment): bool {
    $subject = $this->newAssignmentPrefix . " " . $assignment->getName();

    $recipients = array();
    foreach ($assignment->getGroup()->getStudents() as $student) {
      $recipients[] = $student->getEmail(); // TODO: make sure user want to receive email notifications
    }

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      $recipients,
      $subject,
      $this->createNewAssignmentBody($assignment)
    );
  }

  /**
   * Deadline of assignment is nearby co users which do not submit any solution
   * should be alerted.
   * @param Assignment $assignment
   * @return bool
   */
  public function assignmentDeadline(Assignment $assignment): bool {
    $subject = $this->newAssignmentPrefix . " " . $assignment->getName();

    $recipients = array();
    foreach ($assignment->getGroup()->getStudents() as $student) {
      $recipients[] = $student->getEmail(); // TODO: make sure user want to receive email notifications
    }

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      $recipients,
      $subject,
      $this->createAssignmentDeadlineBody($assignment)
    );
  }

  /**
   * Prepare and format body of the new assignment mail
   * @param Assignment $assignment
   * @return string Formatted mail body to be sent
   */
  private function createNewAssignmentBody(Assignment $assignment): string {
    // render the HTML to string using Latte engine
    $latte = new Latte\Engine;
    return $latte->renderToString(__DIR__ . "/newAssignmentEmail.latte", [
      "assignment" => $assignment->getName()
    ]);
  }

  /**
   * Prepare and format body of the assignment deadline mail
   * @param Assignment $assignment
   * @return string Formatted mail body to be sent
   */
  private function createAssignmentDeadlineBody(Assignment $assignment): string {
    // render the HTML to string using Latte engine
    $latte = new Latte\Engine;
    return $latte->renderToString(__DIR__ . "/assignmentDeadline.latte", [
      "assignment" => $assignment->getName()
    ]);
  }

}
