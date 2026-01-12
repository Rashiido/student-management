<?php

namespace App\Controller\Teacher;

use App\Entity\Attendance;
use App\Entity\Schedule;
use App\Entity\StudentGroup;
use App\Repository\AttendanceRepository;
use App\Repository\ScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEACHER')]
#[Route('/teacher/attendance')]
class AttendanceController extends AbstractController
{
    #[Route('/mark', name: 'teacher_attendance_mark')]
    public function mark(Request $request, EntityManagerInterface $em, ScheduleRepository $scheduleRepository): Response
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

        $groups = $teacher->getStudentGroups();
        $selectedGroup = null;
        if ($request->query->has('group_id')) {
            $selectedGroup = $em->getRepository(StudentGroup::class)->find($request->query->get('group_id'));
            if ($selectedGroup && $selectedGroup->getTeacher() !== $teacher) {
                throw $this->createAccessDeniedException('Invalid group selected.');
            }
        }

        $subjects = [
            'Math' => 'Mathematiques',
            'French' => 'Francais',
        ];
        $timeOptions = $this->buildTimeOptions('08:00', '19:00', 30);
        $selectedStartTime = (string) ($request->request->get('start_time') ?? '08:00');
        $selectedEndTime = (string) ($request->request->get('end_time') ?? '10:00');
        $selectedSubject = (string) ($request->request->get('subject') ?? 'Math');

        if ($request->isMethod('POST') && $selectedGroup) {
            $attendances = $request->request->all('attendances');
            $date = new \DateTime($request->request->get('attendance_date', 'today'));

            $startTime = $this->parseTime($selectedStartTime);
            $endTime = $this->parseTime($selectedEndTime);
            $hasError = false;
            if (!$startTime || !$endTime || $endTime <= $startTime) {
                $this->addFlash('error', 'Heure invalide. Verifiez le debut et la fin.');
                $hasError = true;
            } elseif (!in_array($selectedStartTime, $timeOptions, true) || !in_array($selectedEndTime, $timeOptions, true)) {
                $this->addFlash('error', 'Heure invalide. Selectionnez une heure autorisee.');
                $hasError = true;
            } elseif (!array_key_exists($selectedSubject, $subjects)) {
                $this->addFlash('error', 'Matiere invalide. Selectionnez une matiere autorisee.');
                $hasError = true;
            }

            $durationHours = 0.0;
            if (!$hasError && $startTime && $endTime) {
                $durationHours = $this->calculateDurationHours($startTime, $endTime);
                if ($durationHours > 6) {
                    $this->addFlash('error', 'Duree invalide. Maximum 6 heures par seance.');
                    $hasError = true;
                }
            }

            if (!$hasError) {
                $weekStart = (new \DateTimeImmutable($date->format('Y-m-d')))->modify('monday this week');
                $weekEnd = $weekStart->modify('sunday this week');
                [$weeklyHours, $sessionExists] = $this->getWeekHoursAndSession(
                    $teacher,
                    $weekStart,
                    $weekEnd,
                    $date,
                    $startTime,
                    $endTime,
                    $selectedGroup,
                    $em
                );
                $projectedHours = $weeklyHours + ($sessionExists ? 0.0 : $durationHours);
                if ($projectedHours > 9) {
                    $this->addFlash('error', 'Limite depassee. Maximum 9 heures par semaine.');
                    $hasError = true;
                }
            }

            if (!$hasError) {
                $dayOfWeek = $date->format('l');
                $schedule = $scheduleRepository->findOneBy([
                    'studentGroup' => $selectedGroup,
                    'subject' => $selectedSubject,
                    'dayOfWeek' => $dayOfWeek,
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                ]);

                if (!$schedule) {
                    $schedule = new Schedule();
                    $schedule->setStudentGroup($selectedGroup);
                    $schedule->setSubject($selectedSubject);
                    $schedule->setDayOfWeek($dayOfWeek);
                    $schedule->setStartTime(clone $startTime);
                    $schedule->setEndTime(clone $endTime);
                    $em->persist($schedule);
                }

                foreach ($attendances as $studentId => $status) {
                    $student = $em->getRepository(\App\Entity\Student::class)->find($studentId);
                    if ($student && $student->getStudentGroup() === $selectedGroup) {
                        $existingAttendance = $em->getRepository(Attendance::class)->findOneBy([
                            'student' => $student,
                            'date' => $date,
                            'startTime' => $startTime,
                            'endTime' => $endTime,
                        ]);

                        $attendance = $existingAttendance ?? (new Attendance());
                        if (!$existingAttendance) {
                            $attendance->setStudent($student);
                            $attendance->setDate($date);
                            $attendance->setStudentGroup($selectedGroup);
                        }

                        $attendance->setStartTime($startTime);
                        $attendance->setEndTime($endTime);
                        $attendance->setSchedule($schedule);

                        $status = in_array($status, ['present', 'absent'], true) ? $status : 'present';
                        $attendance->setStatus($status);
                        $em->persist($attendance);
                    }
                }
                $em->flush();
                $this->addFlash('success', 'La feuille de presence a ete enregistree.');
                return $this->redirectToRoute('teacher_attendance_mark', ['group_id' => $selectedGroup->getId()]);
            }
        }

        return $this->render('teacher/attendance/mark.html.twig', [
            'teacher' => $teacher,
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'students' => $selectedGroup ? $selectedGroup->getStudents() : [],
            'today' => new \DateTime('today'),
            'timeOptions' => $timeOptions,
            'selectedStartTime' => $selectedStartTime,
            'selectedEndTime' => $selectedEndTime,
            'subjects' => $subjects,
            'selectedSubject' => $selectedSubject,
        ]);
    }

    #[Route('/history', name: 'teacher_attendance_history')]
    public function history(Request $request, AttendanceRepository $attendanceRepository): Response
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

        $groupId = $request->query->get('group');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        $attendances = $attendanceRepository->findByTeacherWithFilter($teacher, $groupId, $startDate, $endDate);

        return $this->render('teacher/attendance/history.html.twig', [
            'teacher' => $teacher,
            'groups' => $teacher->getStudentGroups(),
            'attendances' => $attendances,
            'selectedGroupId' => $groupId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    private function buildTimeOptions(string $start, string $end, int $stepMinutes): array
    {
        $times = [];
        $startTime = \DateTimeImmutable::createFromFormat('H:i', $start);
        $endTime = \DateTimeImmutable::createFromFormat('H:i', $end);
        if (!$startTime || !$endTime) {
            return $times;
        }

        for ($time = $startTime; $time <= $endTime; $time = $time->modify('+' . $stepMinutes . ' minutes')) {
            $times[] = $time->format('H:i');
        }

        return $times;
    }

    private function parseTime(string $value): ?\DateTime
    {
        $time = \DateTime::createFromFormat('H:i', $value);
        if (!$time) {
            return null;
        }

        return $time;
    }

    private function calculateDurationHours(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
        if ($minutes <= 0) {
            return 0.0;
        }

        return $minutes / 60;
    }

    private function getWeekHoursAndSession(
        \App\Entity\Teacher $teacher,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        \DateTimeInterface $date,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        StudentGroup $group,
        EntityManagerInterface $em
    ): array {
        $rows = $em->getRepository(Attendance::class)
            ->createQueryBuilder('a')
            ->select('a.date AS date', 'a.startTime AS startTime', 'a.endTime AS endTime', 'IDENTITY(a.studentGroup) AS groupId')
            ->join('a.studentGroup', 'sg')
            ->where('sg.teacher = :teacher')
            ->andWhere('a.date BETWEEN :start AND :end')
            ->andWhere('a.startTime IS NOT NULL')
            ->andWhere('a.endTime IS NOT NULL')
            ->groupBy('a.date, a.startTime, a.endTime, groupId')
            ->setParameter('teacher', $teacher)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        $totalHours = 0.0;
        $sessionExists = false;
        $targetDate = $date->format('Y-m-d');
        $targetStart = $startTime->format('H:i');
        $targetEnd = $endTime->format('H:i');

        foreach ($rows as $row) {
            $rowDate = $this->normalizeDateValue($row['date']);
            $rowStart = $this->normalizeTimeValue($row['startTime']);
            $rowEnd = $this->normalizeTimeValue($row['endTime']);

            if ($rowStart && $rowEnd) {
                $rowStartTime = $this->parseTimeValue($row['startTime']);
                $rowEndTime = $this->parseTimeValue($row['endTime']);
                if ($rowStartTime && $rowEndTime) {
                    $totalHours += $this->calculateDurationHours($rowStartTime, $rowEndTime);
                }
            }

            if ($rowDate === $targetDate && (int) $row['groupId'] === $group->getId()
                && $rowStart === $targetStart && $rowEnd === $targetEnd
            ) {
                $sessionExists = true;
            }
        }

        return [$totalHours, $sessionExists];
    }

    private function normalizeDateValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function normalizeTimeValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $time = \DateTime::createFromFormat('H:i:s', (string) $value)
            ?: \DateTime::createFromFormat('H:i', (string) $value);

        return $time ? $time->format('H:i') : '';
    }

    private function parseTimeValue(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        $time = \DateTime::createFromFormat('H:i:s', (string) $value)
            ?: \DateTime::createFromFormat('H:i', (string) $value);

        return $time ?: null;
    }
}
