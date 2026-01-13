<?php

namespace App\Controller\Admin;

use App\Entity\School;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class SchoolCrudController extends AbstractCrudController
{
    public function __construct(private AdminUrlGenerator $adminUrlGenerator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return School::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('École')
            ->setEntityLabelInPlural('Écoles')
            ->setPageTitle('index', 'Gestion des écoles')
            ->setPageTitle('new', 'Créer une école')
            ->setPageTitle('edit', 'Modifier l\'école')
            ->setDefaultSort(['name' => 'ASC'])
            ->overrideTemplate('crud/index', 'admin/school/index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        // Add a custom action to go to your custom school overview
        $viewOverview = Action::new('viewOverview', 'Vue complète', 'fa fa-eye')
            ->linkToUrl(function (School $school) {
                return $this->adminUrlGenerator
                    ->setController(SchoolCrudController::class)
                    ->setAction('index')
                    ->generateUrl();
            })
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $viewOverview)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setIcon('fa fa-plus')->setLabel('Ajouter une école');
            });
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm()
            ->onlyOnDetail();

        yield TextField::new('name', 'Nom de l\'école')
            ->setRequired(true)
            ->setHelp('Nom complet de l\'école');

        yield TextareaField::new('address', 'Adresse')
            ->setRequired(false)
            ->setHelp('Adresse complète de l\'école')
            ->hideOnIndex();

        yield TelephoneField::new('phone', 'Téléphone')
            ->setRequired(false)
            ->setHelp('Numéro de téléphone principal')
            ->hideOnIndex();

        // Show teacher count on index page (optional)
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('teachersCount', 'Nb. enseignants')
                ->onlyOnIndex()
                ->formatValue(function ($value, $entity) {
                    return count($entity->getTeachers());
                });
        }
    }
}
