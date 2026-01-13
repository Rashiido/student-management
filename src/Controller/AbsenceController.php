<?php

namespace App\Controller;

use App\Repository\AttendanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AbsenceController extends AbstractController
{
    #[Route('/absence', name: 'app_absence')]
    public function index(AttendanceRepository $attendanceRepository): Response
    {
        $absences = $attendanceRepository->createQueryBuilder('a')
            ->where('a.status != :status')
            ->setParameter('status', 'present')
            ->getQuery()
            ->getResult();

        return $this->render('absence/index.html.twig', [
            'absences' => $absences,
        ]);
    }
}
