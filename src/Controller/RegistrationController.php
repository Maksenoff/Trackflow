<?php

namespace App\Controller;

use App\Entity\Athlete;
use App\Entity\Performance;
use App\Entity\User;
use App\Form\AthleteType;
use App\Form\RegistrationFormType;
use App\Service\FfaSync;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        Security $security
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles(['ROLE_ATHLETE']);
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $em->persist($user);
            $em->flush();

            $security->login($user, 'form_login', 'main');

            $this->addFlash('info', 'Compte créé ! Créez maintenant votre profil athlète.');
            return $this->redirectToRoute('app_register_link_profile');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/register/link-profile', name: 'app_register_link_profile')]
    #[IsGranted('ROLE_ATHLETE')]
    public function linkProfile(
        Request $request,
        EntityManagerInterface $em,
        FfaSync $ffaSync,
        SluggerInterface $slugger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Already linked → go to their profile
        if ($user->getLinkedAthlete()) {
            return $this->redirectToRoute('app_athlete_show', ['id' => $user->getLinkedAthlete()->getId()]);
        }

        $athlete = new Athlete();
        $form = $this->createForm(AthleteType::class, $athlete);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $disciplinesRaw = $request->request->get('disciplines_raw', '[]');
            $athlete->setDisciplines(json_decode($disciplinesRaw, true) ?: []);

            // Handle photo upload
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile) {
                $safeFilename = $slugger->slug(pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename  = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();
                $uploadDir    = $this->getParameter('kernel.project_dir') . '/public/uploads/athletes';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                try {
                    $photoFile->move($uploadDir, $newFilename);
                    $athlete->setPhoto($newFilename);
                } catch (\Exception) {}
            }

            $em->persist($athlete);
            $user->setLinkedAthlete($athlete);
            $em->flush();

            $this->addFlash('success', 'Profil créé avec succès. Bienvenue ' . $athlete->getFirstName() . ' !');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athlete->getId()]);
        }

        return $this->render('registration/link_profile.html.twig', [
            'form'        => $form,
            'athlete'     => $athlete,
            'disciplines' => Performance::DISCIPLINES,
        ]);
    }
}
