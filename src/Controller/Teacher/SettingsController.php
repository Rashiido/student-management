<?php

namespace App\Controller\Teacher;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEACHER')]
class SettingsController extends AbstractController
{
    #[Route('/teacher/settings', name: 'teacher_settings')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $teacher = $user->getTeacher();
        if (!$teacher) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $formType = (string) $request->request->get('form_type', 'password_update');

            if ($formType === 'phone_update') {
                $token = (string) $request->request->get('_token');
                if (!$this->isCsrfTokenValid('teacher_phone_update', $token)) {
                    $this->addFlash('error', 'Jeton CSRF invalide.');
                    return $this->redirectToRoute('teacher_settings');
                }

                $phone = trim((string) $request->request->get('phone'));
                if ($phone === '') {
                    $teacher->setPhone(null);
                } elseif (!preg_match('/^0[67]\d{8}$/', $phone)) {
                    $this->addFlash('error', 'Le numero doit commencer par 06 ou 07 et contenir 10 chiffres.');
                    return $this->redirectToRoute('teacher_settings');
                } else {
                    $teacher->setPhone($phone);
                }

                $em->flush();
                $this->addFlash('success', 'Telephone mis a jour.');
                return $this->redirectToRoute('teacher_settings');
            }

            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('teacher_password_change', $token)) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('teacher_settings');
            }

            $currentPassword = (string) $request->request->get('current_password');
            $newPassword = (string) $request->request->get('new_password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
            } elseif (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Mot de passe actuel incorrect.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'La confirmation ne correspond pas.');
            } elseif (!$this->isStrongPassword($newPassword)) {
                $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caracteres, une majuscule, une minuscule et un chiffre.');
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $em->flush();
                $this->addFlash('success', 'Mot de passe mis a jour.');
                return $this->redirectToRoute('teacher_settings');
            }
        }

        return $this->render('teacher/settings/index.html.twig', [
            'teacher' => $teacher,
            'user' => $user,
        ]);
    }

    private function isStrongPassword(string $password): bool
    {
        if (strlen($password) < 8) {
            return false;
        }

        $hasUpper = preg_match('/[A-Z]/', $password) === 1;
        $hasLower = preg_match('/[a-z]/', $password) === 1;
        $hasDigit = preg_match('/\d/', $password) === 1;

        return $hasUpper && $hasLower && $hasDigit;
    }
}
