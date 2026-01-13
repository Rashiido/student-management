<?php

namespace App\Controller\Admin;

use App\Entity\Attendance;
use App\Entity\Schedule;
use App\Entity\StudentGroup;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Doctrine\ORM\EntityManagerInterface;

class AttendanceCrudController extends AbstractCrudController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return Attendance::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Présence')
            ->setEntityLabelInPlural('Présences')
            ->setPageTitle('index', 'Liste des %entity_label_plural%')
            ->setPageTitle('new', 'Enregistrer une %entity_label_singular%')
            ->setPageTitle('edit', 'Modifier %entity_label_singular%')
            ->setPageTitle('detail', 'Détails de %entity_label_singular%');
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')->hideOnForm(),
            DateField::new('date', 'Date')
                ->setRequired(true),
            AssociationField::new('schedule', 'Horaire')
                ->setRequired(true),
            AssociationField::new('student', 'Étudiant')
                ->setRequired(true),
            ChoiceField::new('status', 'Statut')
                ->setChoices([
                    'Présent' => 'present',
                    'Absent' => 'absent',
                    'Retard' => 'late',
                    'Excusé' => 'excused',
                ])
                ->setRequired(true),
        ];

        // Filter students by group if we're creating from a specific group
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $groupId = $request->query->get('groupId');

        if ($groupId && $pageName === Crud::PAGE_NEW) {
            $group = $this->entityManager->getRepository(StudentGroup::class)->find($groupId);
            if ($group) {
                // Modify the student field to only show students from this group
                $fields[3] = AssociationField::new('student', 'Étudiant')
                    ->setRequired(true)
                    ->setQueryBuilder(function ($queryBuilder) use ($groupId) {
                        return $queryBuilder
                            ->andWhere('entity.studentGroup = :groupId')
                            ->setParameter('groupId', $groupId);
                    });
            }
        }

        return $fields;
    }

    public function createEntity(string $entityFqcn)
    {
        $attendance = new Attendance();

        // Pre-fill schedule if scheduleId is in the query string
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $scheduleId = $request->query->get('scheduleId');

        if ($scheduleId) {
            $schedule = $this->entityManager->getRepository(Schedule::class)->find($scheduleId);
            if ($schedule) {
                $attendance->setSchedule($schedule);
            }
        }

        // Set default date to today
        $attendance->setDate(new \DateTime());

        return $attendance;
    }
}
