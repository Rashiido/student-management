<?php

namespace App\Controller\Admin;

use App\Entity\Teacher;
use App\Form\TeacherType;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/teacher')]
class TeacherController extends AbstractController
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    #[Route('/', name: 'admin_teacher_index', methods: ['GET'])]
    public function index(TeacherRepository $teacherRepository): Response
    {
        $teachers = $teacherRepository->findAll();

        return $this->render('admin/teacher/index.html.twig', [
            'teachers' => $teachers,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_teacher_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Teacher $teacher, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TeacherType::class, $teacher);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // If username was changed, update the password
            $user = $teacher->getUser();
            if ($user && $form->get('user')->get('username')->getData()) {
                $newUsername = $form->get('user')->get('username')->getData();
                $oldUsername = $user->getUsername();

                if ($newUsername !== $oldUsername) {
                    // Generate cryptographically secure random password
                    $securePassword = bin2hex(random_bytes(16));
                    $user->setPassword($this->passwordHasher->hashPassword($user, $securePassword));
                    // Do not store plain password
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'L\'enseignant a été modifié avec succès.');
            return $this->redirectToRoute('admin_teacher_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/teacher/edit.html.twig', [
            'teacher' => $teacher,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_teacher_delete', methods: ['POST'])]
    public function delete(Request $request, Teacher $teacher, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$teacher->getId(), $request->request->get('_token'))) {
            // Remove associated user
            $user = $teacher->getUser();
            if ($user) {
                $entityManager->remove($user);
            }

            $entityManager->remove($teacher);
            $entityManager->flush();

            $this->addFlash('success', 'L\'enseignant a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_teacher_index', [], Response::HTTP_SEE_OTHER);
    }
}
