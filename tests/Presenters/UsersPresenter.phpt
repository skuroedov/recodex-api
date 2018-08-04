<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\ForbiddenRequestException;
use App\Helpers\EmailVerificationHelper;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Users;
use App\Security\Roles;
use App\V1Module\Presenters\UsersPresenter;
use Tester\Assert;

/**
 * @httpCode any
 * @testCase
 */
class TestUsersPresenter extends Tester\TestCase
{
  /** @var UsersPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var Nette\Security\User */
  private $user;

  /** @var string */
  private $presenterPath = "V1:Users";

  /** @var App\Model\Repository\Users */
  protected $users;

  /** @var App\Model\Repository\ExternalLogins */
  protected $externalLogins;

  /** @var  Nette\DI\Container */
  protected $container;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::getEntityManager($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->users = $container->getByType(Users::class);
    $this->externalLogins = $container->getByType(ExternalLogins::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, UsersPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(true);
    }
  }

  public function testGetAllUsers()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $request = new Nette\Application\Request($this->presenterPath, 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::true(count($result['payload']['items']) > 0);
    Assert::true(array_key_exists("offset", $result['payload']));
    Assert::true(array_key_exists('limit', $result['payload']));
    Assert::true(array_key_exists('totalCount', $result['payload']));
    Assert::true(array_key_exists('orderBy', $result['payload']));
    Assert::true(array_key_exists('filters', $result['payload']));
    Assert::count((int)$result['payload']['totalCount'], $result['payload']['items']);

    $users = $result['payload']['items'];
    foreach ($users as $user) {
      Assert::true(array_key_exists("id", $user));
      Assert::true(array_key_exists("fullName", $user));
      Assert::true(array_key_exists("privateData", $user));
    }
  }

  public function testGetAllSuperadmins()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'default', 'filters' => ['roles' => ['superadmin']]]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(1, $result['payload']['items'], json_encode($result['payload']));
    Assert::equal($user->getName(), $result['payload']['items'][0]['fullName']);
  }

  public function testGetAllOrderBy()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'default', 'orderBy' => '!name']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($user->getName(), $result['payload']['items'][ $result['payload']['totalCount']-1 ]['fullName']);
  }

  public function testGetAllPaginated()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    // Get all users ordered by email
    $requestAll = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'default', 'orderBy' => 'email']);
    $responseAll = $this->presenter->run($requestAll);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $responseAll);
    $resultAll = $responseAll->getPayload();
    Assert::equal(200, $resultAll['code']);

    // Get a page of users ordered by email
    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'default', 'orderBy' => 'email', 'offset' => 4, 'limit' => 3]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);
    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    // Compare the paginated result with slice from all
    Assert::equal($result['payload']['totalCount'], $resultAll['payload']['totalCount']);
    $offset = 4;
    foreach ($result['payload']['items'] as $user) {
      Assert::equal($user['id'], $resultAll['payload']['items'][$offset++]['id']);
    }
  }

  public function testGetListUsersByIds()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $users = $this->presenter->users->findAll();
    $first = $users[0];
    $second = $users[1];

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'listByIds'],
      ['ids' => [ $first->getId(), $second->getId() ]]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    $users = $result['payload'];
    foreach ($users as $user) {
      Assert::true(array_key_exists("id", $user));
      Assert::true(array_key_exists("fullName", $user));
      Assert::true(array_key_exists("privateData", $user));
    }
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'detail', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    Assert::same($user->getId(), $result["payload"]["id"]);
  }

  public function testUpdateProfileWithoutEmailAndPassword()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $firstName = "firstNameUpdated";
    $lastName = "lastNameUpdated";
    $degreesBeforeName = "degreesBeforeNameUpdated";
    $degreesAfterName = "degreesAfterNameUpdated";

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateProfile', 'id' => $user->getId()],
      [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName,
        'gravatarUrlEnabled' => false
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $updatedUser = $result["payload"]["user"];
    Assert::equal("$degreesBeforeName $firstName $lastName $degreesAfterName", $updatedUser["fullName"]);
    Assert::null($updatedUser["avatarUrl"]);

    $storedUpdatedUser = $this->users->get($user->getId());
    Assert::same($updatedUser["id"], $storedUpdatedUser->getId());
  }

  public function testUpdateProfileWithEmailAndWithoutPassword()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $firstName = "firstNameUpdated";
    $lastName = "lastNameUpdated";
    $degreesBeforeName = "degreesBeforeNameUpdated";
    $degreesAfterName = "degreesAfterNameUpdated";
    $email = "new-email@recodex.cz";

    $emailVerificationHelper = Mockery::mock(EmailVerificationHelper::class);
    $emailVerificationHelper->shouldReceive("process")->with($user)->andReturn()->once();
    $this->presenter->emailVerificationHelper = $emailVerificationHelper;

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateProfile', 'id' => $user->getId()],
      [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName,
        'email' => $email,
        'gravatarUrlEnabled' => false
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $updatedUser = $result["payload"]["user"];
    Assert::equal("$degreesBeforeName $firstName $lastName $degreesAfterName", $updatedUser["fullName"]);
    Assert::equal($email, $updatedUser["privateData"]["email"]);
    Assert::null($updatedUser["avatarUrl"]);

    $storedUpdatedUser = $this->users->get($user->getId());
    Assert::same($updatedUser["id"], $storedUpdatedUser->getId());
  }

  public function testUpdateProfileWithoutEmailAndWithPassword()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
    $login = $this->presenter->logins->findByUsernameOrThrow($user->getEmail());

    $firstName = "firstNameUpdated";
    $lastName = "lastNameUpdated";
    $degreesBeforeName = "degreesBeforeNameUpdated";
    $degreesAfterName = "degreesAfterNameUpdated";
    $oldPassword = "admin";
    $password = "newPassword";
    $passwordConfirm = "newPassword";

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateProfile', 'id' => $user->getId()],
      [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName,
        'oldPassword' => $oldPassword,
        'password' => $password,
        'passwordConfirm' => $passwordConfirm,
        'gravatarUrlEnabled' => false
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $updatedUser = $result["payload"]["user"];
    Assert::equal("$degreesBeforeName $firstName $lastName $degreesAfterName", $updatedUser["fullName"]);
    Assert::true($login->passwordsMatchOrEmpty($password));
    Assert::null($updatedUser["avatarUrl"]);

    $storedUpdatedUser = $this->users->get($user->getId());
    Assert::equal($updatedUser["id"], $storedUpdatedUser->getId());
  }

  public function testUpdateProfileWithoutNewPassword()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateProfile', 'id' => $user->getId()],
      [
        'firstName' => "firstNameUpdated",
        'lastName' => "lastNameUpdated",
        'degreesBeforeName' => "degreesBeforeNameUpdated",
        'degreesAfterName' => "degreesAfterNameUpdated",
        'oldPassword' => "admin",
        'passwordConfirm' => "newPassword",
        'gravatarUrlEnabled' => false
      ]
    );

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\InvalidArgumentException::class);
  }

  public function testUpdateProfileWithoutNewPasswordConfirm()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateProfile', 'id' => $user->getId()],
      [
        'firstName' => "firstNameUpdated",
        'lastName' => "lastNameUpdated",
        'degreesBeforeName' => "degreesBeforeNameUpdated",
        'degreesAfterName' => "degreesAfterNameUpdated",
        'oldPassword' => "admin",
        'password' => "newPassword",
        'gravatarUrlEnabled' => false
      ]
    );

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\InvalidArgumentException::class);
  }

  public function testUpdateProfileWithoutFirstAndLastName()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $degreesBeforeName = "degreesBeforeNameUpdated";
    $degreesAfterName = "degreesAfterNameUpdated";

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateProfile', 'id' => $user->getId()],
      [
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName,
        'gravatarUrlEnabled' => true
      ]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $updatedUser = $result["payload"]["user"];
    Assert::equal("$degreesBeforeName {$user->getFirstName()} {$user->getLastName()} $degreesAfterName", $updatedUser["fullName"]);
    Assert::true($updatedUser["avatarUrl"] !== null);
  }

  public function testUpdateSettings()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $darkTheme = false;
    $vimMode = false;
    $defaultLanguage = "de";
    $newAssignmentEmails = false;
    $assignmentDeadlineEmails = false;
    $submissionEvaluatedEmails = false;

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateSettings', 'id' => $user->getId()],
      [
        'darkTheme' => $darkTheme,
        'vimMode' => $vimMode,
        'defaultLanguage' => $defaultLanguage,
        'newAssignmentEmails' => $newAssignmentEmails,
        'assignmentDeadlineEmails' => $assignmentDeadlineEmails,
        'submissionEvaluatedEmails' => $submissionEvaluatedEmails
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $user = $result["payload"];
    $settings = $user["privateData"]["settings"];
    Assert::equal($darkTheme, $settings->getDarkTheme());
    Assert::equal($vimMode, $settings->getVimMode());
    Assert::equal($defaultLanguage, $settings->getDefaultLanguage());
    Assert::equal($newAssignmentEmails, $settings->getNewAssignmentEmails());
    Assert::equal($assignmentDeadlineEmails, $settings->getAssignmentDeadlineEmails());
    Assert::equal($submissionEvaluatedEmails, $settings->getSubmissionEvaluatedEmails());
  }

  public function testCreateLocalAccount()
  {
    $instance = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN)->getInstances()->first();
    $user = new User("external@external.external", "firstName", "lastName", "", "", "student", $instance);
    $user->setVerified();
    $external = new ExternalLogin($user, "test", $user->getEmail());

    $this->users->persist($user);
    $this->externalLogins->persist($external);

    PresenterTestHelper::login($this->container, $user->getEmail());

    // pre-test condition
    Assert::equal(false, $user->hasLocalAccount());

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'createLocalAccount', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $payload = $result["payload"];
    Assert::equal($user->getId(), $payload["id"]);
    Assert::equal(true, $payload["privateData"]["isLocal"]);
  }

  public function testSupervisorGroups()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'groups', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(3, $result["payload"]);

    Assert::true(array_key_exists("supervisor", $result["payload"]));
    $supervisorIn = $result["payload"]["supervisor"];
    $expectedSupervisorIn = $user->getGroupsAsSupervisor()->getValues();
    Assert::equal($this->presenter->groupViewFactory->getGroups($expectedSupervisorIn), $supervisorIn);
  }

  public function testStudentGroups()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'groups', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(3, $result["payload"]);

    Assert::true(array_key_exists("student", $result["payload"]));
    $studentIn = $result["payload"]["student"];
    $expectedStudentIn = $user->getGroupsAsStudent()->getValues();
    Assert::equal($this->presenter->groupViewFactory->getGroups($expectedStudentIn), $studentIn);

    Assert::true(array_key_exists("stats", $result["payload"]));
    $stats = $result["payload"]["stats"];
    Assert::count(count($expectedStudentIn), $stats);

    foreach ($stats as $stat) {
      Assert::count(7, $stat);
      Assert::true(array_key_exists("userId", $stat));
      Assert::true(array_key_exists("groupId", $stat));
      Assert::true(array_key_exists("assignments", $stat));
      Assert::true(array_key_exists("shadowAssignments", $stat));
      Assert::true(array_key_exists("points", $stat));
      Assert::true(array_key_exists("hasLimit", $stat));
      Assert::true(array_key_exists("passesLimit", $stat));
    }
  }

  public function testInstances()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'instances', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $instances = $result["payload"];
    Assert::equal(1, count($instances));

    $instance = array_pop($instances);
    Assert::type(\App\Model\Entity\Instance::class, $instance);
    Assert::equal($user->getInstances()->first()->getId(), $instance->getId());
  }

  public function testExercises()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'exercises', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $exercises = $result["payload"];
    Assert::equal(
      array_map(function (Exercise $exercise) { return $exercise->getId(); }, $user->getExercises()->getValues()),
      array_map(function ($exercise) { return $exercise["id"]; }, $exercises)
    );

    foreach ($exercises as $exercise) {
      Assert::same($user->getId(), $exercise["authorId"]);
    }
  }

  public function testUnauthenticatedUserCannotViewUserDetail() {
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'detail', 'id' => $user->getId()]
    );

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, ForbiddenRequestException::class);
  }

  public function testDeleteUser() {
    $victim = "user2@example.com";
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail($victim);

    $request = new Nette\Application\Request($this->presenterPath, 'DELETE',
      ['action' => 'delete', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::null($this->users->getByEmail($victim));
  }

  public function testSetRoleUser() {
    $victim = "user2@example.com";
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail($victim);
    Assert::equal(Roles::STUDENT_ROLE, $user->getRole());

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'setRole', 'id' => $user->getId()],
      ['role' => Roles::SUPERVISOR_ROLE]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(Roles::SUPERVISOR_ROLE, $this->users->getByEmail($victim)->getRole());
  }

}

(new TestUsersPresenter())->run();
