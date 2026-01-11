<?php

namespace App\Controller\Teacher;

use App\Entity\Student;
use App\Entity\StudentGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEACHER')]
#[Route('/teacher/students')]
class StudentController extends AbstractController
{
    #[Route('/', name: 'teacher_my_students')]
    public function index(Request $request, EntityManagerInterface $em): Response
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

        $groupId = $request->query->get('group');
        $group = null;
        if ($groupId) {
            $group = $em->getRepository(StudentGroup::class)->find($groupId);
            if ($group && $group->getTeacher() !== $teacher) {
                throw $this->createAccessDeniedException('You do not have access to this group.');
            }
        }

        $students = [];
        if ($group) {
            $students = $group->getStudents();
        } else {
            $studentGroups = $teacher->getStudentGroups();
            foreach ($studentGroups as $studentGroup) {
                foreach ($studentGroup->getStudents() as $student) {
                    $students[] = $student;
                }
            }
        }

        return $this->render('teacher/student/index.html.twig', [
            'teacher' => $teacher,
            'group' => $group,
            'students' => $students,
            'groups' => $teacher->getStudentGroups(),
        ]);
    }

    #[Route('/add', name: 'teacher_student_add')]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
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
            $firstName = trim((string) $request->request->get('firstName'));
            $lastName = trim((string) $request->request->get('lastName'));
            $niveauScolaire = trim((string) $request->request->get('niveauScolaire'));

            if ($firstName === '' || $lastName === '' || $niveauScolaire === '') {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->render('teacher/student/add.html.twig', [
                    'teacher' => $teacher,
                    'groups' => $teacher->getStudentGroups(),
                ]);
            }

            $groupId = $request->request->get('group');
            $group = $groupId ? $em->getRepository(StudentGroup::class)->find($groupId) : null;
            if (!$group || $group->getTeacher() !== $teacher) {
                $this->addFlash('error', 'Veuillez selectionner une classe valide.');
                return $this->render('teacher/student/add.html.twig', [
                    'teacher' => $teacher,
                    'groups' => $teacher->getStudentGroups(),
                ]);
            }

            $student = new Student();
            $student->setFirstName($firstName);
            $student->setLastName($lastName);
            $student->setNiveauScolaire($niveauScolaire);
            $student->setSchool($teacher->getSchool());
            $student->setStudentGroup($group);

            $em->persist($student);
            $em->flush();

            $this->addFlash('success', 'Eleve ajoute avec succes.');
            return $this->redirectToRoute('teacher_my_students');
        }

        return $this->render('teacher/student/add.html.twig', [
            'teacher' => $teacher,
            'groups' => $teacher->getStudentGroups(),
        ]);
    }

    #[Route('/{id}/delete', name: 'teacher_student_delete', methods: ['POST'])]
    public function delete(Request $request, Student $student, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $teacher = $user->getTeacher();
        if (!$teacher || $student->getStudentGroup()?->getTeacher() !== $teacher) {
            throw $this->createAccessDeniedException('You do not have access to this student.');
        }

        if (!$this->isCsrfTokenValid('delete_student' . $student->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('teacher_my_students');
        }

        foreach ($student->getAttendances() as $attendance) {
            $em->remove($attendance);
        }

        $em->remove($student);
        $em->flush();

        $this->addFlash('success', 'Eleve supprime avec succes.');
        return $this->redirectToRoute('teacher_my_students');
    }
}
