<?php

namespace App\Controller\Admin;

use App\Repository\AttendanceRepository;
use App\Repository\TeacherRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/teacher-hours')]
class TeacherHoursController extends AbstractController
{
    #[Route('', name: 'admin_teacher_hours', methods: ['GET'])]
    public function index(
        Request $request,
        AttendanceRepository $attendanceRepository,
        TeacherRepository $teacherRepository
    ): Response {
        $startDateInput = $request->query->get('start_date');
        $endDateInput = $request->query->get('end_date');

        $startDate = null;
        $endDate = null;

        if ($startDateInput) {
            try {
                $startDate = new \DateTime($startDateInput);
            } catch (\Exception $e) {
                $startDate = null;
            }
        }

        if ($endDateInput) {
            try {
                $endDate = new \DateTime($endDateInput);
            } catch (\Exception $e) {
                $endDate = null;
            }
        }

        $hoursByTeacher = $attendanceRepository->getTeacherHoursSummary($startDate, $endDate);
        $teachers = $teacherRepository->findAll();

        $rows = [];
        $totalHours = 0.0;

        foreach ($teachers as $teacher) {
            $hours = $hoursByTeacher[$teacher->getId()] ?? 0.0;
            $rows[] = [
                'teacher' => $teacher,
                'hours' => $hours,
            ];
            $totalHours += $hours;
        }

        return $this->render('admin/teacher/hours.html.twig', [
            'rows' => $rows,
            'totalHours' => $totalHours,
            'startDate' => $startDateInput,
            'endDate' => $endDateInput,
        ]);
    }
}
