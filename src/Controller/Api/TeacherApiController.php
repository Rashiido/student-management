<?php

namespace App\Controller\Api;

use App\Entity\Schedule;
use App\Entity\Attendance;
use App\Repository\ScheduleRepository;
use App\Repository\StudentRepository;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/teacher', name: 'api_teacher_')]
#[IsGranted('ROLE_TEACHER')]
class TeacherApiController extends AbstractController
{
    #[Route('/schedules', name: 'schedules', methods: ['GET'])]
    public function getSchedules(ScheduleRepository $scheduleRepo): JsonResponse
    {
        $user = $this->getUser();

        // Check if user is admin
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            // Admin can see all schedules
            $schedules = $scheduleRepo->createQueryBuilder('s')
                ->orderBy('s.dayOfWeek', 'ASC')
                ->addOrderBy('s.startTime', 'ASC')
                ->getQuery()
                ->getResult();
        } else {
            // Teacher can only see their own schedules
            $teacher = $user->getTeacher();

            if (!$teacher) {
                return $this->json(['error' => 'No teacher profile found'], 404);
            }

            $schedules = $scheduleRepo->createQueryBuilder('s')
                ->join('s.studentGroup', 'g')
                ->where('g.teacher = :teacher')
                ->setParameter('teacher', $teacher)
                ->orderBy('s.dayOfWeek', 'ASC')
                ->addOrderBy('s.startTime', 'ASC')
                ->getQuery()
                ->getResult();
        }

        $data = array_map(function($schedule) {
            return [
                'id' => $schedule->getId(),
                'groupName' => $schedule->getStudentGroup()->getName(),
                'subject' => $schedule->getSubject(),
                'dayOfWeek' => $schedule->getDayOfWeek(),
                'startTime' => $schedule->getStartTime()->format('H:i'),
                'endTime' => $schedule->getEndTime()->format('H:i'),
                'studentCount' => $schedule->getStudentGroup()->getStudents()->count(),
                'teacher' => $schedule->getStudentGroup()->getTeacher() ?
                    $schedule->getStudentGroup()->getTeacher()->getFirstName() . ' ' .
                    $schedule->getStudentGroup()->getTeacher()->getLastName() : 'No teacher',
                'displayName' => $schedule->getStudentGroup()->getName() . ' - ' . $schedule->getSubject() .
                    ' (' . $schedule->getDayOfWeek() . ' ' .
                    $schedule->getStartTime()->format('H:i') . '-' .
                    $schedule->getEndTime()->format('H:i') . ')',
            ];
        }, $schedules);

        return $this->json($data);
    }

    #[Route('/schedule/{id}/students', name: 'students', methods: ['GET'])]
    public function getStudents(Schedule $schedule): JsonResponse
    {
        $user = $this->getUser();

        // If user is admin, bypass teacher check
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            $teacher = $user->getTeacher();
            // Verify teacher owns this schedule
            if ($schedule->getStudentGroup()->getTeacher()->getId() !== $teacher->getId()) {
                return $this->json(['error' => 'Unauthorized'], 403);
            }
        }

        $students = $schedule->getStudentGroup()->getStudents();
        $data = array_map(function($student) {
            return [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'dateOfBirth' => $student->getDateOfBirth()?->format('Y-m-d')
            ];
        }, $students->toArray());

        return $this->json(array_values($data));
    }

    #[Route('/attendance', name: 'mark_attendance', methods: ['POST'])]
    public function markAttendance(
        Request $request,
        EntityManagerInterface $em,
        ScheduleRepository $scheduleRepo,
        StudentRepository $studentRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        $schedule = $scheduleRepo->find($data['scheduleId']);

        // If user is not admin, check if they own the schedule
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            $teacher = $user->getTeacher();
            if ($schedule->getStudentGroup()->getTeacher()->getId() !== $teacher->getId()) {
                return $this->json(['error' => 'Unauthorized'], 403);
            }
        }

        $date = new \DateTime($data['date']);

        foreach ($data['attendance'] as $studentId => $status) {
            $student = $studentRepo->find($studentId);

            // Check if attendance exists
            $attendance = $em->getRepository(Attendance::class)->findOneBy([
                'student' => $student,
                'schedule' => $schedule,
                'date' => $date
            ]);

            if (!$attendance) {
                $attendance = new Attendance();
                $attendance->setStudent($student);
                $attendance->setSchedule($schedule);
                $attendance->setDate($date);
            }

            $attendance->setStatus($status);
            $em->persist($attendance);
        }

        $em->flush();

        return $this->json(['success' => true]);
    }
}
