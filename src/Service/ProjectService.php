<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Project;
use Symfony\Bundle\SecurityBundle\Security;
use DateTimeImmutable;
use App\Repository\ProjectRepository;

final class ProjectService
{

    public function __construct(private EntityManagerInterface $em, private Security $security, private ProjectRepository $projectRepository) {}

    public function createProject(string $projectUuid, string $name, string $type, string $url): Project
    {
        $user = $this->security->getUser();

        $existingProject = $this->projectRepository->findOneByNameAndUser($name, $user);

        if(!$existingProject) {
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

        } else {
            $existingProject->setUuid($projectUuid);
            $existingProject->setType($type);

            if ($type === 'git') {
                $existingProject->setGitUrl($url);
                $existingProject->setZipHash(null);
            }

            if ($type === 'zip') {
                $existingProject->setZipHash($url);
                $existingProject->setGitUrl(null);
            }

            $this->em->flush();
            
            return $existingProject;
        }
    }
}