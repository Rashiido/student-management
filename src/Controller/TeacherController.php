<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/teacher')]
#[IsGranted('ROLE_TEACHER')]
class TeacherController extends AbstractController
{
    #[Route('/history', name: 'teacher_history')]
    public function history(): Response
    {
        $user = $this->getUser();
        $teacher = $user->getTeacher();

        if (!$teacher) {
            throw $this->createNotFoundException('Teacher profile not found');
        }

        $school = $teacher->getSchool();
        $groups = $teacher->getStudentGroups();

        return $this->render('teacher/history.html.twig', [
            'school' => $school,
            'groups' => $groups,
            'teacher' => $teacher,
        ]);
    }

    #[Route('/dashboard', name: 'teacher_dashboard')]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        $teacher = $user->getTeacher();

        if (!$teacher) {
            throw $this->createNotFoundException('Teacher profile not found');
        }

        return $this->render('teacher/dashboard.html.twig', [
            'teacher' => $teacher,
        ]);
    }
}
