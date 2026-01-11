<?php

namespace App\Controller\Admin;

use App\Entity\StudentGroup;
use App\Entity\School;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use Doctrine\ORM\EntityManagerInterface;

class StudentGroupCrudController extends AbstractCrudController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return StudentGroup::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Groupe d\'étudiants')
            ->setEntityLabelInPlural('Groupes d\'étudiants')
            ->setPageTitle('index', 'Liste des %entity_label_plural%')
            ->setPageTitle('new', 'Créer un %entity_label_singular%')
            ->setPageTitle('edit', 'Modifier %entity_label_singular%')
            ->setPageTitle('detail', 'Détails de %entity_label_singular%');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name', 'Nom du groupe'),
            AssociationField::new('school', 'École')
                ->setRequired(true),
            AssociationField::new('teacher', 'Enseignant')
                ->setRequired(false),
        ];
    }

    public function createEntity(string $entityFqcn)
    {
        $studentGroup = new StudentGroup();

        // Pre-fill school if schoolId is in the query string
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $schoolId = $request->query->get('schoolId');

        if ($schoolId) {
            $school = $this->entityManager->getRepository(School::class)->find($schoolId);
            if ($school) {
                $studentGroup->setSchool($school);
            }
        }

        return $studentGroup;
    }
}
