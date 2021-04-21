<?php

namespace App\V1Module\Presenters;

use App\Async\Handler\PingAsyncJobHandler;
use App\Model\Repository\AsyncJobs;
use App\Model\Entity\AsyncJob;
use App\Security\ACL\IAsyncJobPermissions;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\BadRequestException;
use Doctrine\Common\Collections\Criteria;
use Exception;
use DateTime;

/**
 * Endpoints used by workers to exchange files with core.
 * These endpoints take over responsibilities of FileServer component when integrated file-storage is used.
 */
class AsyncJobsPresenter extends BasePresenter
{
    /**
     * @var AsyncJobs
     * @inject
     */
    public $asyncJobs;

    /**
     * @var IAsyncJobPermissions
     * @inject
     */
    public $asyncJobsAcl;

    public function checkDefault(string $id)
    {
        $asyncJob = $this->asyncJobs->findOrThrow($id);
        if (!$this->asyncJobsAcl->canViewDetail($asyncJob)) {
            throw new ForbiddenRequestException("You cannot see details of given async job");
        }
    }

    /**
     * Retrieves details about particular async job.
     * @GET
     * @param string $id job identifier
     * @throws NotFoundException
     */
    public function actionDefault(string $id)
    {
        $asyncJob = $this->asyncJobs->findOrThrow($id);
        $this->sendSuccessResponse($asyncJob);
    }

    public function checkList()
    {
        if (!$this->asyncJobsAcl->canList()) {
            throw new ForbiddenRequestException("You cannot list async jobs");
        }
    }

    /**
     * Retrieves details about async jobs that are either pending or were recently completed.
     * @GET
     * @param int|null $ageThreshold Maximal time since completion (in seconds), null = only pending operations
     * @param bool|null $includeScheduled If true, pending scheduled events will be listed as well
     * @throws BadRequestException
     */
    public function actionList(?int $ageThreshold, ?bool $includeScheduled)
    {
        if ($ageThreshold && $ageThreshold < 0) {
            throw new BadRequestException("Age threshold must not be negative.");
        }

        // criterium for termination (either pending or within threshold)
        $terminatedAt = Criteria::expr()->eq('terminatedAt', null);
        if ($ageThreshold) {
            $thresholdDate = new DateTime();
            $thresholdDate->modify("-$ageThreshold seconds");
            $terminatedAt = Criteria::expr()->orX(
                $terminatedAt,
                Criteria::expr()->gte('terminatedAt', $thresholdDate)
            );
        }

        $criteria = Criteria::create()->where(
            $includeScheduled
                ? $terminatedAt
                : Criteria::expr()->andX(
                    $terminatedAt,
                    Criteria::expr()->eq('scheduledAt', null)
                )
        );
        $criteria->orderBy([ 'createdAt' => 'ASC' ]);
        $jobs = $this->asyncJobs->matching($criteria)->toArray();

        $jobs = array_filter($jobs, function ($job) {
            return $this->asyncJobsAcl->canViewDetail($job);
        });

        $this->sendSuccessResponse($jobs);
    }

    public function checkAbort(string $id)
    {
        $asyncJob = $this->asyncJobs->findOrThrow($id);
        if (!$this->asyncJobsAcl->canAbort($asyncJob)) {
            throw new ForbiddenRequestException("You cannot abort selected async job");
        }
    }

    /**
     * Retrieves details about particular async job.
     * @POST
     * @param string $id job identifier
     * @throws NotFoundException
     */
    public function actionAbort(string $id)
    {
        $this->asyncJobs->beginTransaction();
        try {
            $asyncJob = $this->asyncJobs->findOrThrow($id);
            if ($asyncJob->getStartedAt() === null && $asyncJob->getTerminatedAt() === null) {
                // if the job has not been started yet, it can be aborted
                $asyncJob->setTerminatedNow();
                $asyncJob->appendError("ABORTED");
                $this->asyncJobs->commit();
            } else {
                $this->asyncJobs->rollback();
            }
        } catch (Exception $e) {
            $this->asyncJobs->rollback();
            throw $e;
        }

        $this->sendSuccessResponse($asyncJob);
    }

    public function checkPing()
    {
        if (!$this->asyncJobsAcl->canPing()) {
            throw new ForbiddenRequestException("You cannot ping async job worker");
        }
    }

    /**
     * Initiates ping job. An empty job designed to verify the async handler is running.
     * @POST
     */
    public function actionPing()
    {
        $asyncJob = PingAsyncJobHandler::createAsyncJob($this->getCurrentUser());
        $this->asyncJobs->persist($asyncJob);
        $this->sendSuccessResponse($asyncJob);
    }
}
