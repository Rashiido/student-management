<?php

namespace App\Controller\Admin;

use App\Entity\Teacher;
use App\Entity\User;
use App\Entity\School;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TeacherCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public static function getEntityFqcn(): string
    {
        return Teacher::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Enseignant')
            ->setEntityLabelInPlural('Enseignants')
            ->setPageTitle('index', 'Gestion des enseignants')
            ->setPageTitle('new', 'Ajouter un enseignant')
            ->setPageTitle('edit', 'Modifier un enseignant')
            ->setPageTitle('detail', 'Détails de l\'enseignant')
            ->setSearchFields(['firstName', 'lastName', 'phone', 'school.name', 'user.username'])
            ->setDefaultSort(['lastName' => 'ASC', 'firstName' => 'ASC'])
            ->setPaginatorPageSize(20)
            ->overrideTemplate('crud/index', 'admin/teacher/index.html.twig')
            ->overrideTemplate('crud/detail', 'admin/teacher/detail.html.twig');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('firstName')
            ->add('lastName')
            ->add('school');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setIcon('fa fa-plus')->setLabel('Ajouter'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $a) => $a->setIcon('fa fa-edit'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $a) => $a->setIcon('fa fa-trash'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addPanel('Informations personnelles');

        yield TextField::new('firstName', 'Prénom')->setRequired(true);
        yield TextField::new('lastName', 'Nom')->setRequired(true);
        yield TelephoneField::new('phone', 'Téléphone');

        yield FormField::addPanel('Affectation scolaire');

        yield AssociationField::new('school', 'École')
            ->setCrudController(SchoolCrudController::class)
            ->setRequired(true);

        if (in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT, Crud::PAGE_DETAIL])) {
            yield FormField::addPanel('Informations de connexion');

            yield TextField::new('user.username', 'Nom d\'utilisateur')
                ->onlyOnForms()
                ->setRequired(true)
                ->setHelp('Mot de passe généré automatiquement : username + 123');
        }

        if ($pageName === Crud::PAGE_DETAIL) {
            yield AssociationField::new('user', 'Compte utilisateur')
                ->formatValue(function ($value, $entity) {
                    $user = $entity->getUser();
                    if (!$user) {
                        return 'Aucun compte';
                    }

                    return sprintf(
                        '<strong>Username :</strong> %s<br>
                         <strong>Mot de passe :</strong> <code>%s</code>',
                        $user->getUsername(),
                        $user->getPlainPassword() ?? 'Non défini'
                    );
                });
        }
    }


    public function createEntity(string $entityFqcn)
    {
        $teacher = new Teacher();

        $user = new User();
        $user->setRoles(['ROLE_TEACHER']);

        $teacher->setUser($user);

        return $teacher;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Teacher $teacher */
        $teacher = $entityInstance;
        $user = $teacher->getUser();

        if ($user) {
            if (!$user->getUsername()) {
                $username = $this->generateUniqueUsername(
                    $entityManager,
                    $teacher->getFirstName(),
                    $teacher->getLastName()
                );
                $user->setUsername($username);
            }

            $username = $user->getUsername();
            $generatedPassword = $username . '123';

            // 🔐 HASHED password (login)
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $generatedPassword)
            );

            // 👁️ Plain password (admin display)
            $user->setPlainPassword($generatedPassword);

            $user->setRoles(['ROLE_TEACHER']);

            $entityManager->persist($user);
        }

        parent::persistEntity($entityManager, $teacher);
    }

    private function generateUniqueUsername(
        EntityManagerInterface $em,
        string $firstName,
        string $lastName
    ): string {
        $base = strtolower(preg_replace('/[^a-z0-9.]/', '', "$firstName.$lastName"));
        $username = $base;
        $i = 1;

        while ($em->getRepository(User::class)->findOneBy(['username' => $username])) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }
}
