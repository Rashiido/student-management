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

        $maxStudents = 30;
        $studentsInput = [];
        $studentCount = 1;

        if ($request->isMethod('POST')) {
            // Validate CSRF token
            if (!$this->isCsrfTokenValid('add_students', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('teacher_student_add');
            }

            $studentCount = (int) $request->request->get('student_count', 1);
            if ($studentCount < 1) {
                $studentCount = 1;
            } elseif ($studentCount > $maxStudents) {
                $studentCount = $maxStudents;
            }

            $submittedStudents = $request->request->all('students');
            if (!is_array($submittedStudents)) {
                $submittedStudents = [];
            }

            $studentsToCreate = [];
            $errors = [];

            for ($i = 1; $i <= $studentCount; $i++) {
                $data = $submittedStudents[$i] ?? [];
                $firstName = trim((string) ($data['firstName'] ?? ''));
                $lastName = trim((string) ($data['lastName'] ?? ''));
                $niveauRaw = trim((string) ($data['niveauScolaire'] ?? ''));
                $niveauValue = filter_var($niveauRaw, FILTER_VALIDATE_INT, [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => 6,
                    ],
                ]);

                $studentsInput[$i] = [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'niveauScolaire' => $niveauRaw,
                ];

                if ($firstName === '' || $lastName === '' || $niveauRaw === '') {
                    $errors[] = "L'eleve {$i} est incomplet.";
                    continue;
                }

                if (false === $niveauValue) {
                    $errors[] = "Le niveau scolaire de l'eleve {$i} doit etre entre 1 et 6.";
                    continue;
                }

                $studentsToCreate[] = [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'niveauScolaire' => (string) $niveauValue,
                ];
            }

            $groupId = $request->request->get('group');
            $group = $groupId ? $em->getRepository(StudentGroup::class)->find($groupId) : null;
            if (!$group || $group->getTeacher() !== $teacher) {
                $this->addFlash('error', 'Veuillez selectionner une classe valide.');
                return $this->render('teacher/student/add.html.twig', [
                    'teacher' => $teacher,
                    'groups' => $teacher->getStudentGroups(),
                    'studentsInput' => $studentsInput,
                    'studentCount' => $studentCount,
                    'selectedGroupId' => $groupId,
                ]);
            }

            if (empty($studentsToCreate)) {
                $errors[] = 'Ajoutez au moins un eleve.';
            }

            if (!empty($errors)) {
                $this->addFlash('error', implode(' ', $errors));
                return $this->render('teacher/student/add.html.twig', [
                    'teacher' => $teacher,
                    'groups' => $teacher->getStudentGroups(),
                    'studentsInput' => $studentsInput,
                    'studentCount' => $studentCount,
                    'selectedGroupId' => $groupId,
                ]);
            }

            foreach ($studentsToCreate as $studentData) {
                $student = new Student();
                $student->setFirstName($studentData['firstName']);
                $student->setLastName($studentData['lastName']);
                $student->setNiveauScolaire($studentData['niveauScolaire']);
                $student->setSchool($group->getSchool());
                $student->setStudentGroup($group);
                $em->persist($student);
            }

            $em->flush();

            $this->addFlash('success', 'Eleves ajoutes avec succes.');
            return $this->redirectToRoute('teacher_my_students');
        }

        return $this->render('teacher/student/add.html.twig', [
            'teacher' => $teacher,
            'groups' => $teacher->getStudentGroups(),
            'studentsInput' => $studentsInput,
            'studentCount' => $studentCount,
            'selectedGroupId' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'teacher_student_edit')]
    public function edit(Request $request, Student $student, EntityManagerInterface $em): Response
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

        $firstName = (string) $request->request->get('firstName', $student->getFirstName());
        $lastName = (string) $request->request->get('lastName', $student->getLastName());
        $niveauScolaire = (string) $request->request->get('niveauScolaire', $student->getNiveauScolaire());
        $selectedGroupId = (string) $request->request->get('group', (string) $student->getStudentGroup()?->getId());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_student' . $student->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
            } else {
                $firstName = trim($firstName);
                $lastName = trim($lastName);
                $niveauScolaire = trim($niveauScolaire);

                if ($firstName === '' || $lastName === '' || $niveauScolaire === '') {
                    $this->addFlash('error', 'Tous les champs sont obligatoires.');
                } else {
                    $niveauValue = filter_var($niveauScolaire, FILTER_VALIDATE_INT, [
                        'options' => [
                            'min_range' => 1,
                            'max_range' => 6,
                        ],
                    ]);

                    if (false === $niveauValue) {
                        $this->addFlash('error', 'Le niveau scolaire doit etre entre 1 et 6.');
                        return $this->render('teacher/student/edit.html.twig', [
                            'teacher' => $teacher,
                            'student' => $student,
                            'groups' => $teacher->getStudentGroups(),
                            'firstName' => $firstName,
                            'lastName' => $lastName,
                            'niveauScolaire' => $niveauScolaire,
                            'selectedGroupId' => $selectedGroupId,
                        ]);
                    }

                    $niveauScolaire = (string) $niveauValue;
                    $group = $selectedGroupId !== ''
                        ? $em->getRepository(StudentGroup::class)->find($selectedGroupId)
                        : null;

                    if (!$group || $group->getTeacher() !== $teacher) {
                        $this->addFlash('error', 'Veuillez selectionner une classe valide.');
                    } else {
                        $student->setFirstName($firstName);
                        $student->setLastName($lastName);
                        $student->setNiveauScolaire($niveauScolaire);
                        $student->setSchool($group->getSchool());
                        $student->setStudentGroup($group);

                        $em->flush();

                        $this->addFlash('success', 'Eleve modifie avec succes.');
                        return $this->redirectToRoute('teacher_my_students');
                    }
                }
            }
        }

        return $this->render('teacher/student/edit.html.twig', [
            'teacher' => $teacher,
            'student' => $student,
            'groups' => $teacher->getStudentGroups(),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'niveauScolaire' => $niveauScolaire,
            'selectedGroupId' => $selectedGroupId,
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
