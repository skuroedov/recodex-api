<?php

namespace App\Helpers;

use Latte;
use Nette\Utils\Arrays;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Login;
use App\Model\Entity\ForgottenPassword;
use App\Security\AccessToken;
use App\Security\AccessManager;

use DateTime;
use DateInterval;

/**
 * Sending error reports to administrator by email.
 */
class ForgottenPasswordHelper {

  /**
   * Database entity manager
   * @var EntityManager
   */
  private $em;

  /**
   * Emails sending component
   * @var EmailHelper
   */
  private $emailHelper;

  /**
   * Sender address of all mails, something like "noreply@recodex.cz"
   * @var string
   */
  private $sender;

  /**
   * Prefix of mail subject to be used
   * @var string
   */
  private $subjectPrefix;

  /**
   * URL which will be sent to user with token.
   * @var string
   */
  private $redirectUrl;

  /**
   * Expiration period of the change-password token in seconds
   * @var int
   */
  private $tokenExpiration;

  /**
   * @var AccessManager
   */
  private $accessManager;

  /**
   * Constructor
   * @param \App\Helpers\EmailHelper $emailHelper
   */
  public function __construct(EntityManager $em, EmailHelper $emailHelper, AccessManager $accessManager , array $params) {
    $this->em = $em;
    $this->emailHelper = $emailHelper;
    $this->accessManager = $accessManager;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.cz");
    $this->subjectPrefix = Arrays::get($params, ["emails", "subjectPrefix"], "ReCodEx Forgotten Password Request - ");
    $this->redirectUrl = Arrays::get($params, ["redirectUrl"], "https://recodex.cz");
    $this->tokenExpiration = Arrays::get($params, ["tokenExpiration"], 10 * 60); // default value: 10 minutes
  }

  /**
   * Generate access token and send it to the given email.
   * @param Login $login
   */
  public function process(Login $login) {
    // Stalk forgotten password requests a little bit and store them to database
    $entry = new ForgottenPassword($login->user, $login->user->email, $this->redirectUrl);
    $this->em->persist($entry);
    $this->em->flush();

    // prepare all necessary things
    $token = $this->accessManager->issueToken($login->user, [ AccessToken::SCOPE_CHANGE_PASSWORD ], $this->tokenExpiration);
    $subject = $this->createSubject($login);
    $message = $this->createBody($login, $token);

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      [ $login->user->email ],
      $subject,
      $message
    );
  }

  private function createSubject(Login $login): string {
    return $this->subjectPrefix . " " . $login->username;
  }

  private function createBody(Login $login, string $token): string {
    // show to user a minute less, so he doesn't waste time ;-)
    $exp = $this->tokenExpiration - 60;
    $expiresAfter = (new DateTime)->add(new DateInterval("P{$exp}s"));

    // render the HTML to string using Latte engine
    $latte = new Latte\Engine;
    return $latte->renderToString(__DIR__ . "/forgottenPasswordEmail.latte", [
      "username" => $login->username,
      "link" => "{$this->redirectUrl}#{$token}",
      "expiresAfter" => $expiresAfter->format("H:i")
    ]);
  }

}
