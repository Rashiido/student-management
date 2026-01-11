<?php

namespace App\Controller\Admin;

use App\Entity\Attendance;
use App\Entity\School;
use App\Entity\Student;
use App\Entity\StudentGroup;
use App\Entity\Teacher;
use App\Repository\SchoolRepository;
use App\Repository\ScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ScheduleRepository $scheduleRepository
    ) {}

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        // Obtenir les statistiques
        $stats = $this->getStats();
        $teacherHours = $this->getTeacherHoursByMonth(new \DateTimeImmutable('first day of this month'));

        // Obtenir le planning statique (basé sur le PDF)
        $staticSchedule = $this->getStaticSchedule();

        // Afficher la vue du dashboard avec toutes les données
        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'staticSchedule' => $staticSchedule,
            'teacherHours' => $teacherHours,
            'teacherHoursMonth' => (new \DateTimeImmutable('first day of this month'))->format('m/Y'),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Gestion Scolaire')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::section('Gestion');
        yield MenuItem::linkToCrud('Écoles', 'fas fa-school', School::class);
        yield MenuItem::linkToCrud('Enseignants', 'fas fa-chalkboard-teacher', Teacher::class);
        yield MenuItem::linkToRoute('Historique', 'fas fa-history', 'app_attendance_history');
    }

    private function getStats(): array
    {
        return [
            'schools' => $this->entityManager->getRepository(School::class)->count([]),
            'teachers' => $this->entityManager->getRepository(Teacher::class)->count([]),
            'groups' => $this->entityManager->getRepository(StudentGroup::class)->count([]),
            'students' => $this->entityManager->getRepository(Student::class)->count([]),
        ];
    }

    private function getStaticSchedule(): array
    {
        // Planning statique basé sur le PDF - école numéro 12 supprimée
        return [
            [
                'school' => 'م/م بني وكيل',
                'subject' => 'فرنسية',
                'teacher' => 'مريم كروم',
                'day' => 'الثلاثاء',
                'time' => '15:00 - 18:00'
            ],
            [
                'school' => 'مدرسة الخوارزي',
                'subject' => 'فرنسية',
                'teacher' => 'فاطمة الزهراء يزوغ',
                'day' => 'الأحد',
                'time' => '10:00 - 13:00'
            ],
            [
                'school' => 'مدرسة علال بن عبد هللا',
                'subject' => 'فرنسية',
                'teacher' => 'أهيمة الناصر',
                'day' => 'الثلاثاء',
                'time' => '15:00 - 18:00'
            ],
            [
                'school' => 'مدرسة ابن الرشيق',
                'subject' => 'فرنسية',
                'teacher' => 'سارة جاجا',
                'day' => 'السبت',
                'time' => '14:00 - 17:00'
            ],
            [
                'school' => 'م/م الإمام الشافعي (فرعية أولاد عزوز)',
                'subject' => 'فرنسية',
                'teacher' => 'عبد السميع حسنه',
                'day' => 'السبت',
                'time' => '14:00 - 17:00'
            ],
            [
                'school' => 'مدرسة الشهيد محمد بنعودة',
                'subject' => 'فرنسية',
                'teacher' => 'قائمی',
                'day' => 'الأحد',
                'time' => '09:00 - 12:00'
            ],
            [
                'school' => 'مدرسة خالد بن الوليد',
                'subject' => 'فرنسية',
                'teacher' => 'مریم زقانی',
                'day' => 'الأربعاء',
                'time' => '14:00 - 17:00'
            ],
            [
                'school' => 'مدرسة عمر بن الخطاب',
                'subject' => 'فرنسية',
                'teacher' => 'رشيدة غری',
                'day' => 'الأربعاء',
                'time' => '15:00 - 18:00'
            ],
            [
                'school' => 'مدرسة محمد العرايي',
                'subject' => 'فرنسية',
                'teacher' => 'فاطمة الزهراء اخلیقی',
                'day' => 'السبت',
                'time' => '09:00 - 12:00'
            ],
            [
                'school' => 'م/م سیدی بولنوار',
                'subject' => 'فرنسية',
                'teacher' => 'امح حوت',
                'day' => 'السبت',
                'time' => '09:00 - 17:00'
            ],
            [
                'school' => 'م/م أولاد عباد',
                'subject' => 'فرنسية',
                'teacher' => 'بروكینفی عبد الغاق',
                'day' => 'السبت',
                'time' => '09:00 - 15:00'
            ],
            // École numéro 12 SUPPRIMÉE : 'م/م ابن الكثیر (فرعية لعراعرة السفلی)'
            [
                'school' => 'م/م المهدی بن تومرت',
                'subject' => 'فرنسية',
                'teacher' => 'محمد حزار',
                'day' => 'السبت',
                'time' => '09:00 - 15:00'
            ],
            [
                'school' => 'م/م الإمام مسلم (فرعية حاسي لحمر)',
                'subject' => 'فرنسية',
                'teacher' => 'الحخار العون',
                'day' => 'السبت',
                'time' => '08:30 - 14:30'
            ],
            [
                'school' => 'المرکب التربوی عن الصفاء (فرعية صغرو)',
                'subject' => 'فرنسية',
                'teacher' => 'أبواب غانم',
                'day' => 'السبت',
                'time' => '08:30 - 14:30'
            ],
            [
                'school' => 'م/م مصعب بن عمیر',
                'subject' => 'فرنسية',
                'teacher' => 'محمد عباجی',
                'day' => 'السبت',
                'time' => '14:00 - 17:00'
            ],
            [
                'school' => 'م/م عبد الرحمن الداخل',
                'subject' => 'فرنسية',
                'teacher' => 'رشيد عبد الموهی',
                'day' => 'السبت',
                'time' => '15:00 - 18:00'
            ],
            [
                'school' => 'م/م سیدی عطوان (لعراعرة)',
                'subject' => 'فرنسية',
                'teacher' => 'نطبقة البالی',
                'day' => 'الجمعة',
                'time' => '14:00 - 17:00'
            ],
            [
                'school' => 'مدرسة محمد عدلی',
                'subject' => 'فرنسية',
                'teacher' => 'خدیجة قاللی',
                'day' => 'السبت',
                'time' => '14:00 - 17:00'
            ],
        ];
    }

    private function getTeacherHoursByMonth(\DateTimeImmutable $monthStart): array
    {
        $monthEnd = $monthStart->modify('last day of this month');
        $schedules = $this->scheduleRepository->findAll();

        $hoursByTeacher = [];
        foreach ($schedules as $schedule) {
            $group = $schedule->getStudentGroup();
            if (!$group || !$group->getTeacher()) {
                continue;
            }

            $teacher = $group->getTeacher();
            $duration = $this->calculateDurationHours($schedule->getStartTime(), $schedule->getEndTime());
            if ($duration <= 0) {
                continue;
            }

            $occurrences = $this->countWeekdayOccurrences($schedule->getDayOfWeek(), $monthStart, $monthEnd);
            if ($occurrences <= 0) {
                continue;
            }

            $teacherId = $teacher->getId();
            if (!isset($hoursByTeacher[$teacherId])) {
                $hoursByTeacher[$teacherId] = [
                    'teacher' => $teacher,
                    'hours' => 0.0,
                ];
            }

            $hoursByTeacher[$teacherId]['hours'] += $duration * $occurrences;
        }

        $results = array_values($hoursByTeacher);
        usort($results, function (array $a, array $b): int {
            return $b['hours'] <=> $a['hours'];
        });

        return $results;
    }

    private function calculateDurationHours(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
        if ($minutes <= 0) {
            return 0.0;
        }

        return $minutes / 60;
    }

    private function countWeekdayOccurrences(string $dayOfWeek, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $normalized = $this->normalizeDayOfWeek($dayOfWeek);
        if (!$normalized) {
            return 0;
        }

        $first = $start->modify('first ' . $normalized . ' of this month');
        if ($first->format('m') !== $start->format('m')) {
            return 0;
        }

        $diffDays = $first->diff($end)->days;
        if ($diffDays < 0) {
            return 0;
        }

        return 1 + intdiv($diffDays, 7);
    }

    private function normalizeDayOfWeek(string $dayOfWeek): ?string
    {
        $day = strtolower(trim($dayOfWeek));
        $map = [
            'monday' => 'monday',
            'tuesday' => 'tuesday',
            'wednesday' => 'wednesday',
            'thursday' => 'thursday',
            'friday' => 'friday',
            'saturday' => 'saturday',
            'sunday' => 'sunday',
        ];

        return $map[$day] ?? null;
    }
}
