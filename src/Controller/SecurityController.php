<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login', methods: ['GET','POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // si déjà connecté => home
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // si erreur, Symfony revient ici
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/register', name: 'app_register', methods: ['GET','POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher
    ): Response {
        $error = null;

        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email');
            $password = (string) $request->request->get('password');

            if (!$email || !$password) {
                $error = 'Email et mot de passe requis';
            } else {
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existingUser) {
                    $error = 'Un compte existe déjà avec cet email';
                } else {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setRoles(['ROLE_USER']);

                    $hashedPassword = $hasher->hashPassword($user, $password);
                    $user->setPassword($hashedPassword);

                    $entityManager->persist($user);
                    $entityManager->flush();

                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by Symfony.');
    }
}