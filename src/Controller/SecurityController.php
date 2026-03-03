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
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/register', name: 'app_register')]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $error = null;

        // si le formulaire est valider, lance le processus d'insription
        if ($request->isMethod('POST')) {
            // récupère les informations entrer dans le formulaire
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            // vérifie si un utilisateur n'existe pas déjà avec cet email
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser !== null) {
                // l'email est déjà pris : afficher une erreur
                $error = 'Un compte existe déjà avec cet email';
            } else {
                $user = new User();
                $user->setEmail($email);
                $user->setRoles(['ROLE_USER']);

                // encrypte le mot de passe avant de l'ajouter au user
                $hashedPassword = $hasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);

                // enregistre le nouvel utilisateur et redirige vers la page de connexion
                $entityManager->persist($user);
                $entityManager->flush();

                return $this->redirectToRoute('app_login');
            }
        }

        // recharge la page d'inscription en cas d'erreur
        return $this->render('security/register.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
