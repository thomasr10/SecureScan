<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Uid\Uuid;
use ZipArchive;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function showHome(): Response
    {   

    // On affiche la homepage
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
    
    #[Route('/upload', name: 'app_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        $url = $request->request->get('project_url');
        $zip = $request->files->get('project_zip');

        // SI URL GIT
        if ($url && !$zip) {
            
            // checker si url est bien un .git
            $url = trim($url);
            if(!str_ends_with($url, '.git')) {
                $error = "L'URL doit être un dépôt valide";
                
                $this->redirectToRoute('app_home');
            }

            // clone du repo
            try {
                // créer un dossier unique
                $projectId = 'project_' . Uuid::v4();

                // process = composant symfo qui permet d'exécuter des commandes
                $projectsDir = $this->getParameter('kernel.project_dir') . '/projects' . '/' . $projectId;  
                $process = new Process(['git', 'clone', $url, $projectsDir]);
                $process->run();
                
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process->getErrorOutput());
                }

            } catch(\Exception $e) {
                // ajouter erreurs
                return $this->redirectToRoute('app_home');
            }

        }

        // SI .ZIP
        if ($zip && !$url) {
            //créer une archive pour gérer les fichiers zip
            $zipProject = new ZipArchive();

            try {
                // créer un dossier unique
                $projectId = 'project_' . Uuid::v4();
                $projectsDir = $this->getParameter('kernel.project_dir') . '/projects' . '/' . $projectId;

                // ouvrir et extraire le zip dans /projects/
                $zipProject->open($zip->getPathname());
                $zipProject->extractTo($projectsDir);
                $zipProject->close();
            } catch (\Exception $e) {
                return $this->redirectToRoute('app_home');
            }
        }

        return $this->redirectToRoute('app_home');
    }
}
