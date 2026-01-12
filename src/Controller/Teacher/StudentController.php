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
            $groupId = $request->request->get('group');
            $group = $groupId ? $em->getRepository(StudentGroup::class)->find($groupId) : null;
            if (!$group || $group->getTeacher() !== $teacher) {
                $this->addFlash('error', 'Veuillez sélectionner une classe valide.');
                return $this->render('teacher/student/add.html.twig', [
                    'teacher' => $teacher,
                    'groups' => $teacher->getStudentGroups(),
                ]);
            }

            $studentsData = $request->request->all('students');
            if (!is_array($studentsData)) {
                $studentsData = [];
            }

            $studentsToCreate = [];
            foreach ($studentsData as $row) {
                $firstName = trim((string) ($row['firstName'] ?? ''));
                $lastName = trim((string) ($row['lastName'] ?? ''));
                $niveauScolaire = trim((string) ($row['niveauScolaire'] ?? ''));

                $allEmpty = $firstName === '' && $lastName === '' && $niveauScolaire === '';
                if ($allEmpty) {
                    continue;
                }

                if ($firstName === '' || $lastName === '' || $niveauScolaire === '') {
                    $this->addFlash('error', 'Chaque élève rempli doit avoir un prénom, un nom et un niveau scolaire.');
                    return $this->render('teacher/student/add.html.twig', [
                        'teacher' => $teacher,
                        'groups' => $teacher->getStudentGroups(),
                    ]);
                }

                $studentsToCreate[] = [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'niveauScolaire' => $niveauScolaire,
                ];
            }

            if ($studentsToCreate === []) {
                $this->addFlash('error', 'Veuillez renseigner au moins un élève.');
                return $this->render('teacher/student/add.html.twig', [
                    'teacher' => $teacher,
                    'groups' => $teacher->getStudentGroups(),
                ]);
            }

            foreach ($studentsToCreate as $data) {
                $student = new Student();
                $student->setFirstName($data['firstName']);
                $student->setLastName($data['lastName']);
                $student->setNiveauScolaire($data['niveauScolaire']);
                $student->setSchool($teacher->getSchool());
                $student->setStudentGroup($group);
                $em->persist($student);
            }

            $em->flush();

            $this->addFlash('success', count($studentsToCreate) . ' élève(s) ajouté(s) avec succès.');
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
