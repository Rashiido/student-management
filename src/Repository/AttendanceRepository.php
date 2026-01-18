<?php

namespace App\Repository;

use App\Entity\Attendance;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attendance>
 */
class AttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attendance::class);
    }

    public function countTodayByTeacher(Teacher $teacher): int
    {
        return $this->createQueryBuilder('a')
            ->select('count(a.id)')
            ->join('a.studentGroup', 'sg')
            ->where('sg.teacher = :teacher')
            ->andWhere('a.date = :today')
            ->setParameter('teacher', $teacher)
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecentByTeacher(Teacher $teacher, int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.studentGroup', 'sg')
            ->where('sg.teacher = :teacher')
            ->setParameter('teacher', $teacher)
            ->orderBy('a.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByTeacherWithFilter(Teacher $teacher, ?string $groupId, ?string $startDate, ?string $endDate): array
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.studentGroup', 'sg')
            ->where('sg.teacher = :teacher')
            ->setParameter('teacher', $teacher)
            ->orderBy('a.date', 'DESC');

        if ($groupId) {
            $qb->andWhere('sg.id = :groupId')
                ->setParameter('groupId', $groupId);
        }

        if ($startDate) {
            $qb->andWhere('a.date >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $qb->andWhere('a.date <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate));
        }

        return $qb->getQuery()->getResult();
    }

    public function getTeacherHoursSummary(?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'IDENTITY(sg.teacher) AS teacherId',
                'a.date AS date',
                'a.startTime AS startTime',
                'a.endTime AS endTime',
                'sch.startTime AS scheduleStart',
                'sch.endTime AS scheduleEnd',
                'sg.id AS groupId'
            )
            ->join('a.studentGroup', 'sg')
            ->leftJoin('a.schedule', 'sch')
            ->andWhere('(a.startTime IS NOT NULL OR sch.startTime IS NOT NULL)')
            ->andWhere('(a.endTime IS NOT NULL OR sch.endTime IS NOT NULL)');

        if ($startDate) {
            $qb->andWhere('a.date >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('a.date <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $totals = [];

        foreach ($rows as $row) {
            $teacherId = (int) $row['teacherId'];
            $startValue = $row['startTime'] ?? $row['scheduleStart'];
            $endValue = $row['endTime'] ?? $row['scheduleEnd'];

            $startTime = $this->normalizeTimeValue($startValue);
            $endTime = $this->normalizeTimeValue($endValue);

            if (!$startTime || !$endTime || $endTime <= $startTime) {
                continue;
            }

            $duration = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600;
            if ($duration <= 0) {
                continue;
            }

            $totals[$teacherId] = ($totals[$teacherId] ?? 0.0) + $duration;
        }

        return $totals;
    }

    private function normalizeTimeValue(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        $time = \DateTime::createFromFormat('H:i:s', (string) $value)
            ?: \DateTime::createFromFormat('H:i', (string) $value);

        return $time ?: null;
    }
}
