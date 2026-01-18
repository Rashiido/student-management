<?php

namespace App\Controller\Teacher;

use App\Entity\Attendance;
use App\Entity\StudentGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEACHER')]
#[Route('/teacher/api', name: 'teacher_api_')]
class AttendanceApiController extends AbstractController
{
    private const FALLBACK_SUBJECT = 'General';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/my-groups', name: 'my_groups', methods: ['GET'])]
    public function getMyGroups(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $teacher = $user?->getTeacher();
        if (!$teacher) {
            return new JsonResponse(['error' => 'No teacher profile found'], 404);
        }

        $primarySchool = $teacher->getSchool();
        $groups = $teacher->getStudentGroups();

        $groupsData = [];
        foreach ($groups as $group) {
            $groupsData[] = [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'studentCount' => $group->getStudents()->count(),
            ];
        }

        return new JsonResponse([
            'schoolId' => $primarySchool?->getId(),
            'schoolName' => $primarySchool?->getName(),
            'groups' => $groupsData,
        ]);
    }

    #[Route('/students-by-group/{groupId}', name: 'students_by_group', methods: ['GET'])]
    public function getStudentsByGroup(int $groupId): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $teacher = $user?->getTeacher();
        if (!$teacher) {
            return new JsonResponse(['error' => 'No teacher profile found'], 404);
        }

        $group = $this->entityManager->getRepository(StudentGroup::class)->find($groupId);
        if (!$group) {
            return new JsonResponse(['error' => 'Groupe non trouve'], 404);
        }

        if ($group->getTeacher() !== $teacher) {
            return new JsonResponse(['error' => 'Acces non autorise a ce groupe'], 403);
        }

        $studentsData = [];
        foreach ($group->getStudents() as $student) {
            $studentsData[] = [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'niveauScolaire' => $student->getNiveauScolaire(),
            ];
        }

        return new JsonResponse(['students' => $studentsData]);
    }

    #[Route('/save-attendance', name: 'save_attendance', methods: ['POST'])]
    public function saveAttendance(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $teacher = $user?->getTeacher();
        if (!$teacher) {
            return new JsonResponse(['error' => 'No teacher profile found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $groupId = $data['groupId'] ?? null;
        $dateStr = $data['date'] ?? null;
        $attendanceData = $data['attendance'] ?? [];

        if (!$groupId || !$dateStr || empty($attendanceData)) {
            return new JsonResponse(['error' => 'Donnees manquantes'], 400);
        }

        $group = $this->entityManager->getRepository(StudentGroup::class)->find($groupId);
        if (!$group) {
            return new JsonResponse(['error' => 'Groupe non trouve'], 404);
        }
        if ($group->getTeacher() !== $teacher) {
            return new JsonResponse(['error' => 'Acces non autorise'], 403);
        }

        $date = new \DateTime($dateStr);
        $savedCount = 0;

        foreach ($attendanceData as $studentId => $status) {
            $student = $this->entityManager->getRepository(\App\Entity\Student::class)->find($studentId);
            if (!$student || $student->getStudentGroup()?->getId() !== $group->getId()) {
                continue;
            }

            $attendance = $this->entityManager->getRepository(Attendance::class)->findOneBy([
                'student' => $student,
                'date' => $date,
            ]);

            if (!$attendance) {
                $attendance = new Attendance();
                $attendance->setStudent($student);
                $attendance->setStudentGroup($group);
                $attendance->setDate($date);
            }

            $status = in_array($status, ['present', 'absent'], true) ? $status : 'present';
            $attendance->setStatus($status);
            $this->entityManager->persist($attendance);
            $savedCount++;
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Presences enregistrees avec succes',
            'count' => $savedCount,
        ]);
    }

    #[Route('/available-dates', name: 'available_dates', methods: ['GET'])]
    public function getAvailableDates(Request $request): JsonResponse
    {
        $groupId = $request->query->get('groupId');
        if (!$groupId) {
            return new JsonResponse(['success' => false, 'error' => 'Group ID required'], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $teacher = $user?->getTeacher();
        if (!$teacher) {
            return new JsonResponse(['success' => false, 'error' => 'No teacher profile found'], 404);
        }

        $group = $this->entityManager->getRepository(StudentGroup::class)->find($groupId);
        if (!$group) {
            return new JsonResponse(['success' => false, 'error' => 'Groupe non trouve'], 404);
        }

        if ($group->getTeacher() !== $teacher) {
            return new JsonResponse(['success' => false, 'error' => 'Acces non autorise a ce groupe'], 403);
        }

        $results = $this->entityManager->getRepository(Attendance::class)
            ->createQueryBuilder('a')
            ->select('DISTINCT a.date')
            ->join('a.studentGroup', 'g')
            ->where('g = :group')
            ->setParameter('group', $group)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();

        $dates = array_map(function (array $row): array {
            $date = $row['date'];
            if (!$date instanceof \DateTimeInterface) {
                $date = new \DateTime((string) $date);
            }

            return [
                'value' => $date->format('Y-m-d'),
                'label' => $date->format('d/m/Y'),
                'full' => $date->format('l d F Y'),
            ];
        }, $results);

        return new JsonResponse([
            'success' => true,
            'dates' => $dates,
        ]);
    }

    #[Route('/available-subjects', name: 'available_subjects', methods: ['GET'])]
    public function getAvailableSubjects(Request $request): JsonResponse
    {
        $groupId = $request->query->get('groupId');
        $date = $request->query->get('date');

        if (!$groupId || !$date) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $teacher = $user?->getTeacher();
        if (!$teacher) {
            return new JsonResponse(['success' => false, 'error' => 'No teacher profile found'], 404);
        }

        $group = $this->entityManager->getRepository(StudentGroup::class)->find($groupId);
        if (!$group) {
            return new JsonResponse(['success' => false, 'error' => 'Groupe non trouve'], 404);
        }

        if ($group->getTeacher() !== $teacher) {
            return new JsonResponse(['success' => false, 'error' => 'Acces non autorise a ce groupe'], 403);
        }

        try {
            $dateValue = new \DateTime((string) $date);
        } catch (\Exception $exception) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid date'], 400);
        }

        $subjectRows = $this->entityManager->getRepository(Attendance::class)
            ->createQueryBuilder('a')
            ->select('DISTINCT sch.subject')
            ->leftJoin('a.schedule', 'sch')
            ->join('a.studentGroup', 'g')
            ->where('g = :group')
            ->andWhere('a.date = :date')
            ->andWhere('sch.subject IS NOT NULL')
            ->setParameter('group', $group)
            ->setParameter('date', $dateValue)
            ->orderBy('sch.subject', 'ASC')
            ->getQuery()
            ->getResult();

        $subjects = array_values(array_filter(array_map(function (array $row): ?string {
            return $row['subject'] ?? null;
        }, $subjectRows)));

        $generalCount = $this->entityManager->getRepository(Attendance::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.studentGroup', 'g')
            ->where('g = :group')
            ->andWhere('a.date = :date')
            ->andWhere('a.schedule IS NULL')
            ->setParameter('group', $group)
            ->setParameter('date', $dateValue)
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $generalCount > 0 && !in_array(self::FALLBACK_SUBJECT, $subjects, true)) {
            $subjects[] = self::FALLBACK_SUBJECT;
        }

        return new JsonResponse([
            'success' => true,
            'subjects' => $subjects,
            'count' => count($subjects),
        ]);
    }

    #[Route('/attendance-history', name: 'attendance_history', methods: ['GET'])]
    public function getAttendanceHistory(Request $request): JsonResponse
    {
        $groupId = $request->query->get('groupId');
        $date = $request->query->get('date');
        $subject = $request->query->get('subject');
        $page = $request->query->getInt('page', 1);
        $limit = max(1, $request->query->getInt('limit', 100));

        if (!$groupId || !$date) {
            return new JsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $teacher = $user?->getTeacher();
        if (!$teacher) {
            return new JsonResponse(['success' => false, 'error' => 'No teacher profile found'], 404);
        }

        $group = $this->entityManager->getRepository(StudentGroup::class)->find($groupId);
        if (!$group) {
            return new JsonResponse(['success' => false, 'error' => 'Groupe non trouve'], 404);
        }

        if ($group->getTeacher() !== $teacher) {
            return new JsonResponse(['success' => false, 'error' => 'Acces non autorise a ce groupe'], 403);
        }

        try {
            $dateValue = new \DateTime((string) $date);
        } catch (\Exception $exception) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid date'], 400);
        }

        $queryBuilder = $this->entityManager->getRepository(Attendance::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.student', 's')
            ->leftJoin('a.schedule', 'sch')
            ->leftJoin('a.studentGroup', 'g')
            ->addSelect('s', 'sch', 'g')
            ->where('g = :group')
            ->andWhere('a.date = :date')
            ->setParameter('group', $group)
            ->setParameter('date', $dateValue)
            ->orderBy('a.date', 'DESC');

        if ($subject) {
            if (self::FALLBACK_SUBJECT === $subject) {
                $queryBuilder->andWhere('a.schedule IS NULL');
            } else {
                $queryBuilder->andWhere('sch.subject = :subject')
                    ->setParameter('subject', $subject);
            }
        }

        $totalQuery = clone $queryBuilder;
        $total = $totalQuery->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $queryBuilder->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $attendances = $queryBuilder->getQuery()->getResult();

        $data = array_map(function (Attendance $attendance): array {
            $schedule = $attendance->getSchedule();
            $student = $attendance->getStudent();
            $group = $attendance->getStudentGroup();
            $startTime = $schedule?->getStartTime() ?? $attendance->getStartTime();
            $endTime = $schedule?->getEndTime() ?? $attendance->getEndTime();
            $timeSlot = $startTime && $endTime
                ? $startTime->format('H:i') . ' - ' . $endTime->format('H:i')
                : '--:-- - --:--';

            return [
                'id' => $attendance->getId(),
                'date' => $attendance->getDate()->format('Y-m-d'),
                'dateFormatted' => $attendance->getDate()->format('d/m/Y'),
                'status' => $attendance->getStatus(),
                'statusLabel' => $this->getStatusLabel($attendance->getStatus()),
                'student' => $student ? [
                    'id' => $student->getId(),
                    'name' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'firstName' => $student->getFirstName(),
                    'lastName' => $student->getLastName(),
                ] : null,
                'group' => $group ? [
                    'id' => $group->getId(),
                    'name' => $group->getName(),
                ] : null,
                'schedule' => [
                    'subject' => $schedule ? $schedule->getSubject() : self::FALLBACK_SUBJECT,
                    'dayOfWeek' => $schedule ? $schedule->getDayOfWeek() : null,
                    'startTime' => $startTime ? $startTime->format('H:i') : null,
                    'endTime' => $endTime ? $endTime->format('H:i') : null,
                    'timeSlot' => $timeSlot,
                ],
            ];
        }, $attendances);

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int) $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    private function getStatusLabel(string $status): string
    {
        $labels = [
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'En retard',
            'excused' => 'Excuse',
        ];

        return $labels[$status] ?? $status;
    }
}
