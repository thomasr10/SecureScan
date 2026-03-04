<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Project;
use Symfony\Bundle\SecurityBundle\Security;
use DateTimeImmutable;

final class ProjectService
{

    public function __construct(private EntityManagerInterface $em, private Security $security) {}

    public function createProject(string $projectUuid, string $name, string $type, string $url): Project
    {
        $user = $this->security->getUser();

        $project = new Project();

        $project->setUuid($projectUuid);
        $project->setName($name);
        $project->setType($type);

        if ($type === 'git') {
           $project->setGitUrl($url); 
        }

        if ($type === 'zip') {
            $project->setZipHash($url);
        }

        $project->setCreatedAt(new DateTimeImmutable());
        $project->setUserId($user);

        $this->em->persist($project);
        $this->em->flush();

        return $project;
    }
}