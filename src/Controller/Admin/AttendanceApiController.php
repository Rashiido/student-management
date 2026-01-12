<?php

namespace App\Controller\Admin;

use App\Entity\Attendance;
use App\Entity\Schedule;
use App\Entity\StudentGroup;
use App\Entity\Student;
use App\Repository\SchoolRepository;
use App\Repository\StudentGroupRepository;
use App\Repository\ScheduleRepository;
use App\Repository\AttendanceRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class AttendanceApiController extends AbstractController
{
    #[Route('/admin/api/groups-by-school/{schoolId}', name: 'api_groups_by_school')]
    public function getGroupsBySchool(int $schoolId, StudentGroupRepository $groupRepo): JsonResponse
    {
        $groups = $groupRepo->findBy(['school' => $schoolId]);

        $data = array_map(function ($group) {
            return [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'teacher' => $group->getTeacher() ? $group->getTeacher()->getFirstName() . ' ' . $group->getTeacher()->getLastName() : null
            ];
        }, $groups);

        return $this->json($data);
    }

    #[Route('/admin/api/schedules-by-group/{groupId}', name: 'api_schedules_by_group')]
    public function getSchedulesByGroup(
        int                    $groupId,
        Request                $request,
        ScheduleRepository     $scheduleRepo,
        StudentGroupRepository $groupRepo
    ): JsonResponse
    {
        // Get schoolId from request for validation
        $schoolId = $request->query->get('schoolId');

        if ($schoolId) {
            // Validate group belongs to school
            $group = $groupRepo->findOneBy(['id' => $groupId, 'school' => $schoolId]);
            if (!$group) {
                return $this->json(['error' => 'Groupe non trouvÃ© dans cette Ã©cole'], 404);
            }
        }

        $schedules = $scheduleRepo->findBy(['studentGroup' => $groupId]);

        $data = array_map(function ($schedule) {
            return [
                'id' => $schedule->getId(),
                'subject' => $schedule->getSubject(),
                'dayOfWeek' => $schedule->getDayOfWeek(),
                'startTime' => $schedule->getStartTime()->format('H:i'),
                'endTime' => $schedule->getEndTime()->format('H:i')
            ];
        }, $schedules);

        return $this->json($data);
    }

    #[Route('/admin/api/students-by-group/{groupId}', name: 'api_students_by_group')]
    public function getStudentsByGroup(
        int                    $groupId,
        Request                $request,
        StudentGroupRepository $groupRepo,
        StudentRepository      $studentRepo
    ): JsonResponse
    {
        $schoolId = $request->query->get('schoolId');

        if (!$schoolId) {
            return $this->json(['error' => 'School ID required'], 400);
        }

        // Validate group belongs to school
        $group = $groupRepo->findOneBy(['id' => $groupId, 'school' => $schoolId]);
        if (!$group) {
            return $this->json(['error' => 'Groupe non trouvÃ© dans cette Ã©cole'], 404);
        }

        // Get students from this group AND this school
        $students = $studentRepo->findBy([
            'studentGroup' => $groupId,
            'school' => $schoolId
        ]);

        $data = array_map(function ($student) {
            return [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'niveauScolaire' => $student->getNiveauScolaire(),
                'dateOfBirth' => $student->getDateOfBirth() ? $student->getDateOfBirth()->format('Y-m-d') : null
            ];
        }, $students);

        return $this->json(['students' => $data]);
    }

    #[Route('/admin/api/save-custom-attendance', name: 'api_save_custom_attendance', methods: ['POST'])]
    public function saveCustomAttendance(
        Request                $request,
        EntityManagerInterface $entityManager,
        StudentGroupRepository $groupRepo,
        StudentRepository      $studentRepo
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $schoolId = $data['schoolId'];
        $groupId = $data['groupId'];
        $subject = $data['subject'];
        $startTime = $data['startTime'];
        $endTime = $data['endTime'];
        $date = new \DateTime($data['date']);
        $attendanceData = $data['attendance'];

        // 1. Validate group belongs to school
        $studentGroup = $groupRepo->findOneBy([
            'id' => $groupId,
            'school' => $schoolId
        ]);

        if (!$studentGroup) {
            return $this->json(['success' => false, 'error' => 'Groupe invalide ou n\'appartient pas Ã  cette Ã©cole']);
        }

        // 2. Get the day of week from the selected date
        $dayOfWeek = $date->format('l'); // Monday, Tuesday, etc.

        // 3. Convert times to DateTime objects
        $startDateTime = \DateTime::createFromFormat('H:i', $startTime);
        $endDateTime = \DateTime::createFromFormat('H:i', $endTime);

        // 4. ALWAYS create a new schedule (on the fly)
        $schedule = new Schedule();
        $schedule->setStudentGroup($studentGroup);
        $schedule->setSubject($subject);
        $schedule->setDayOfWeek($dayOfWeek);
        $schedule->setStartTime($startDateTime);
        $schedule->setEndTime($endDateTime);

        $entityManager->persist($schedule);
        $entityManager->flush(); // Flush to get schedule ID

        // 5. Save attendance for each student
        $savedCount = 0;
        $errors = [];

        foreach ($attendanceData as $studentId => $status) {
            // Check student belongs to this group AND school
            $student = $studentRepo->findOneBy([
                'id' => $studentId,
                'studentGroup' => $groupId,
                'school' => $schoolId
            ]);

            if (!$student) {
                $errors[] = "Ã‰tudiant ID $studentId non trouvÃ© dans ce groupe/Ã©cole";
                continue;
            }

            // Check if attendance already exists for this date and parameters
            $existingAttendance = $entityManager->getRepository(Attendance::class)->createQueryBuilder('a')
                ->leftJoin('a.schedule', 's')
                ->where('a.student = :student')
                ->andWhere('a.date = :date')
                ->andWhere('s.studentGroup = :group')
                ->andWhere('s.subject = :subject')
                ->andWhere('s.startTime = :startTime')
                ->andWhere('s.endTime = :endTime')
                ->setParameter('student', $student)
                ->setParameter('date', $date)
                ->setParameter('group', $studentGroup)
                ->setParameter('subject', $subject)
                ->setParameter('startTime', $startDateTime)
                ->setParameter('endTime', $endDateTime)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingAttendance) {
                // Update existing
                $existingAttendance->setStatus($status);
                $existingAttendance->setStudentGroup($studentGroup);
            } else {
                // Create new
                $attendance = new Attendance();
                $attendance->setStudent($student);
                $attendance->setSchedule($schedule);
                $attendance->setStudentGroup($studentGroup);
                $attendance->setDate($date);
                $attendance->setStatus($status);

                $entityManager->persist($attendance);
            }
            $savedCount++;
        }

        try {
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => "$savedCount prÃ©sences enregistrÃ©es",
                'scheduleId' => $schedule->getId(),
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/admin/api/export-custom-attendance', name: 'api_export_custom_attendance', methods: ['POST'])]
    public function exportCustomAttendance(
        Request                $request,
        StudentGroupRepository $groupRepo
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $schoolId = $data['schoolId'] ?? null;
        $groupId = $data['groupId'];
        $subject = $data['subject'];
        $startTime = $data['startTime'];
        $endTime = $data['endTime'];
        $date = $data['date'];
        $students = $data['students'] ?? [];

        if ($schoolId) {
            // Validate group belongs to school
            $group = $groupRepo->findOneBy(['id' => $groupId, 'school' => $schoolId]);
            if (!$group) {
                return $this->json(['error' => 'Groupe invalide'], 400);
            }
        }

        // Here you would generate Excel file
        // For now, return JSON with download link structure

        $exportData = [
            'fileName' => "presences_" . date('Y-m-d_H-i') . ".xlsx",
            'data' => [
                'Ã‰cole' => $schoolId ? "Ã‰cole #$schoolId" : "Toutes les Ã©coles",
                'Groupe' => $data['groupName'] ?? "Groupe #$groupId",
                'MatiÃ¨re' => $subject,
                'Date' => $date,
                'Horaire' => "$startTime - $endTime",
                'Ã‰tudiants' => array_map(function ($studentId, $studentData) {
                    return [
                        'ID' => $studentId,
                        'Nom' => $studentData['firstName'] . ' ' . $studentData['lastName'],
                        'Statut' => $studentData['status'] ?? 'present'
                    ];
                }, array_keys($students), array_values($students))
            ]
        ];

        return $this->json($exportData);
    }

    // Keep your existing methods but add school validation where needed
    #[Route('/admin/api/attendance-data/{scheduleId}/{date}', name: 'api_attendance_data')]
    public function getAttendanceData(
        int                  $scheduleId,
        string               $date,
        Request              $request,
        ScheduleRepository   $scheduleRepo,
        AttendanceRepository $attendanceRepo
    ): JsonResponse
    {
        $schoolId = $request->query->get('schoolId');

        $schedule = $scheduleRepo->find($scheduleId);
        if (!$schedule) {
            return $this->json(['error' => 'Schedule not found'], 404);
        }

        // Validate schedule belongs to school
        if ($schoolId && $schedule->getStudentGroup()->getSchool()->getId() != $schoolId) {
            return $this->json(['error' => 'Schedule does not belong to this school'], 403);
        }

        $dateObj = new \DateTime($date);
        $group = $schedule->getStudentGroup();

        // Get existing attendance for this date and schedule
        $existingAttendance = $attendanceRepo->createQueryBuilder('a')
            ->where('a.schedule = :schedule')
            ->andWhere('a.date = :date')
            ->setParameter('schedule', $schedule)
            ->setParameter('date', $dateObj)
            ->getQuery()
            ->getResult();

        $attendanceMap = [];
        foreach ($existingAttendance as $attendance) {
            if ($attendance->getStudent()) {
                $attendanceMap[$attendance->getStudent()->getId()] = $attendance->getStatus();
            }
        }

        // Get all students in the group
        $students = $group->getStudents()->toArray();
        $studentsData = array_map(function ($student) use ($attendanceMap) {
            return [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'dateOfBirth' => $student->getDateOfBirth() ? $student->getDateOfBirth()->format('Y-m-d') : null
            ];
        }, $students);

        return $this->json([
            'groupName' => $group->getName(),
            'subject' => $schedule->getSubject(),
            'date' => $date,
            'students' => $studentsData,
            'attendance' => $attendanceMap
        ]);
    }

    #[Route('/admin/api/save-attendance', name: 'api_save_attendance', methods: ['POST'])]
    public function saveAttendance(
        Request                $request,
        EntityManagerInterface $entityManager,
        ScheduleRepository     $scheduleRepo,
        StudentRepository      $studentRepo,
        AttendanceRepository   $attendanceRepo
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $scheduleId = $data['scheduleId'];
        $date = new \DateTime($data['date']);
        $attendanceData = $data['attendance'];
        $schoolId = $data['schoolId'] ?? null;

        $schedule = $scheduleRepo->find($scheduleId);
        if (!$schedule) {
            return $this->json(['success' => false, 'error' => 'Schedule not found']);
        }

        // Validate schedule belongs to school
        if ($schoolId && $schedule->getStudentGroup()->getSchool()->getId() != $schoolId) {
            return $this->json(['success' => false, 'error' => 'Schedule does not belong to this school']);
        }

        try {
            foreach ($attendanceData as $studentId => $status) {
                $student = $studentRepo->find($studentId);
                if (!$student) continue;

                // Check if attendance already exists
                $existing = $attendanceRepo->findOneBy([
                    'schedule' => $schedule,
                    'student' => $student,
                    'date' => $date
                ]);

                if ($existing) {
                    // Update existing
                    $existing->setStatus($status);
                    $existing->setStudentGroup($schedule->getStudentGroup()); // Set group
                } else {
                    // Create new
                    $attendance = new Attendance();
                    $attendance->setSchedule($schedule);
                    $attendance->setStudent($student);
                    $attendance->setStudentGroup($schedule->getStudentGroup()); // Set group
                    $attendance->setDate($date);
                    $attendance->setStatus($status);
                    $entityManager->persist($attendance);
                }
            }

            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Attendance saved']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/mark-attendance', name: 'app_mark_attendance')]
    public function attendanceMarking(SchoolRepository $schoolRepository): Response
    {
        $schools = $schoolRepository->findAll();

        return $this->render('admin/attendance/attendance.html.twig', [
            'schools' => $schools
        ]);
    }

    #[Route('/attendance-history', name: 'app_attendance_history')]
    public function attendanceHistory(SchoolRepository $schoolRepository): Response
    {
        $schools = $schoolRepository->findAll();

        return $this->render('admin/attendance/history.html.twig', [
            'schools' => $schools
        ]);
    }

    #[Route('/admin/api/attendance-history', name: 'api_attendance_history', methods: ['GET'])]
    public function getAttendanceHistory(
        Request                $request,
        AttendanceRepository   $attendanceRepo,
        StudentGroupRepository $groupRepo,
        StudentRepository      $studentRepo
    ): JsonResponse
    {
        $schoolId = $request->query->get('schoolId');
        $groupId = $request->query->get('groupId');
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');
        $subject = $request->query->get('subject');
        $status = $request->query->get('status');
        $studentId = $request->query->get('studentId');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

        // Build query
        $queryBuilder = $attendanceRepo->createQueryBuilder('a')
            ->leftJoin('a.student', 's')
            ->leftJoin('a.schedule', 'sch')
            ->leftJoin('a.studentGroup', 'g')
            ->leftJoin('g.school', 'sc')
            ->addSelect('s', 'sch', 'g', 'sc')
            ->orderBy('a.date', 'DESC')
            ->addOrderBy('sch.startTime', 'ASC');

        // Apply filters
        if ($schoolId) {
            $queryBuilder->andWhere('sc.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        if ($groupId) {
            $queryBuilder->andWhere('g.id = :groupId')
                ->setParameter('groupId', $groupId);
        }

        if ($startDate) {
            $queryBuilder->andWhere('a.date >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $queryBuilder->andWhere('a.date <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate));
        }

        if ($subject) {
            $queryBuilder->andWhere('sch.subject = :subject')
                ->setParameter('subject', $subject);
        }

        if ($status) {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($studentId) {
            $queryBuilder->andWhere('s.id = :studentId')
                ->setParameter('studentId', $studentId);
        }

        // Get total count for pagination
        $totalQuery = clone $queryBuilder;
        $total = $totalQuery->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        // Apply pagination
        $queryBuilder->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $attendances = $queryBuilder->getQuery()->getResult();

        // Format data for JSON response
        $data = array_map(function ($attendance) {
            /** @var Attendance $attendance */
            $schedule = $attendance->getSchedule();
            $student = $attendance->getStudent();
            $group = $attendance->getStudentGroup();

            return [
                'id' => $attendance->getId(),
                'date' => $attendance->getDate()->format('Y-m-d'),
                'dateFormatted' => $attendance->getDate()->format('d/m/Y'),
                'status' => $attendance->getStatus(),
                'statusLabel' => $this->getStatusLabel($attendance->getStatus()),
                'student' => $student ? [
                    'id' => $student->getId(),
                    'name' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'niveau' => $student->getNiveauScolaire()
                ] : null,
                'group' => $group ? [
                    'id' => $group->getId(),
                    'name' => $group->getName()
                ] : null,
                'schedule' => $schedule ? [
                    'subject' => $schedule->getSubject(),
                    'dayOfWeek' => $schedule->getDayOfWeek(),
                    'startTime' => $schedule->getStartTime()->format('H:i'),
                    'endTime' => $schedule->getEndTime()->format('H:i'),
                    'timeSlot' => $schedule->getStartTime()->format('H:i') . ' - ' . $schedule->getEndTime()->format('H:i')
                ] : null,
                'school' => $group && $group->getSchool() ? [
                    'id' => $group->getSchool()->getId(),
                    'name' => $group->getSchool()->getName()
                ] : null
            ];
        }, $attendances);

        return $this->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/admin/api/attendance-stats', name: 'api_attendance_stats', methods: ['GET'])]
    public function getAttendanceStats(
        Request              $request,
        AttendanceRepository $attendanceRepo
    ): JsonResponse
    {
        $schoolId = $request->query->get('schoolId');
        $groupId = $request->query->get('groupId');
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');

        $queryBuilder = $attendanceRepo->createQueryBuilder('a')
            ->leftJoin('a.studentGroup', 'g')
            ->leftJoin('g.school', 'sc');

        if ($schoolId) {
            $queryBuilder->andWhere('sc.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        if ($groupId) {
            $queryBuilder->andWhere('g.id = :groupId')
                ->setParameter('groupId', $groupId);
        }

        if ($startDate) {
            $queryBuilder->andWhere('a.date >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $queryBuilder->andWhere('a.date <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate));
        }

        // Get counts by status
        $statusCounts = $queryBuilder->select('a.status, COUNT(a.id) as count')
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();

        $stats = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'total' => 0
        ];

        foreach ($statusCounts as $row) {
            $status = $row['status'];
            $count = $row['count'];
            if (isset($stats[$status])) {
                $stats[$status] = $count;
            }
            $stats['total'] += $count;
        }

        // Get attendance by date (for chart)
        $dateStats = $queryBuilder->select("DATE_FORMAT(a.date, '%Y-%m-%d') as date, a.status, COUNT(a.id) as count")
            ->groupBy("DATE_FORMAT(a.date, '%Y-%m-%d'), a.status")
            ->orderBy('date', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'stats' => $stats,
            'dateStats' => $dateStats
        ]);
    }

    #[Route('/admin/api/export-history', name: 'api_export_history', methods: ['POST'])]
    public function exportHistory(
        Request              $request,
        AttendanceRepository $attendanceRepo
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $schoolId = $data['schoolId'] ?? null;
        $groupId = $data['groupId'] ?? null;
        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        $subject = $data['subject'] ?? null;
        $status = $data['status'] ?? null;

        // Build query (same as getAttendanceHistory but without pagination)
        $queryBuilder = $attendanceRepo->createQueryBuilder('a')
            ->leftJoin('a.student', 's')
            ->leftJoin('a.schedule', 'sch')
            ->leftJoin('a.studentGroup', 'g')
            ->leftJoin('g.school', 'sc')
            ->addSelect('s', 'sch', 'g', 'sc')
            ->orderBy('a.date', 'DESC');

        // Apply filters
        if ($schoolId) {
            $queryBuilder->andWhere('sc.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        if ($groupId) {
            $queryBuilder->andWhere('g.id = :groupId')
                ->setParameter('groupId', $groupId);
        }

        if ($startDate) {
            $queryBuilder->andWhere('a.date >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $queryBuilder->andWhere('a.date <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate));
        }

        if ($subject) {
            $queryBuilder->andWhere('sch.subject = :subject')
                ->setParameter('subject', $subject);
        }

        if ($status) {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        $attendances = $queryBuilder->getQuery()->getResult();

        // Prepare data for export
        $exportData = [];
        foreach ($attendances as $attendance) {
            $exportData[] = [
                'Date' => $attendance->getDate()->format('d/m/Y'),
                'Ã‰lÃ¨ve' => $attendance->getStudent()->getFirstName() . ' ' . $attendance->getStudent()->getLastName(),
                'Groupe' => $attendance->getStudentGroup()->getName(),
                'Ã‰cole' => $attendance->getStudentGroup()->getSchool()->getName(),
                'MatiÃ¨re' => $attendance->getSchedule()->getSubject(),
                'Horaire' => $attendance->getSchedule()->getStartTime()->format('H:i') . ' - ' . $attendance->getSchedule()->getEndTime()->format('H:i'),
                'Statut' => $this->getStatusLabel($attendance->getStatus()),
                'Niveau' => $attendance->getStudent()->getNiveauScolaire()
            ];
        }

        return $this->json([
            'success' => true,
            'fileName' => 'historique_presences_' . date('Y-m-d_H-i') . '.xlsx',
            'data' => $exportData,
            'count' => count($exportData)
        ]);
    }

    private function getStatusLabel(string $status): string
    {
        $labels = [
            'present' => 'PrÃ©sent',
            'absent' => 'Absent',
            'late' => 'En retard',
            'excused' => 'ExcusÃ©'
        ];

        return $labels[$status] ?? $status;
    }

    #[Route('/admin/api/available-dates', name: 'api_available_dates', methods: ['GET'])]
    public function getAvailableDates(
        Request              $request,
        AttendanceRepository $attendanceRepo
    ): JsonResponse
    {
        $schoolId = $request->query->get('schoolId');
        $groupId = $request->query->get('groupId');

        $queryBuilder = $attendanceRepo->createQueryBuilder('a')
            ->select('DISTINCT a.date')
            ->leftJoin('a.studentGroup', 'g')
            ->leftJoin('g.school', 's')
            ->orderBy('a.date', 'DESC');

        if ($schoolId) {
            $queryBuilder->andWhere('s.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        if ($groupId) {
            $queryBuilder->andWhere('g.id = :groupId')
                ->setParameter('groupId', $groupId);
        }

        $results = $queryBuilder->getQuery()->getResult();

        $dates = array_map(function($row) {
            $date = $row['date'];
            return [
                'value' => $date->format('Y-m-d'),
                'label' => $date->format('d/m/Y'),
                'full' => $date->format('l d F Y') //
            ];
        }, $results);

        return $this->json([
            'success' => true,
            'dates' => $dates
        ]);
    }

    #[Route('/admin/api/available-students', name: 'api_available_students', methods: ['GET'])]
    public function getAvailableStudents(
        Request              $request,
        AttendanceRepository $attendanceRepo
    ): JsonResponse
    {
        $schoolId = $request->query->get('schoolId');
        $groupId = $request->query->get('groupId');
        $date = $request->query->get('date');

        $queryBuilder = $attendanceRepo->createQueryBuilder('a')
            ->select('DISTINCT s.id, s.firstName, s.lastName')
            ->leftJoin('a.student', 's')
            ->leftJoin('a.studentGroup', 'g')
            ->leftJoin('g.school', 'sc')
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC');

        if ($schoolId) {
            $queryBuilder->andWhere('sc.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
        }

        if ($groupId) {
            $queryBuilder->andWhere('g.id = :groupId')
                ->setParameter('groupId', $groupId);
        }

        if ($date) {
            $queryBuilder->andWhere('a.date = :date')
                ->setParameter('date', new \DateTime($date));
        }

        $results = $queryBuilder->getQuery()->getResult();

        $students = array_map(function ($row) {
            return [
                'id' => $row['id'],
                'name' => $row['firstName'] . ' ' . $row['lastName'],
                'firstName' => $row['firstName'],
                'lastName' => $row['lastName']
            ];
        }, $results);

        return $this->json([
            'success' => true,
            'students' => $students
        ]);
    }
    #[Route('/admin/api/available-subjects', name: 'admin_api_available_subjects', methods: ['GET'])]
    public function getAvailableSubjects(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $schoolId = $request->query->get('schoolId');
        $groupId = $request->query->get('groupId');
        $date = $request->query->get('date');

        if (!$schoolId || !$groupId || !$date) {
            return new JsonResponse(['error' => 'Parametres manquants'], 400);
        }

        $subjects = $entityManager->createQueryBuilder()
            ->select('DISTINCT s.subject AS subject')
            ->from('App\\Entity\\Attendance', 'a')
            ->join('a.schedule', 's')
            ->join('s.studentGroup', 'g')
            ->join('g.school', 'sc')
            ->where('g.id = :groupId')
            ->andWhere('sc.id = :schoolId')
            ->andWhere('a.date = :date')
            ->setParameter('groupId', $groupId)
            ->setParameter('schoolId', $schoolId)
            ->setParameter('date', new \DateTime($date))
            ->orderBy('s.subject', 'ASC')
            ->getQuery()
            ->getResult();

        $subjectNames = array_map(function ($item) {
            return $item['subject'];
        }, $subjects);

        $subjectDisplayNames = [];
        foreach ($subjectNames as $subject) {
            switch ($subject) {
                case 'Math':
                    $subjectDisplayNames[] = 'Mathematiques';
                    break;
                case 'French':
                    $subjectDisplayNames[] = 'Francais';
                    break;
                default:
                    $subjectDisplayNames[] = $subject;
            }
        }

        return new JsonResponse([
            'success' => true,
            'subjects' => $subjectNames,
            'displayNames' => $subjectDisplayNames,
            'count' => count($subjectNames)
        ]);
    }



}




