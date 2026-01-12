<?php

namespace App\Controller\Teacher;

use App\Entity\Attendance;
use App\Entity\Schedule;
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

        $teacherSchool = $teacher->getSchool();
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
            'schoolId' => $teacherSchool?->getId(),
            'schoolName' => $teacherSchool?->getName(),
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
        $subject = $data['subject'] ?? null;
        $startTimeStr = $data['startTime'] ?? null;
        $endTimeStr = $data['endTime'] ?? null;
        $attendanceData = $data['attendance'] ?? [];

        if (!$groupId || !$dateStr || !$subject || !$startTimeStr || !$endTimeStr || empty($attendanceData)) {
            return new JsonResponse(['error' => 'Donnees manquantes'], 400);
        }

        $group = $this->entityManager->getRepository(StudentGroup::class)->find($groupId);
        if (!$group) {
            return new JsonResponse(['error' => 'Groupe non trouve'], 404);
        }
        if ($group->getTeacher() !== $teacher) {
            return new JsonResponse(['error' => 'Acces non autorise'], 403);
        }

        $allowedSubjects = ['Math', 'French'];
        if (!in_array($subject, $allowedSubjects, true)) {
            return new JsonResponse(['error' => 'Matiere invalide'], 400);
        }

        $date = new \DateTime($dateStr);
        $startTime = \DateTime::createFromFormat('H:i', (string) $startTimeStr);
        $endTime = \DateTime::createFromFormat('H:i', (string) $endTimeStr);
        if (!$startTime || !$endTime || $endTime <= $startTime) {
            return new JsonResponse(['error' => 'Horaire invalide'], 400);
        }

        $dayOfWeek = $date->format('l');
        $scheduleRepo = $this->entityManager->getRepository(Schedule::class);
        $schedule = $scheduleRepo->findOneBy([
            'studentGroup' => $group,
            'subject' => $subject,
            'dayOfWeek' => $dayOfWeek,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);

        if (!$schedule) {
            $schedule = new Schedule();
            $schedule->setStudentGroup($group);
            $schedule->setSubject($subject);
            $schedule->setDayOfWeek($dayOfWeek);
            $schedule->setStartTime(clone $startTime);
            $schedule->setEndTime(clone $endTime);
            $this->entityManager->persist($schedule);
        }
        $savedCount = 0;

        foreach ($attendanceData as $studentId => $status) {
            $student = $this->entityManager->getRepository(\App\Entity\Student::class)->find($studentId);
            if (!$student || $student->getStudentGroup()?->getId() !== $group->getId()) {
                continue;
            }

            $attendance = $this->entityManager->getRepository(Attendance::class)->findOneBy([
                'student' => $student,
                'date' => $date,
                'startTime' => $startTime,
                'endTime' => $endTime,
            ]);

            if (!$attendance) {
                $attendance = new Attendance();
                $attendance->setStudent($student);
            }

            $attendance->setStudentGroup($group);
            $attendance->setDate($date);
            $attendance->setStartTime($startTime);
            $attendance->setEndTime($endTime);
            $attendance->setSchedule($schedule);
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
}
