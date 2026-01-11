<?php

namespace App\Controller\Teacher;

use App\Entity\Attendance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEACHER')]
class TeacherDashboardController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/teacher', name: 'teacher_dashboard')]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $teacher = $user?->getTeacher();
        if (!$teacher) {
            return $this->redirectToRoute('app_login');
        }

        $groups = $teacher->getStudentGroups();
        $totalStudents = 0;
        foreach ($groups as $group) {
            $totalStudents += count($group->getStudents());
        }

        $attendanceStats = $this->getAttendanceStats($groups, new \DateTime('today'));

        return $this->render('teacher/dashboard/index.html.twig', [
            'teacher' => $teacher,
            'totalGroups' => count($groups),
            'totalStudents' => $totalStudents,
            'attendanceStats' => $attendanceStats,
        ]);
    }

    private function getAttendanceStats($groups, \DateTime $date): array
    {
        if (count($groups) === 0) {
            return ['present' => 0, 'absent' => 0];
        }

        $rows = $this->entityManager->getRepository(Attendance::class)
            ->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) AS count')
            ->where('a.studentGroup IN (:groups)')
            ->andWhere('a.date = :date')
            ->setParameter('groups', $groups)
            ->setParameter('date', $date)
            ->groupBy('a.status')
            ->getQuery()
            ->getArrayResult();

        $stats = ['present' => 0, 'absent' => 0];
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            if (array_key_exists($status, $stats)) {
                $stats[$status] = (int) $row['count'];
            }
        }

        return $stats;
    }
}
