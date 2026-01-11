<?php
// src/Controller/AttendanceController.php

namespace App\Controller;

use App\Repository\SchoolRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AttendanceController extends AbstractController
{
    #[Route('/attendance', name: 'app_attendance')]
    public function index(SchoolRepository $schoolRepository): Response
    {
        $schools = $schoolRepository->findAll();

        return $this->render('attendance/index.html.twig', [
            'schools' => $schools
        ]);
    }
}
