<?php
// src/Controller/Admin/SchoolApiController.php
namespace App\Controller\Admin;

use App\Entity\School;
use App\Entity\StudentGroup;
use App\Entity\Student;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/api')]
class SchoolApiController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    // ============================================
    // SCHOOLS ENDPOINTS
    // ============================================

    #[Route('/schools', name: 'admin_api_schools_list', methods: ['GET'])]
    public function getSchools(): JsonResponse
    {
        try {
            $schools = $this->entityManager->getRepository(School::class)->findAll();

            $schoolsData = [];
            foreach ($schools as $school) {
                $schoolsData[] = $this->formatSchoolData($school);
            }

            return new JsonResponse([
                'success' => true,
                'schools' => $schoolsData,
                'count' => count($schoolsData)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors du chargement des écoles',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/schools', name: 'admin_api_schools_create', methods: ['POST'])]
    public function createSchool(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation
            if (empty($data['name'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Le nom de l\'école est requis'
                ], 400);
            }

            // Check if school already exists
            $existingSchool = $this->entityManager->getRepository(School::class)
                ->findOneBy(['name' => $data['name']]);

            if ($existingSchool) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Une école avec ce nom existe déjà'
                ], 400);
            }

            // Create new school using only properties you have
            $school = new School();
            $school->setName($data['name']);

            // Only set if provided and property exists
            if (isset($data['address'])) {
                $school->setAddress($data['address']);
            }

            if (isset($data['phone'])) {
                $school->setPhone($data['phone']);
            }

            $this->entityManager->persist($school);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'École créée avec succès',
                'school' => $this->formatSchoolData($school)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la création de l\'école',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/schools/{id}', name: 'admin_api_schools_update', methods: ['PUT'])]
    public function updateSchool(int $id, Request $request): JsonResponse
    {
        try {
            $school = $this->entityManager->getRepository(School::class)->find($id);

            if (!$school) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'École non trouvée'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            // Update only properties that exist
            if (isset($data['name'])) {
                $school->setName($data['name']);
            }

            if (isset($data['address'])) {
                $school->setAddress($data['address']);
            }

            if (isset($data['phone'])) {
                $school->setPhone($data['phone']);
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'École mise à jour avec succès',
                'school' => $this->formatSchoolData($school)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour de l\'école',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/schools/{id}', name: 'admin_api_schools_delete', methods: ['DELETE'])]
    public function deleteSchool(int $id): JsonResponse
    {
        try {
            $school = $this->entityManager->getRepository(School::class)->find($id);

            if (!$school) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'École non trouvée'
                ], 404);
            }

            // Check if school has groups
            $groups = $school->getStudentGroups();
            if (count($groups) > 0) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Impossible de supprimer l\'école car elle contient des groupes. Supprimez d\'abord les groupes.'
                ], 400);
            }

            // Check if school has students directly (though they should be in groups)
            $students = $school->getStudents();
            if (count($students) > 0) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Impossible de supprimer l\'école car elle contient des étudiants. Supprimez d\'abord les étudiants.'
                ], 400);
            }

            $this->entityManager->remove($school);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'École supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la suppression de l\'école',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // STUDENT GROUPS ENDPOINTS (Note: Entity is StudentGroup, not Group)
    // ============================================

    #[Route('/student-groups', name: 'admin_api_student_groups_create', methods: ['POST'])]
    public function createStudentGroup(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation
            if (empty($data['name'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Le nom du groupe est requis'
                ], 400);
            }

            if (empty($data['schoolId'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'L\'école est requise'
                ], 400);
            }

            // Find school
            $school = $this->entityManager->getRepository(School::class)->find($data['schoolId']);
            if (!$school) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'École non trouvée'
                ], 404);
            }

            // Check if group already exists in this school
            $existingGroup = $this->entityManager->getRepository(StudentGroup::class)
                ->findOneBy([
                    'name' => $data['name'],
                    'school' => $school
                ]);

            if ($existingGroup) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Un groupe avec ce nom existe déjà dans cette école'
                ], 400);
            }

            // Create new student group
            $studentGroup = new StudentGroup();
            $studentGroup->setName($data['name']);
            $studentGroup->setSchool($school);

            // Set teacher if provided
            if (!empty($data['teacherId'])) {
                $teacher = $this->entityManager->getRepository('App\Entity\Teacher')->find($data['teacherId']);
                if ($teacher) {
                    $studentGroup->setTeacher($teacher);
                }
            }

            $this->entityManager->persist($studentGroup);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Groupe créé avec succès',
                'studentGroup' => $this->formatStudentGroupData($studentGroup)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la création du groupe',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/student-groups/{id}', name: 'admin_api_student_groups_update', methods: ['PUT'])]
    public function updateStudentGroup(int $id, Request $request): JsonResponse
    {
        try {
            $studentGroup = $this->entityManager->getRepository(StudentGroup::class)->find($id);

            if (!$studentGroup) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Groupe non trouvé'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            // Update fields
            if (isset($data['name'])) {
                $studentGroup->setName($data['name']);
            }

            // Update school if provided
            if (isset($data['schoolId'])) {
                $school = $this->entityManager->getRepository(School::class)->find($data['schoolId']);
                if ($school) {
                    $studentGroup->setSchool($school);
                }
            }

            // Update teacher if provided
            if (isset($data['teacherId'])) {
                if (empty($data['teacherId'])) {
                    $studentGroup->setTeacher(null);
                } else {
                    $teacher = $this->entityManager->getRepository('App\Entity\Teacher')->find($data['teacherId']);
                    if ($teacher) {
                        $studentGroup->setTeacher($teacher);
                    }
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Groupe mis à jour avec succès',
                'studentGroup' => $this->formatStudentGroupData($studentGroup)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour du groupe',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/student-groups/{id}', name: 'admin_api_student_groups_delete', methods: ['DELETE'])]
    public function deleteStudentGroup(int $id): JsonResponse
    {
        try {
            $studentGroup = $this->entityManager->getRepository(StudentGroup::class)->find($id);

            if (!$studentGroup) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Groupe non trouvé'
                ], 404);
            }

            // Check if group has students
            $students = $studentGroup->getStudents();
            if (count($students) > 0) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Impossible de supprimer le groupe car il contient des étudiants. Supprimez d\'abord les étudiants.'
                ], 400);
            }

            $this->entityManager->remove($studentGroup);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Groupe supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la suppression du groupe',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // STUDENTS ENDPOINTS
    // ============================================

    #[Route('/students', name: 'admin_api_students_create', methods: ['POST'])]
    public function createStudent(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation
            if (empty($data['firstName']) || empty($data['lastName'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Le prénom et le nom sont requis'
                ], 400);
            }

            if (empty($data['studentGroupId'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Le groupe est requis'
                ], 400);
            }

            if (empty($data['niveauScolaire'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Le niveau scolaire est requis'
                ], 400);
            }

            // Find group
            $studentGroup = $this->entityManager->getRepository(StudentGroup::class)->find($data['studentGroupId']);
            if (!$studentGroup) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Groupe non trouvé'
                ], 404);
            }

            // Create new student
            $student = new Student();
            $student->setFirstName($data['firstName']);
            $student->setLastName($data['lastName']);
            $student->setNiveauScolaire($data['niveauScolaire']);
            $student->setStudentGroup($studentGroup);
            $student->setSchool($studentGroup->getSchool()); // Set school from group

            // Set optional fields
            if (isset($data['dateOfBirth'])) {
                $student->setDateOfBirth(new \DateTime($data['dateOfBirth']));
            }

            $this->entityManager->persist($student);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Étudiant créé avec succès',
                'student' => $this->formatStudentData($student)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la création de l\'étudiant',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/students/{id}', name: 'admin_api_students_update', methods: ['PUT'])]
    public function updateStudent(int $id, Request $request): JsonResponse
    {
        try {
            $student = $this->entityManager->getRepository(Student::class)->find($id);

            if (!$student) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Étudiant non trouvé'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            // Update fields
            if (isset($data['firstName'])) {
                $student->setFirstName($data['firstName']);
            }

            if (isset($data['lastName'])) {
                $student->setLastName($data['lastName']);
            }

            if (isset($data['niveauScolaire'])) {
                $student->setNiveauScolaire($data['niveauScolaire']);
            }

            if (isset($data['dateOfBirth'])) {
                $student->setDateOfBirth(new \DateTime($data['dateOfBirth']));
            }

            // Update group if provided
            if (isset($data['studentGroupId'])) {
                $studentGroup = $this->entityManager->getRepository(StudentGroup::class)->find($data['studentGroupId']);
                if ($studentGroup) {
                    $student->setStudentGroup($studentGroup);
                    $student->setSchool($studentGroup->getSchool()); // Update school when group changes
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Étudiant mis à jour avec succès',
                'student' => $this->formatStudentData($student)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour de l\'étudiant',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/students/{id}', name: 'admin_api_students_delete', methods: ['DELETE'])]
    public function deleteStudent(int $id): JsonResponse
    {
        try {
            $student = $this->entityManager->getRepository(Student::class)->find($id);

            if (!$student) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Étudiant non trouvé'
                ], 404);
            }

            $this->entityManager->remove($student);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Étudiant supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la suppression de l\'étudiant',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function formatSchoolData(School $school): array
    {
        $studentGroups = $school->getStudentGroups();
        $groupsData = [];

        foreach ($studentGroups as $group) {
            $groupsData[] = $this->formatStudentGroupData($group);
        }

        return [
            'id' => $school->getId(),
            'name' => $school->getName(),
            'address' => $school->getAddress(),
            'phone' => $school->getPhone(),
            'studentGroups' => $groupsData,
            'studentGroupCount' => count($studentGroups),
            'studentCount' => $this->countSchoolStudents($school),
            'teacherCount' => count($school->getTeachers())
        ];
    }

    private function formatStudentGroupData(StudentGroup $studentGroup): array
    {
        $students = $studentGroup->getStudents();
        $studentsData = [];

        foreach ($students as $student) {
            $studentsData[] = $this->formatStudentData($student);
        }

        $teacher = $studentGroup->getTeacher();

        return [
            'id' => $studentGroup->getId(),
            'name' => $studentGroup->getName(),
            'schoolId' => $studentGroup->getSchool()->getId(),
            'schoolName' => $studentGroup->getSchool()->getName(),
            'teacherId' => $teacher ? $teacher->getId() : null,
            'teacherName' => $teacher ? $teacher->getFullName() : null,
            'students' => $studentsData,
            'studentCount' => count($students)
        ];
    }

    private function formatStudentData(Student $student): array
    {
        $studentGroup = $student->getStudentGroup();

        return [
            'id' => $student->getId(),
            'firstName' => $student->getFirstName(),
            'lastName' => $student->getLastName(),
            'fullName' => $student->getFirstName() . ' ' . $student->getLastName(),
            'dateOfBirth' => $student->getDateOfBirth() ? $student->getDateOfBirth()->format('Y-m-d') : null,
            'niveauScolaire' => $student->getNiveauScolaire(),
            'studentGroupId' => $studentGroup->getId(),
            'studentGroupName' => $studentGroup->getName(),
            'schoolId' => $studentGroup->getSchool()->getId(),
            'schoolName' => $studentGroup->getSchool()->getName()
        ];
    }

    private function countSchoolStudents(School $school): int
    {
        $count = 0;
        foreach ($school->getStudentGroups() as $group) {
            $count += count($group->getStudents());
        }
        return $count;
    }

}
