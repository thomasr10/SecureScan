<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
use App\Entity\Report;
use App\Entity\Project;

final class ReportService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function insertReport(array $languages, int $score, string $status, array $details, Project $project): Report
    {

        $report = new Report();
        $report->setLanguages($languages);
        $report->setScore($score);
        $report->setStatus($status);
        $report->setDetails($details);
        $report->setCreatedAt(new DateTimeImmutable());
        $report->setProject($project);

        $this->em->persist($report);
        $this->em->flush();

        return $report;

    }
}
