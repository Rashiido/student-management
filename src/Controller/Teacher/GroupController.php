<?php

namespace App\Controller\Teacher;

use App\Entity\StudentGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEACHER')]
#[Route('/teacher/groups')]
class GroupController extends AbstractController
{
    #[Route('/', name: 'teacher_my_groups')]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $teacher = $user->getTeacher();
        if (!$teacher) {
            throw $this->createAccessDeniedException('You must be a teacher to access this page.');
        }

        return $this->render('teacher/group/index.html.twig', [
            'teacher' => $teacher,
            'groups' => $teacher->getStudentGroups(),
        ]);
    }

    #[Route('/add', name: 'teacher_group_add')]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $teacher = $user->getTeacher();
        if (!$teacher) {
            throw $this->createAccessDeniedException('You must be a teacher to access this page.');
        }

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name'));
            if ($name === '') {
                $this->addFlash('error', 'Le nom de la classe est requis.');
                return $this->render('teacher/group/add.html.twig', ['teacher' => $teacher]);
            }

            $group = new StudentGroup();
            $group->setName($name);
            $group->setTeacher($teacher);
            $group->setSchool($teacher->getSchool());

            $em->persist($group);
            $em->flush();

            $this->addFlash('success', 'Classe ajoutee avec succes.');
            return $this->redirectToRoute('teacher_my_groups');
        }

        return $this->render('teacher/group/add.html.twig', [
            'teacher' => $teacher,
        ]);
    }

    #[Route('/{id}/edit', name: 'teacher_group_edit')]
    public function edit(Request $request, StudentGroup $group, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $teacher = $user->getTeacher();
        if (!$teacher || $group->getTeacher() !== $teacher) {
            throw $this->createAccessDeniedException('You do not have access to this group.');
        }

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name'));
            if ($name === '') {
                $this->addFlash('error', 'Le nom de la classe est requis.');
                return $this->render('teacher/group/edit.html.twig', [
                    'teacher' => $teacher,
                    'group' => $group,
                ]);
            }

            $group->setName($name);
            $em->flush();

            $this->addFlash('success', 'Classe modifiee avec succes.');
            return $this->redirectToRoute('teacher_my_groups');
        }

        return $this->render('teacher/group/edit.html.twig', [
            'teacher' => $teacher,
            'group' => $group,
        ]);
    }

    #[Route('/{id}/delete', name: 'teacher_group_delete', methods: ['POST'])]
    public function delete(Request $request, StudentGroup $group, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $teacher = $user->getTeacher();
        if (!$teacher || $group->getTeacher() !== $teacher) {
            throw $this->createAccessDeniedException('You do not have access to this group.');
        }

        if (!$this->isCsrfTokenValid('delete_group' . $group->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('teacher_my_groups');
        }

        if (count($group->getStudents()) > 0 || count($group->getSchedules()) > 0 || count($group->getAttendances()) > 0) {
            $this->addFlash('error', 'Cette classe contient des eleves, des horaires ou des presences. Supprimez-les d abord.');
            return $this->redirectToRoute('teacher_my_groups');
        }

        $em->remove($group);
        $em->flush();

        $this->addFlash('success', 'Classe supprimee avec succes.');
        return $this->redirectToRoute('teacher_my_groups');
    }
}
