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
}
